<?php
require_once __DIR__ . '/../_admin.php';
$admin = require_role(['Super Admin','Admin','Staff']);
ensure_support_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        db()->prepare("UPDATE policies SET status='hidden', active=0, updated_at=NOW() WHERE id=?")->execute([$id]);
        audit_log('hide_policy', 'policies', $id);
        flash('success', 'Đã ẩn chính sách.');
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $status = ($_POST['status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';
        $slug = trim((string)($_POST['slug'] ?? '')) ?: slugify_vi($title);
        if ($title === '' || $content === '') {
            flash('error', 'Vui lòng nhập đầy đủ tiêu đề và nội dung.');
        } elseif ($id > 0) {
            db()->prepare('UPDATE policies SET title=?,slug=?,content=?,status=?,active=?,updated_at=NOW() WHERE id=?')->execute([$title,$slug,$content,$status,$status === 'active' ? 1 : 0,$id]);
            audit_log('update_policy', 'policies', $id);
            flash('success', 'Đã cập nhật chính sách.');
        } else {
            db()->prepare('INSERT INTO policies(title,slug,content,status,active,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$title,$slug,$content,$status,$status === 'active' ? 1 : 0]);
            audit_log('create_policy', 'policies', (int)db()->lastInsertId());
            flash('success', 'Đã thêm chính sách.');
        }
    }
    header('Location: index.php');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM policies WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}
$rows = db()->query('SELECT * FROM policies ORDER BY status ASC, title ASC')->fetchAll();
admin_boot('policies', 'Chính sách');
?>
<h1 class="section-title">Quản lý chính sách</h1>
<form method="post" class="table-card mb-4" data-confirm-submit="Xác nhận lưu chính sách?">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="row g-3">
    <div class="col-md-5"><label>Tiêu đề</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
    <div class="col-md-4"><label>Slug</label><input class="form-control" name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="Tự tạo nếu để trống"></div>
    <div class="col-md-3"><label>Trạng thái</label><select class="form-select" name="status"><option value="active" <?= (($edit['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Hiển thị</option><option value="hidden" <?= (($edit['status'] ?? '') === 'hidden') ? 'selected' : '' ?>>Ẩn</option></select></div>
    <div class="col-12"><label>Nội dung</label><textarea class="form-control" name="content" rows="7" required><?= e($edit['content'] ?? '') ?></textarea></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-dark"><?= $edit ? 'Cập nhật' : 'Thêm chính sách' ?></button><?php if ($edit): ?><a class="btn btn-outline-secondary" href="index.php">Hủy sửa</a><?php endif; ?></div>
  </div>
</form>
<div class="table-card">
  <table class="table datatable">
    <thead><tr><th>Tiêu đề</th><th>Slug</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <tr>
          <td><?= e($p['title']) ?></td>
          <td><?= e($p['slug']) ?></td>
          <td><span class="badge <?= ($p['status'] ?? 'active') === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ($p['status'] ?? 'active') === 'active' ? 'Hiển thị' : 'Ẩn' ?></span></td>
          <td class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-dark" href="?edit=<?= (int)$p['id'] ?>">Sửa</a>
            <form method="post" data-confirm-submit="Ẩn chính sách này?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger">Ẩn</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
