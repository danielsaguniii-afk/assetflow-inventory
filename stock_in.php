<?php
ob_start();
session_save_path(sys_get_temp_dir());
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    if ($action === 'save') {
        $assetId=$_POST['asset_id']; $qty=(int)$_POST['qty'];
        $unitCost=(float)$_POST['unit_cost']; $supplier=trim($_POST['supplier']);
        $receivedBy=trim($_POST['received_by']); $remarks=trim($_POST['remarks']);
        $dateIn=$_POST['date_in'];
        if ($assetId < 1 || $qty < 1) { flash('error','Asset and quantity are required.'); header('Location: stock_in.php?action=add'); exit; }
        $ref = generateRef('SI');
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO stock_in (reference_no,asset_id,qty,unit_cost,supplier,received_by,remarks,date_in) VALUES (?,?,?,?,?,?,?,?)")->execute([$ref,$assetId,$qty,$unitCost,$supplier,$receivedBy,$remarks,$dateIn]);
            $db->prepare("UPDATE assets SET stock_qty=stock_qty+? WHERE id=?")->execute([$qty,$assetId]);
            $db->commit();
            flash('success',"Stock In recorded. Reference: {$ref}");
        } catch(Exception $e) { $db->rollBack(); flash('error','Failed: '.$e->getMessage()); }
        header('Location: stock_in.php'); exit;
    }
    if ($action === 'delete') {
        $id=(int)$_POST['id'];
        $s=$db->prepare("SELECT * FROM stock_in WHERE id=?"); $s->execute([$id]); $si=$s->fetch();
        if ($si) {
            $db->beginTransaction();
            try { $db->prepare("DELETE FROM stock_in WHERE id=?")->execute([$id]); $db->prepare("UPDATE assets SET stock_qty=GREATEST(stock_qty-?,0) WHERE id=?")->execute([$si['qty'],$si['asset_id']]); $db->commit(); flash('success','Deleted and quantity reversed.'); }
            catch(Exception $e) { $db->rollBack(); flash('error','Delete failed.'); }
        }
        header('Location: stock_in.php'); exit;
    }
}
require_once 'includes/header.php';
// ── Fetch ─────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'list';
$assets = $db->query("SELECT id, asset_code, name, unit_cost, unit FROM assets WHERE status='active' ORDER BY name")->fetchAll();

$records = $db->query("
    SELECT si.*, a.name asset_name, a.asset_code, a.unit
    FROM stock_in si JOIN assets a ON a.id = si.asset_id
    ORDER BY si.created_at DESC
    LIMIT 200
")->fetchAll();

// Summary
$totalRecords  = $db->query("SELECT COUNT(*) FROM stock_in")->fetchColumn();
$totalQtyIn    = $db->query("SELECT COALESCE(SUM(qty),0) FROM stock_in")->fetchColumn();
$totalCostIn   = $db->query("SELECT COALESCE(SUM(total_cost),0) FROM stock_in")->fetchColumn();
$todayIn       = $db->query("SELECT COALESCE(SUM(qty),0) FROM stock_in WHERE date_in = CURDATE()")->fetchColumn();
?>

<div class="page-header">
  <div>
    <h1>Stock In</h1>
    <p>Record received goods and inventory additions.</p>
  </div>
  <?php if($action !== 'add'): ?>
  <a href="stock_in.php?action=add" class="btn btn-accent">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Stock In
  </a>
  <?php endif; ?>
</div>

<!-- Summary cards -->
<div class="cards-grid" style="grid-template-columns:repeat(4,1fr);">
  <div class="stat-card accent">
    <div class="label">Total Transactions</div>
    <div class="value"><?= number_format($totalRecords) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Total Units Received</div>
    <div class="value" style="color:var(--success);"><?= number_format($totalQtyIn) ?></div>
  </div>
  <div class="stat-card info">
    <div class="label">Total Value In</div>
    <div class="value" style="font-size:18px;"><?= formatMoney((float)$totalCostIn) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Today's Receipts</div>
    <div class="value" style="color:var(--accent);"><?= number_format($todayIn) ?></div>
  </div>
</div>

<?php if($action === 'add'): ?>
<!-- ── Add Form ── -->
<div class="form-card">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:1.5rem;">
    <a href="stock_in.php" class="btn btn-sm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13"><polyline points="15 18 9 12 15 6"/></svg>
      Back
    </a>
    <h2 style="font-family:var(--font-head);font-size:15px;font-weight:600;">New Stock In Entry</h2>
  </div>

  <form method="POST">
    <input type="hidden" name="_action" value="save">
    <div class="form-grid">
      <div class="form-group full">
        <label>Asset *</label>
        <select name="asset_id" id="asset-select" required onchange="fillCost(this)">
          <option value="">— Select Asset —</option>
          <?php foreach($assets as $a): ?>
            <option value="<?= $a['id'] ?>" data-cost="<?= $a['unit_cost'] ?>" data-unit="<?= sanitize($a['unit']) ?>">
              [<?= sanitize($a['asset_code']) ?>] <?= sanitize($a['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Quantity Received *</label>
        <input type="number" name="qty" id="qty-in" min="1" required placeholder="0" oninput="calcTotal()">
      </div>
      <div class="form-group">
        <label>Unit Cost (₱)</label>
        <input type="number" name="unit_cost" id="unit-cost" step="0.01" min="0" placeholder="0.00" oninput="calcTotal()">
      </div>
      <div class="form-group full">
        <label>Computed Total Cost</label>
        <input type="text" id="total-cost-display" readonly placeholder="₱ 0.00" style="color:var(--accent);font-family:var(--font-mono);">
      </div>
      <div class="form-group">
        <label>Supplier / Source</label>
        <input type="text" name="supplier" placeholder="Supplier name or source">
      </div>
      <div class="form-group">
        <label>Received By</label>
        <input type="text" name="received_by" placeholder="Name of receiver">
      </div>
      <div class="form-group">
        <label>Date Received *</label>
        <input type="date" name="date_in" required value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group full">
        <label>Remarks</label>
        <textarea name="remarks" placeholder="Optional notes or remarks…"></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-accent">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
        Record Stock In
      </button>
      <a href="stock_in.php" class="btn">Cancel</a>
    </div>
  </form>
</div>
<script>
function fillCost(sel) {
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('unit-cost').value = opt.dataset.cost || '';
  calcTotal();
}
function calcTotal() {
  const qty  = parseFloat(document.getElementById('qty-in').value) || 0;
  const cost = parseFloat(document.getElementById('unit-cost').value) || 0;
  const total = qty * cost;
  document.getElementById('total-cost-display').value =
    '₱ ' + total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>

<?php else: ?>
<!-- ── Transaction List ── -->
<div class="table-wrap">
  <div class="table-toolbar">
    <h2>Stock In Records</h2>
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" class="js-search" data-table="tbl-si" placeholder="Search records…">
    </div>
  </div>
  <div class="table-scroll">
    <table id="tbl-si">
      <thead><tr>
        <th>Ref #</th><th>Date</th><th>Asset</th><th>Qty</th>
        <th>Unit Cost</th><th>Total Cost</th><th>Supplier</th><th>Received By</th><th>Action</th>
      </tr></thead>
      <tbody>
      <?php if(empty($records)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
            <p>No stock-in records yet. Click "New Stock In" to get started.</p>
          </div>
        </td></tr>
      <?php else: foreach($records as $r): ?>
        <tr>
          <td><span class="mono"><?= sanitize($r['reference_no']) ?></span></td>
          <td style="color:var(--muted);font-size:13px;"><?= date('M j, Y', strtotime($r['date_in'])) ?></td>
          <td>
            <div style="font-weight:500;"><?= sanitize($r['asset_name']) ?></div>
            <div class="mono" style="font-size:11px;"><?= sanitize($r['asset_code']) ?></div>
          </td>
          <td>
            <span class="badge badge-success">+<?= number_format($r['qty']) ?> <?= sanitize($r['unit']) ?></span>
          </td>
          <td class="mono"><?= formatMoney((float)$r['unit_cost']) ?></td>
          <td class="mono" style="color:var(--accent);"><?= formatMoney((float)$r['total_cost']) ?></td>
          <td><?= sanitize($r['supplier'] ?: '—') ?></td>
          <td><?= sanitize($r['received_by'] ?: '—') ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger js-confirm"
                data-msg="Delete this stock-in record? Stock quantity will be reversed.">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                Delete
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
