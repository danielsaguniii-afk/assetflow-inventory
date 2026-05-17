<?php require_once 'includes/header.php'; ?>
<?php

$db = getDB();

// Summary stats
$totalAssets  = $db->query("SELECT COUNT(*) FROM assets WHERE status='active'")->fetchColumn();
$totalValue   = $db->query("SELECT SUM(unit_cost * stock_qty) FROM assets WHERE status='active'")->fetchColumn() ?? 0;
$lowStock     = $db->query("SELECT COUNT(*) FROM assets WHERE stock_qty <= min_stock AND stock_qty > 0 AND status='active'")->fetchColumn();
$outOfStock   = $db->query("SELECT COUNT(*) FROM assets WHERE stock_qty = 0 AND status='active'")->fetchColumn();
$todayIn      = $db->query("SELECT COALESCE(SUM(qty),0) FROM stock_in  WHERE date_in  = CURDATE()")->fetchColumn();
$todayOut     = $db->query("SELECT COALESCE(SUM(qty),0) FROM stock_out WHERE date_out = CURDATE()")->fetchColumn();

// Recent stock in
$recentIn = $db->query("
    SELECT si.reference_no, a.name asset_name, a.asset_code, si.qty, si.supplier, si.date_in
    FROM stock_in si JOIN assets a ON a.id = si.asset_id
    ORDER BY si.created_at DESC LIMIT 7
")->fetchAll();

// Recent stock out
$recentOut = $db->query("
    SELECT so.reference_no, a.name asset_name, a.asset_code, so.qty, so.issued_to, so.date_out
    FROM stock_out so JOIN assets a ON a.id = so.asset_id
    ORDER BY so.created_at DESC LIMIT 7
")->fetchAll();

// Low stock items
$lowItems = $db->query("
    SELECT asset_code, name, stock_qty, min_stock, unit
    FROM assets WHERE stock_qty <= min_stock AND status='active'
    ORDER BY stock_qty ASC LIMIT 8
")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Welcome back — here's your inventory at a glance.</p>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="stock_in.php?action=add" class="btn btn-accent">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Stock In
    </a>
    <a href="stock_out.php?action=add" class="btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Stock Out
    </a>
  </div>
</div>

<!-- Stats -->
<div class="cards-grid">
  <div class="stat-card accent">
    <div class="label">Total Assets</div>
    <div class="value"><?= number_format($totalAssets) ?></div>
    <div class="sub">Active items in registry</div>
  </div>
  <div class="stat-card info">
    <div class="label">Inventory Value</div>
    <div class="value" style="font-size:20px;"><?= formatMoney((float)$totalValue) ?></div>
    <div class="sub">Total stock value</div>
  </div>
  <div class="stat-card warn">
    <div class="label">Low Stock</div>
    <div class="value"><?= $lowStock ?></div>
    <div class="sub">Items below min level</div>
  </div>
  <div class="stat-card danger">
    <div class="label">Out of Stock</div>
    <div class="value"><?= $outOfStock ?></div>
    <div class="sub">Items with zero qty</div>
  </div>
  <div class="stat-card">
    <div class="label">Today's In</div>
    <div class="value" style="color:var(--success);"><?= $todayIn ?></div>
    <div class="sub">Units received today</div>
  </div>
  <div class="stat-card">
    <div class="label">Today's Out</div>
    <div class="value" style="color:var(--danger);"><?= $todayOut ?></div>
    <div class="sub">Units released today</div>
  </div>
</div>

<!-- Two-column: recent transactions + low stock -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">

  <!-- Recent Stock In -->
  <div class="table-wrap">
    <div class="table-toolbar">
      <h2>Recent Stock In</h2>
      <a href="stock_in.php" class="btn btn-sm">View All</a>
    </div>
    <div class="table-scroll">
      <table>
        <thead><tr>
          <th>Ref #</th><th>Asset</th><th>Qty</th><th>Date</th>
        </tr></thead>
        <tbody>
        <?php if(empty($recentIn)): ?>
          <tr><td colspan="4"><div class="empty-state"><p>No stock-in records yet.</p></div></td></tr>
        <?php else: foreach($recentIn as $r): ?>
          <tr>
            <td><span class="mono"><?= sanitize($r['reference_no']) ?></span></td>
            <td>
              <div style="font-weight:500;"><?= sanitize($r['asset_name']) ?></div>
              <div class="mono" style="font-size:11px;"><?= sanitize($r['asset_code']) ?></div>
            </td>
            <td><span class="badge badge-success">+<?= $r['qty'] ?></span></td>
            <td style="color:var(--muted);font-size:12.5px;"><?= date('M j', strtotime($r['date_in'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Stock Out -->
  <div class="table-wrap">
    <div class="table-toolbar">
      <h2>Recent Stock Out</h2>
      <a href="stock_out.php" class="btn btn-sm">View All</a>
    </div>
    <div class="table-scroll">
      <table>
        <thead><tr>
          <th>Ref #</th><th>Asset</th><th>Qty</th><th>Date</th>
        </tr></thead>
        <tbody>
        <?php if(empty($recentOut)): ?>
          <tr><td colspan="4"><div class="empty-state"><p>No stock-out records yet.</p></div></td></tr>
        <?php else: foreach($recentOut as $r): ?>
          <tr>
            <td><span class="mono"><?= sanitize($r['reference_no']) ?></span></td>
            <td>
              <div style="font-weight:500;"><?= sanitize($r['asset_name']) ?></div>
              <div class="mono" style="font-size:11px;"><?= sanitize($r['asset_code']) ?></div>
            </td>
            <td><span class="badge badge-danger">-<?= $r['qty'] ?></span></td>
            <td style="color:var(--muted);font-size:12.5px;"><?= date('M j', strtotime($r['date_out'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Low stock alerts -->
<?php if(!empty($lowItems)): ?>
<div class="table-wrap">
  <div class="table-toolbar">
    <h2>⚠ Low Stock Alerts</h2>
    <a href="assets.php" class="btn btn-sm">View Registry</a>
  </div>
  <div class="table-scroll">
    <table>
      <thead><tr>
        <th>Code</th><th>Asset Name</th><th>Current Qty</th><th>Min Level</th><th>Status</th>
      </tr></thead>
      <tbody>
      <?php foreach($lowItems as $item): ?>
        <tr>
          <td><span class="mono"><?= sanitize($item['asset_code']) ?></span></td>
          <td style="font-weight:500;"><?= sanitize($item['name']) ?></td>
          <td><?= $item['stock_qty'] ?> <?= sanitize($item['unit']) ?></td>
          <td><?= $item['min_stock'] ?> <?= sanitize($item['unit']) ?></td>
          <td><?= stockBadge((int)$item['stock_qty'], (int)$item['min_stock']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
