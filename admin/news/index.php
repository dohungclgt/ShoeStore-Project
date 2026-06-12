<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin', 'Admin', 'Staff']);
ensure_news_schema();

function news_slug(string $title): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title)), '-'));
    return $slug ?: 'news-' . time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        db()->prepare("UPDATE news SET status='draft' WHERE id=?")->execute([$id]);
        audit_log('soft_delete_news', 'news', $id);
        flash('success', 'Đã ẩn tin tức.');
        header('Location: index.php'); exit;
    }
    if ($action === 'toggle' && $id) {
        $stmt = db()->prepare('SELECT status FROM news WHERE id=?');
        $stmt->execute([$id]);
        $status = (string)$stmt->fetchColumn();
        $newStatus = $status === 'published' ? 'draft' : 'published';
        db()->prepare('UPDATE news SET status=? WHERE id=?')->execute([$newStatus, $id]);
        audit_log('toggle_news', 'news', $id, ['status' => $newStatus]);
        flash('success', 'Đã cập nhật trạng thái tin tức.');
        header('Location: index.php'); exit;
    }

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        flash('error', 'Tiêu đề không được bỏ trống.');
        header('Location: index.php'); exit;
    }
    $slug = trim((string)($_POST['slug'] ?? '')) ?: news_slug($title);
    $data = [$title, $slug, trim((string)$_POST['thumbnail']), trim((string)$_POST['excerpt']), trim((string)$_POST['content']), trim((string)$_POST['author']), trim((string)$_POST['tags']), $_POST['status'] ?? 'published'];
    if ($id) {
        db()->prepare('UPDATE news SET title=?,slug=?,thumbnail=?,excerpt=?,content=?,author=?,tags=?,status=? WHERE id=?')->execute([...$data, $id]);
        audit_log('update_news', 'news', $id);
    } else {
        db()->prepare('INSERT INTO news(title,slug,thumbnail,excerpt,content,author,tags,status) VALUES(?,?,?,?,?,?,?,?)')->execute($data);
        audit_log('create_news', 'news', (int)db()->lastInsertId());
    }
    flash('success', 'Đã lưu tin tức.');
    header('Location: index.php'); exit;
}

$rows = db()->query('SELECT * FROM news ORDER BY created_at DESC')->fetchAll();
admin_boot('news', 'Tin tức');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="section-title">Tin tức</h1><button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#newsModal">Thêm tin</button></div>
<div class="modal fade" id="newsModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" class="modal-content" data-confirm-submit="Xác nhận lưu tin tức?"><div class="modal-header"><h5 class="modal-title" id="newsModalTitle">Tin tức</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id"><div class="col-md-8"><label>Tiêu đề</label><input name="title" class="form-control" required></div><div class="col-md-4"><label>Slug</label><input name="slug" class="form-control"></div><div class="col-12"><label>Thumbnail</label><input name="thumbnail" class="form-control" required value="https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=900&q=80"></div><div class="col-12"><label>Mô tả ngắn</label><textarea name="excerpt" class="form-control" required></textarea></div><div class="col-12"><label>Nội dung</label><textarea name="content" class="form-control" rows="7" required></textarea></div><div class="col-md-4"><label>Tác giả</label><input name="author" class="form-control" value="ShoeStore Team"></div><div class="col-md-4"><label>Tag</label><input name="tags" class="form-control"></div><div class="col-md-4"><label>Trạng thái</label><select name="status" class="form-select"><option value="published">Hiện</option><option value="draft">Ẩn</option></select></div></div><div class="modal-footer"><button class="btn btn-dark">Lưu</button></div></form></div></div>
<div class="table-card"><table class="table datatable"><thead><tr><th>Tiêu đề</th><th>Tác giả</th><th>Tag</th><th>Trạng thái</th><th>Ngày</th><th>Thao tác</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr data-news='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'><td><?= e($r['title']) ?><br><small><?= e($r['slug']) ?></small></td><td><?= e($r['author']) ?></td><td><?= e($r['tags']) ?></td><td><?= $r['status']==='published'?'Hiện':'Ẩn' ?></td><td><?= e($r['created_at']) ?></td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-dark edit-news">Sửa</button><form method="post" class="d-inline" data-confirm-submit="Cập nhật ẩn/hiện tin này?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $r['status']==='published'?'Ẩn':'Hiện' ?></button></form><form method="post" class="d-inline" data-confirm-submit="Xóa tin này?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const modalEl=document.getElementById('newsModal'); if(!modalEl)return; const modal=new bootstrap.Modal(modalEl); const form=modalEl.querySelector('form'); document.querySelectorAll('.edit-news').forEach(btn=>btn.addEventListener('click',()=>{const d=JSON.parse(btn.closest('tr').dataset.news); document.getElementById('newsModalTitle').textContent='Sửa tin #' + d.id; ['id','title','slug','thumbnail','excerpt','content','author','tags','status'].forEach(k=>{if(form.elements[k]) form.elements[k].value=d[k]||'';}); modal.show();})); modalEl.addEventListener('hidden.bs.modal',()=>{form.reset(); form.elements.id.value=''; document.getElementById('newsModalTitle').textContent='Tin tức';});});
</script>
<?php admin_end(); ?>