<?php
ob_start();
// ── Process POST before any output ──────────────────────────────────────────
session_save_path(sys_get_temp_dir());
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE assets SET status='inactive' WHERE id=?")->execute([$id]);
        flash('success', 'Asset removed from active registry.');
        header('Location: assets.php'); exit;
    }

    if (in_array($action, ['add','edit'])) {
        $name     = trim($_POST['name']);
        $catId    = (int)$_POST['category_id'] ?: null;
        $unit     = trim($_POST['unit']);
        $cost     = (float)$_POST['unit_cost'];
        $minStock = (int)$_POST['min_stock'];
        $location = trim($_POST['location']);
        $desc     = trim($_POST['description']);

        if ($action === 'add') {
            $code = generateAssetCode();
            $db->prepare("INSERT INTO assets (asset_code,name,category_id,unit,unit_cost,min_stock,location,description) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$code,$name,$catId,$unit,$cost,$minStock,$location,$desc]);
            flash('success', "Asset «{$name}» added (code: {$code}).");
        } else {
            $id = (int)$_POST['id'];
            $db->prepare("UPDATE assets SET name=?,category_id=?,unit=?,unit_cost=?,min_stock=?,location=?,description=? WHERE id=?")
               ->execute([$name,$catId,$unit,$cost,$minStock,$location,$desc,$id]);
            flash('success', "Asset «{$name}» updated.");
        }
        header('Location: assets.php'); exit;
    }
}

// ── Now load header (outputs HTML) ──────────────────────────────────────────
require_once 'includes/header.php';

// ── Fetch data ───────────────────────────────────────────────────────────────
$cats  = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$items = $db->query("
    SELECT a.*, c.name cat_name
    FROM assets a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.status = 'active'
    ORDER BY a.asset_code
")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM assets WHERE id=? AND status='active'");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}
?>

<div class="page-header">
  <div>
    <h1>Asset Registry</h1>
    <p>All active items and their current stock levels.</p>
  </div>
  <button class="btn btn-accent" data-open-modal="modal-asset">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Asset
  </button>
</div>

<div class="table-wrap">
  <div class="table-toolbar">
    <h2>All Assets (<?= count($items) ?>)</h2>
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" class="js-search" data-table="tbl-assets" placeholder="Search assets…">
    </div>
  </div>
  <div class="table-scroll">
    <table id="tbl-assets">
      <thead><tr>
        <th>Code</th><th>Name</th><th>Category</th><th>Unit</th>
        <th>Unit Cost</th><th>Stock Qty</th><th>Min</th><th>Location</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if(empty($items)): ?>
        <tr><td colspan="10">
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            <p>No assets found. Add your first asset.</p>
          </div>
        </td></tr>
      <?php else: foreach($items as $a): ?>
        <tr>
          <td><span class="mono"><?= sanitize($a['asset_code']) ?></span></td>
          <td style="font-weight:500;"><?= sanitize($a['name']) ?></td>
          <td><?= sanitize($a['cat_name'] ?? '—') ?></td>
          <td><?= sanitize($a['unit']) ?></td>
          <td style="font-family:var(--font-mono);font-size:13px;"><?= formatMoney((float)$a['unit_cost']) ?></td>
          <td style="font-weight:600;"><?= $a['stock_qty'] ?></td>
          <td style="color:var(--muted);"><?= $a['min_stock'] ?></td>
          <td style="color:var(--muted);font-size:13px;"><?= sanitize($a['location'] ?? '—') ?></td>
          <td><?= stockBadge((int)$a['stock_qty'], (int)$a['min_stock']) ?></td>
          <td>
            <div class="actions">
              <a href="assets.php?edit=<?= $a['id'] ?>" class="btn btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger js-confirm" data-msg="Remove «<?= sanitize($a['name']) ?>» from the registry?">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                  Remove
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay <?= $editing ? 'open' : '' ?>" id="modal-asset">
  <div class="modal">
    <div class="modal-header">
      <h3><?= $editing ? 'Edit Asset' : 'Add New Asset' ?></h3>
      <button class="modal-close" onclick="window.location='assets.php'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="<?= $editing ? 'edit' : 'add' ?>">
      <?php if($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
      <div class="form-grid">
        <div class="form-group full">
          <label>Asset Name *</label>
          <input type="text" name="name" required placeholder="e.g. Laptop Dell Inspiron 15"
            value="<?= sanitize($editing['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category_id">
            <option value="">— Select Category —</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($editing['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                <?= sanitize($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Unit</label>
          <select name="unit">
            <?php foreach(['pcs','unit','ream','box','set','roll','ltr','kg','m'] as $u): ?>
              <option value="<?= $u ?>" <?= ($editing['unit'] ?? 'pcs') === $u ? 'selected' : '' ?>><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Unit Cost (₱)</label>
          <input type="number" name="unit_cost" step="0.01" min="0" placeholder="0.00"
            value="<?= $editing['unit_cost'] ?? 0 ?>">
        </div>
        <div class="form-group">
          <label>Min Stock Level</label>
          <input type="number" name="min_stock" min="0" placeholder="0"
            value="<?= $editing['min_stock'] ?? 0 ?>">
        </div>
        <div class="form-group full">
          <label>Storage Location</label>
          <input type="text" name="location" placeholder="e.g. Warehouse A"
            value="<?= sanitize($editing['location'] ?? '') ?>">
        </div>
        <div class="form-group full">
          <label>Description</label>
          <textarea name="description"><?= sanitize($editing['description'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-accent"><?= $editing ? 'Save Changes' : 'Add Asset' ?></button>
        <a href="assets.php" class="btn">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
