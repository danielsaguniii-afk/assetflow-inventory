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
    if ($action === 'add') {
        $name=trim($_POST['name']); $desc=trim($_POST['description']);
        if ($name) { $db->prepare("INSERT INTO categories (name,description) VALUES (?,?)")->execute([$name,$desc]); flash('success',"Category «{$name}» added."); }
    } elseif ($action === 'edit') {
        $id=(int)$_POST['id']; $name=trim($_POST['name']); $desc=trim($_POST['description']);
        $db->prepare("UPDATE categories SET name=?,description=? WHERE id=?")->execute([$name,$desc,$id]); flash('success','Category updated.');
    } elseif ($action === 'delete') {
        $id=(int)$_POST['id'];
        $count=$db->prepare("SELECT COUNT(*) FROM assets WHERE category_id=? AND status='active'"); $count->execute([$id]);
        if ($count->fetchColumn()>0) { flash('error','Cannot delete — category has active assets.'); }
        else { $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]); flash('success','Category deleted.'); }
    }
    header('Location: categories.php'); exit;
}
require_once 'includes/header.php';
$cats = $db->query("
    SELECT c.*, COUNT(a.id) asset_count
    FROM categories c
    LEFT JOIN assets a ON a.category_id = c.id AND a.status = 'active'
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}
?>

<div class="page-header">
  <div><h1>Categories</h1><p>Organise assets into logical groups.</p></div>
  <button class="btn btn-accent" data-open-modal="modal-cat">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Category
  </button>
</div>

<div class="table-wrap">
  <div class="table-toolbar"><h2>All Categories (<?= count($cats) ?>)</h2></div>
  <table id="tbl-cats">
    <thead><tr><th>#</th><th>Category Name</th><th>Description</th><th>Assets</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($cats as $c): ?>
      <tr>
        <td style="color:var(--muted);font-size:12px;"><?= $c['id'] ?></td>
        <td style="font-weight:500;"><?= sanitize($c['name']) ?></td>
        <td style="color:var(--muted);font-size:13px;"><?= sanitize($c['description'] ?: '—') ?></td>
        <td><span class="badge badge-accent"><?= $c['asset_count'] ?></span></td>
        <td style="color:var(--muted);font-size:12.5px;"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
        <td>
          <div class="actions">
            <a href="categories.php?edit=<?= $c['id'] ?>" class="btn btn-sm">Edit</a>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger js-confirm"
                data-msg="Delete category «<?= sanitize($c['name']) ?>»? Assets won't be deleted.">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal -->
<div class="modal-overlay <?= $editing ? 'open' : '' ?>" id="modal-cat">
  <div class="modal">
    <div class="modal-header">
      <h3><?= $editing ? 'Edit Category' : 'Add Category' ?></h3>
      <button class="modal-close" onclick="window.location='categories.php'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="<?= $editing ? 'edit' : 'add' ?>">
      <?php if($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Category Name *</label>
        <input type="text" name="name" required placeholder="e.g. Electronics" value="<?= sanitize($editing['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description"><?= sanitize($editing['description'] ?? '') ?></textarea>
      </div>
      <div class="form-actions" style="margin-top:1.25rem;">
        <button type="submit" class="btn btn-accent">Save</button>
        <a href="categories.php" class="btn">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
