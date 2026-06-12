<?php
require_once __DIR__ . '/../_admin.php';
ensure_support_schema();
$tables = ['knowledge_base'=>'KB','faq'=>'FAQ','policies'=>'Chính sách'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $table = $_POST['table_name'] ?? '';
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);
    if (!isset($tables[$table])) exit('Không hợp lệ');
    if ($action === 'delete' && $id > 0) {
        if ($table === 'policies') db()->prepare("UPDATE policies SET active=0,status='hidden' WHERE id=?")->execute([$id]);
        else db()->prepare("UPDATE $table SET active=0 WHERE id=?")->execute([$id]);
        audit_log('hide_chatbot_data', $table, $id);
        flash('success','Đã ẩn dữ liệu.');
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        if ($title === '' || $content === '') flash('error','Vui lòng nhập đầy đủ dữ liệu.');
        elseif ($id > 0) {
            if ($table === 'faq') db()->prepare('UPDATE faq SET question=?,answer=?,active=? WHERE id=?')->execute([$title,$content,isset($_POST['active'])?1:0,$id]);
            elseif ($table === 'policies') db()->prepare('UPDATE policies SET title=?,slug=?,excerpt=?,content=?,active=?,status=? WHERE id=?')->execute([$title,slugify_vi($title),mb_strimwidth($content,0,180,'...','UTF-8'),$content,isset($_POST['active'])?1:0,isset($_POST['active'])?'active':'hidden',$id]);
            else db()->prepare('UPDATE knowledge_base SET title=?,content=?,active=? WHERE id=?')->execute([$title,$content,isset($_POST['active'])?1:0,$id]);
            audit_log('update_chatbot_data', $table, $id);
            flash('success','Đã cập nhật dữ liệu.');
        } else {
            if ($table === 'faq') db()->prepare('INSERT INTO faq(question,answer,active) VALUES(?,?,?)')->execute([$title,$content,isset($_POST['active'])?1:0]);
            elseif ($table === 'policies') db()->prepare('INSERT INTO policies(title,slug,excerpt,content,status,active,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$title,slugify_vi($title),mb_strimwidth($content,0,180,'...','UTF-8'),$content,isset($_POST['active'])?'active':'hidden',isset($_POST['active'])?1:0]);
            else db()->prepare('INSERT INTO knowledge_base(title,content,active) VALUES(?,?,?)')->execute([$title,$content,isset($_POST['active'])?1:0]);
            audit_log('create_chatbot_data', $table, (int)db()->lastInsertId());
            flash('success','Đã thêm dữ liệu.');
        }
    }
    header('Location: index.php'); exit;
}
$edit = null; $editTable = $_GET['table'] ?? '';
if (isset($tables[$editTable], $_GET['edit'])) {
    $id = (int)$_GET['edit'];
    if ($editTable === 'faq') $stmt = db()->prepare('SELECT id,question title,answer content,active FROM faq WHERE id=?');
    else $stmt = db()->prepare("SELECT id,title,content,active FROM $editTable WHERE id=?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch() ?: null;
}
admin_boot('chatbot-kb','Chatbot KB');
?>
<h1 class="section-title">Chatbot KB</h1>
<form method="post" class="table-card row g-2 mb-4" data-confirm-submit="Xác nhận lưu dữ liệu chatbot?">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="col-md-2"><select name="table_name" class="form-select"><?php foreach($tables as $key=>$label): ?><option value="<?= e($key) ?>" <?= $editTable===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input name="title" class="form-control" required placeholder="Tiêu đề/câu hỏi" value="<?= e($edit['title'] ?? '') ?>"></div>
  <div class="col-md-5"><input name="content" class="form-control" required placeholder="Nội dung/trả lời" value="<?= e($edit['content'] ?? '') ?>"></div>
  <div class="col-md-1 d-flex align-items-center"><label><input type="checkbox" name="active" <?= (($edit['active'] ?? 1) ? 'checked' : '') ?>> Hiện</label></div>
  <div class="col-md-1"><button class="btn btn-dark w-100"><?= $edit ? 'Lưu' : 'Thêm' ?></button></div>
</form>
<div class="row g-3">
<?php foreach($tables as $table=>$label): ?>
  <div class="col-lg-4"><div class="table-card"><h2 class="h6"><?= e($label) ?></h2>
    <?php
      $query = $table === 'faq' ? 'SELECT id,question title,active FROM faq ORDER BY id DESC LIMIT 25' : "SELECT id,title,active FROM $table ORDER BY id DESC LIMIT 25";
      foreach(db()->query($query) as $r): ?>
      <div class="d-flex justify-content-between gap-2 border-bottom py-2"><span><?= e($r['title']) ?> <?= $r['active']?'':'<small class="text-muted">(ẩn)</small>' ?></span><span class="text-nowrap"><a class="btn btn-sm btn-outline-dark" href="?table=<?= e($table) ?>&edit=<?= (int)$r['id'] ?>">Sửa</a><form method="post" class="d-inline" data-confirm-submit="Bạn có chắc chắn muốn xóa mục này không?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="table_name" value="<?= e($table) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form></span></div>
    <?php endforeach; ?>
  </div></div>
<?php endforeach; ?>
</div>
<?php admin_end(); ?>
