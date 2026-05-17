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
        $assetId=(int)$_POST['asset_id']; $qty=(int)$_POST['qty'];
        $issuedTo=trim($_POST['issued_to']); $dept=trim($_POST['department']);
        $purpose=trim($_POST['purpose']); $releasedBy=trim($_POST['released_by']);
        $dateOut=$_POST['date_out'];
        if ($assetId<1||$qty<1) { flash('error','Asset and quantity are required.'); header('Location: stock_out.php?action=add'); exit; }
        $s=$db->prepare("SELECT stock_qty FROM assets WHERE id=?"); $s->execute([$assetId]); $asset=$s->fetch();
        if (!$asset||$asset['stock_qty']<$qty) { flash('error',"Insufficient stock. Available: {$asset['stock_qty']}."); header('Location: stock_out.php?action=add'); exit; }
        $ref=generateRef('SO');
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO stock_out (reference_no,asset_id,qty,issued_to,department,purpose,released_by,date_out) VALUES (?,?,?,?,?,?,?,?)")->execute([$ref,$assetId,$qty,$issuedTo,$dept,$purpose,$releasedBy,$dateOut]);
            $db->prepare("UPDATE assets SET stock_qty=stock_qty-? WHERE id=?")->execute([$qty,$assetId]);
            $db->commit(); flash('success',"Stock Out recorded. Reference: {$ref}");
        } catch(Exception $e) { $db->rollBack(); flash('error','Failed: '.$e->getMessage()); }
        header('Location: stock_out.php'); exit;
    }
    if ($action === 'delete') {
        $id=(int)$_POST['id'];
        $s=$db->prepare("SELECT * FROM stock_out WHERE id=?"); $s->execute([$id]); $so=$s->fetch();
        if ($so) {
            $db->beginTransaction();
            try { $db->prepare("DELETE FROM stock_out WHERE id=?")->execute([$id]); $db->prepare("UPDATE assets SET stock_qty=stock_qty+? WHERE id=?")->execute([$so['qty'],$so['asset_id']]); $db->commit(); flash('success','Deleted and quantity restored.'); }
            catch(Exception $e) { $db->rollBack(); flash('error','Delete failed.'); }
        }
        header('Location: stock_out.php'); exit;
    }
}
require_once 'includes/header.php';
// ── Fetch ─────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'list';
$assets = $db->query("SELECT id, asset_code, name, stock_qty, unit FROM assets WHERE status='active' ORDER BY name")->fetchAll();

$records = $db->query("
    SELECT so.*, a.name asset_name, a.asset_code, a.unit
    FROM stock_out so JOIN assets a ON a.id = so.asset_id
    ORDER BY so.created_at DESC
    LIMIT 200
")->fetchAll();

// Summary
$totalRecords  = $db->query("SELECT COUNT(*) FROM stock_out")->fetchColumn();
$totalQtyOut   = $db->query("SELECT COALESCE(SUM(qty),0) FROM stock_out")->fetchColumn();
$todayOut      = $db->query("SELECT COALESCE(SUM(qty),0) FROM stock_out WHERE date_out = CURDATE()")->fetchColumn();
$monthOut      = $db->query("SELECT COALESCE(SUM(qty),0) FROM stock_out WHERE MONTH(date_out)=MONTH(CURDATE()) AND YEAR(date_out)=YEAR(CURDATE())")->fetchColumn();
?>

<div class="page-header">
  <div>
    <h1>Stock Out</h1>
    <p>Issue assets to personnel or departments.</p>
  </div>
  <?php if($action !== 'add'): ?>
  <a href="stock_out.php?action=add" class="btn btn-accent">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><polyline points="16 7 12 3 8 7"/><line x1="12" y1="12" x2="12" y2="3"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
    New Stock Out
  </a>
  <?php endif; ?>
</div>

<!-- Summary cards -->
<div class="cards-grid" style="grid-template-columns:repeat(4,1fr);">
  <div class="stat-card danger">
    <div class="label">Total Transactions</div>
    <div class="value"><?= number_format($totalRecords) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Total Units Issued</div>
    <div class="value" style="color:var(--danger);"><?= number_format($totalQtyOut) ?></div>
  </div>
  <div class="stat-card warn">
    <div class="label">This Month</div>
    <div class="value"><?= number_format($monthOut) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Today's Issues</div>
    <div class="value" style="color:var(--warn);"><?= number_format($todayOut) ?></div>
  </div>
</div>

<?php if($action === 'add'): ?>
<!-- ── Add Form ── -->
<div class="form-card">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:1.5rem;">
    <a href="stock_out.php" class="btn btn-sm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13"><polyline points="15 18 9 12 15 6"/></svg>
      Back
    </a>
    <h2 style="font-family:var(--font-head);font-size:15px;font-weight:600;">New Stock Out Entry</h2>
  </div>

  <form method="POST">
    <input type="hidden" name="_action" value="save">
    <div class="form-grid">
      <div class="form-group full">
        <label>Asset *</label>
        <select name="asset_id" id="asset-select" required onchange="showStock(this)">
          <option value="">— Select Asset —</option>
          <?php foreach($assets as $a): ?>
            <option value="<?= $a['id'] ?>" data-stock="<?= $a['stock_qty'] ?>" data-unit="<?= sanitize($a['unit']) ?>">
              [<?= sanitize($a['asset_code']) ?>] <?= sanitize($a['name']) ?>
              (Available: <?= $a['stock_qty'] ?> <?= sanitize($a['unit']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="stock-info-wrap" style="display:none;">
        <label>Available Stock</label>
        <input type="text" id="stock-display" readonly style="color:var(--success);font-family:var(--font-mono);">
      </div>
      <div class="form-group">
        <label>Quantity to Issue *</label>
        <input type="number" name="qty" id="qty-out" min="1" required placeholder="0">
      </div>
      <div class="form-group">
        <label>Issued To</label>
        <input type="text" name="issued_to" placeholder="Employee / recipient name">
      </div>
      <div class="form-group">
        <label>Department</label>
        <input type="text" name="department" placeholder="e.g. IT, Accounting, HR">
      </div>
      <div class="form-group">
        <label>Released By</label>
        <input type="text" name="released_by" placeholder="Name of person releasing">
      </div>
      <div class="form-group">
        <label>Date Out *</label>
        <input type="date" name="date_out" required value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group full">
        <label>Purpose / Remarks</label>
        <textarea name="purpose" placeholder="Reason for issuing this asset…"></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-accent" onclick="return validateOut()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><polyline points="16 7 12 3 8 7"/><line x1="12" y1="12" x2="12" y2="3"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
        Record Stock Out
      </button>
      <a href="stock_out.php" class="btn">Cancel</a>
    </div>
  </form>
</div>
<script>
function showStock(sel) {
  const opt   = sel.options[sel.selectedIndex];
  const stock = opt.dataset.stock;
  const unit  = opt.dataset.unit || '';
  const wrap  = document.getElementById('stock-info-wrap');
  if (stock !== undefined) {
    wrap.style.display = '';
    document.getElementById('stock-display').value = stock + ' ' + unit;
    document.getElementById('stock-display').style.color =
      parseInt(stock) === 0 ? 'var(--danger)' : parseInt(stock) <= 5 ? 'var(--warn)' : 'var(--success)';
  } else {
    wrap.style.display = 'none';
  }
}
function validateOut() {
  const sel   = document.getElementById('asset-select');
  const opt   = sel.options[sel.selectedIndex];
  const stock = parseInt(opt.dataset.stock) || 0;
  const qty   = parseInt(document.getElementById('qty-out').value) || 0;
  if (qty > stock) {
    alert('Quantity exceeds available stock (' + stock + '). Please adjust.');
    return false;
  }
  return true;
}
</script>

<?php else: ?>
<!-- ── Transaction List ── -->
<div class="table-wrap">
  <div class="table-toolbar">
    <h2>Stock Out Records</h2>
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" class="js-search" data-table="tbl-so" placeholder="Search records…">
    </div>
  </div>
  <div class="table-scroll">
    <table id="tbl-so">
      <thead><tr>
        <th>Ref #</th><th>Date</th><th>Asset</th><th>Qty</th>
        <th>Issued To</th><th>Department</th><th>Released By</th><th>Purpose</th><th>Action</th>
      </tr></thead>
      <tbody>
      <?php if(empty($records)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40"><polyline points="16 7 12 3 8 7"/><line x1="12" y1="12" x2="12" y2="3"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
            <p>No stock-out records yet. Click "New Stock Out" to get started.</p>
          </div>
        </td></tr>
      <?php else: foreach($records as $r): ?>
        <tr>
          <td><span class="mono"><?= sanitize($r['reference_no']) ?></span></td>
          <td style="color:var(--muted);font-size:13px;"><?= date('M j, Y', strtotime($r['date_out'])) ?></td>
          <td>
            <div style="font-weight:500;"><?= sanitize($r['asset_name']) ?></div>
            <div class="mono" style="font-size:11px;"><?= sanitize($r['asset_code']) ?></div>
          </td>
          <td>
            <span class="badge badge-danger">-<?= number_format($r['qty']) ?> <?= sanitize($r['unit']) ?></span>
          </td>
          <td><?= sanitize($r['issued_to'] ?: '—') ?></td>
          <td><?= sanitize($r['department'] ?: '—') ?></td>
          <td><?= sanitize($r['released_by'] ?: '—') ?></td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:12.5px;">
            <?= sanitize($r['purpose'] ?: '—') ?>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger js-confirm"
                data-msg="Delete this stock-out record? Stock quantity will be restored.">
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
