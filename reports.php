<?php
ob_start();
session_save_path(sys_get_temp_dir());
session_start();
if (empty($_SESSION["user"])) { header("Location: login.php"); exit; }
require_once "includes/header.php";

$db = getDB();

// Filters
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');
$type     = $_GET['type'] ?? 'all';
$assetFilter = (int)($_GET['asset_id'] ?? 0);

$assets = $db->query("SELECT id, asset_code, name FROM assets WHERE status='active' ORDER BY name")->fetchAll();

// Build movement query
$movements = [];

if ($type !== 'out') {
    $sql = "SELECT 'IN' txn_type, si.reference_no, si.date_in txn_date,
                   a.asset_code, a.name asset_name, a.unit,
                   si.qty, si.unit_cost, si.total_cost,
                   si.supplier party, si.received_by handled_by, si.remarks
            FROM stock_in si JOIN assets a ON a.id = si.asset_id
            WHERE si.date_in BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    if ($assetFilter) { $sql .= " AND si.asset_id = ?"; $params[] = $assetFilter; }
    $stmt = $db->prepare($sql); $stmt->execute($params);
    $movements = array_merge($movements, $stmt->fetchAll());
}

if ($type !== 'in') {
    $sql = "SELECT 'OUT' txn_type, so.reference_no, so.date_out txn_date,
                   a.asset_code, a.name asset_name, a.unit,
                   so.qty, 0 unit_cost, 0 total_cost,
                   so.issued_to party, so.released_by handled_by, so.purpose remarks
            FROM stock_out so JOIN assets a ON a.id = so.asset_id
            WHERE so.date_out BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    if ($assetFilter) { $sql .= " AND so.asset_id = ?"; $params[] = $assetFilter; }
    $stmt = $db->prepare($sql); $stmt->execute($params);
    $movements = array_merge($movements, $stmt->fetchAll());
}

// Sort by date desc
usort($movements, fn($a,$b) => strcmp($b['txn_date'], $a['txn_date']));

// Totals
$totalIn   = array_sum(array_map(fn($r) => $r['txn_type']==='IN'  ? $r['qty'] : 0, $movements));
$totalOut  = array_sum(array_map(fn($r) => $r['txn_type']==='OUT' ? $r['qty'] : 0, $movements));
$totalCost = array_sum(array_column($movements, 'total_cost'));
?>

<div class="page-header">
  <div><h1>Movement Report</h1><p>Track all inventory transactions over a date range.</p></div>
</div>

<!-- Filter bar -->
<div class="table-wrap" style="margin-bottom:1.5rem;">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;padding:1rem 1.25rem;">
    <div class="form-group" style="min-width:140px;">
      <label>From</label>
      <input type="date" name="from" value="<?= sanitize($fromDate) ?>">
    </div>
    <div class="form-group" style="min-width:140px;">
      <label>To</label>
      <input type="date" name="to" value="<?= sanitize($toDate) ?>">
    </div>
    <div class="form-group" style="min-width:130px;">
      <label>Type</label>
      <select name="type">
        <option value="all" <?= $type==='all'?'selected':'' ?>>All</option>
        <option value="in"  <?= $type==='in' ?'selected':'' ?>>Stock In Only</option>
        <option value="out" <?= $type==='out'?'selected':'' ?>>Stock Out Only</option>
      </select>
    </div>
    <div class="form-group" style="min-width:200px;">
      <label>Asset</label>
      <select name="asset_id">
        <option value="">All Assets</option>
        <?php foreach($assets as $a): ?>
          <option value="<?= $a['id'] ?>" <?= $assetFilter==$a['id']?'selected':'' ?>>
            [<?= sanitize($a['asset_code']) ?>] <?= sanitize($a['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-accent">Apply Filter</button>
    <a href="reports.php" class="btn">Reset</a>
  </form>
</div>

<!-- Summary -->
<div class="cards-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem;">
  <div class="stat-card"><div class="label">Total Records</div><div class="value"><?= count($movements) ?></div></div>
  <div class="stat-card"><div class="label">Total In</div><div class="value" style="color:var(--success);">+<?= number_format($totalIn) ?></div></div>
  <div class="stat-card"><div class="label">Total Out</div><div class="value" style="color:var(--danger);">-<?= number_format($totalOut) ?></div></div>
  <div class="stat-card info"><div class="label">Total Cost (In)</div><div class="value" style="font-size:17px;"><?= formatMoney($totalCost) ?></div></div>
</div>

<!-- Report table -->
<div class="table-wrap">
  <div class="table-toolbar">
    <h2>Transactions: <?= date('M j, Y', strtotime($fromDate)) ?> – <?= date('M j, Y', strtotime($toDate)) ?></h2>
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" class="js-search" data-table="tbl-report" placeholder="Search…">
    </div>
  </div>
  <div class="table-scroll">
    <table id="tbl-report">
      <thead><tr>
        <th>Type</th><th>Ref #</th><th>Date</th><th>Asset</th>
        <th>Qty</th><th>Unit Cost</th><th>Total</th><th>Party</th><th>Handled By</th>
      </tr></thead>
      <tbody>
      <?php if(empty($movements)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <p>No transactions found for the selected period.</p>
          </div>
        </td></tr>
      <?php else: foreach($movements as $m): $isIn = $m['txn_type']==='IN'; ?>
        <tr>
          <td>
            <?php if($isIn): ?>
              <span class="badge badge-success">IN</span>
            <?php else: ?>
              <span class="badge badge-danger">OUT</span>
            <?php endif; ?>
          </td>
          <td><span class="mono"><?= sanitize($m['reference_no']) ?></span></td>
          <td style="color:var(--muted);font-size:13px;"><?= date('M j, Y', strtotime($m['txn_date'])) ?></td>
          <td>
            <div style="font-weight:500;"><?= sanitize($m['asset_name']) ?></div>
            <div class="mono" style="font-size:11px;"><?= sanitize($m['asset_code']) ?></div>
          </td>
          <td>
            <span style="font-weight:600;color:<?= $isIn ? 'var(--success)' : 'var(--danger)' ?>;">
              <?= $isIn ? '+' : '-' ?><?= number_format($m['qty']) ?> <?= sanitize($m['unit']) ?>
            </span>
          </td>
          <td class="mono"><?= $isIn ? formatMoney((float)$m['unit_cost']) : '—' ?></td>
          <td class="mono" style="color:var(--accent);"><?= $isIn ? formatMoney((float)$m['total_cost']) : '—' ?></td>
          <td><?= sanitize($m['party'] ?: '—') ?></td>
          <td><?= sanitize($m['handled_by'] ?: '—') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
