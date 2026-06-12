<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin','Admin','Staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        db()->prepare('UPDATE popups SET active=0 WHERE id=?')->execute([$id]);
        audit_log('delete_popup', 'popups', $id);
        flash('success', 'Đã tắt popup.');
        header('Location: index.php'); exit;
    }
    if ($action === 'toggle' && $id) {
        $stmt = db()->prepare('SELECT active FROM popups WHERE id=?');
        $stmt->execute([$id]);
        $active = (int)$stmt->fetchColumn();
        db()->prepare('UPDATE popups SET active=? WHERE id=?')->execute([$active ? 0 : 1, $id]);
        audit_log('toggle_popup', 'popups', $id, ['active' => $active ? 0 : 1]);
        flash('success', 'Đã cập nhật trạng thái popup.');
        header('Location: index.php'); exit;
    }

    $img = isset($_FILES['image']) ? upload_file($_FILES['image'], 'uploads/products', ['image/jpeg','image/png','image/webp']) : null;
    $image = $img ?: trim((string)($_POST['image_url'] ?? ''));
    $data = [
        $_POST['type'] ?? 'promotion',
        trim((string)$_POST['title']),
        trim((string)$_POST['content']),
        $image ?: null,
        trim((string)$_POST['cta_text']),
        trim((string)$_POST['cta_link']),
        isset($_POST['active']) ? 1 : 0,
        ($_POST['starts_at'] ?? '') ?: null,
        ($_POST['ends_at'] ?? '') ?: null,
    ];
    if ($id) {
        if (!$img && $image === '') {
            $old = db()->prepare('SELECT image FROM popups WHERE id=?');
            $old->execute([$id]);
            $data[3] = $old->fetchColumn() ?: null;
        }
        db()->prepare('UPDATE popups SET type=?,title=?,content=?,image=?,cta_text=?,cta_link=?,active=?,starts_at=?,ends_at=? WHERE id=?')->execute([...$data, $id]);
        audit_log('update_popup', 'popups', $id);
    } else {
        db()->prepare('INSERT INTO popups(type,title,content,image,cta_text,cta_link,active,starts_at,ends_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);
        audit_log('create_popup', 'popups', (int)db()->lastInsertId());
    }
    flash('success', 'Đã lưu popup.');
    header('Location: index.php'); exit;
}

$rows = db()->query("SELECT p.*,(SELECT COUNT(*) FROM popup_logs l WHERE l.popup_id=p.id AND event='impression') impressions,(SELECT COUNT(*) FROM popup_logs l WHERE l.popup_id=p.id AND event='click') clicks FROM popups p ORDER BY p.id DESC")->fetchAll();
admin_boot('popupads','Popup quảng cáo');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="section-title">Popup quảng cáo</h1><button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#popupModal"><i class="fa-solid fa-plus me-1"></i>Thêm popup</button></div>
<div class="modal fade" id="popupModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" enctype="multipart/form-data" class="modal-content" data-confirm-submit="Xác nhận lưu popup quảng cáo?"><div class="modal-header"><h5 class="modal-title" id="popupModalTitle">Popup</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id"><div class="col-md-3"><label>Loại</label><select name="type" class="form-select"><option value="flash_sale">Flash Sale</option><option value="banner">Banner</option><option value="promotion">Khuyến mãi</option><option value="announcement">Thông báo</option></select></div><div class="col-md-5"><label>Tiêu đề</label><input name="title" class="form-control" required></div><div class="col-md-4"><label>CTA</label><input name="cta_text" class="form-control"></div><div class="col-md-8"><label>Nội dung</label><input name="content" class="form-control"></div><div class="col-md-4"><label>Liên kết</label><input name="cta_link" class="form-control"></div><div class="col-md-6"><label>Image URL</label><input name="image_url" class="form-control"></div><div class="col-md-4"><label>Ảnh tải lên</label><input type="file" name="image" class="form-control"><div class="small text-muted current-image"></div></div><div class="col-md-2 d-flex align-items-end"><label><input type="checkbox" name="active" checked> Hoạt động</label></div><div class="col-md-6"><label>Bắt đầu</label><input type="datetime-local" name="starts_at" class="form-control"></div><div class="col-md-6"><label>Kết thúc</label><input type="datetime-local" name="ends_at" class="form-control"></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-dark">Lưu</button></div></form></div></div>
<div class="table-card"><table class="table datatable align-middle"><thead><tr><th>Tiêu đề</th><th>Loại</th><th>Thời gian</th><th>Trạng thái</th><th>Impression</th><th>Click</th><th>Thao tác</th></tr></thead><tbody><?php foreach($rows as $p): ?><tr data-popup='<?= e(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'><td><?= e($p['title']) ?><br><small><?= e($p['cta_link']) ?></small></td><td><?= e($p['type']) ?></td><td><small><?= e($p['starts_at'] ?? '') ?><br><?= e($p['ends_at'] ?? '') ?></small></td><td><?= (int)$p['active'] ? 'Bật' : 'Tắt' ?></td><td><?= (int)$p['impressions'] ?></td><td><?= (int)$p['clicks'] ?></td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-dark edit-popup">Sửa</button><form method="post" class="d-inline" data-confirm-submit="Bật/tắt popup này?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= (int)$p['active'] ? 'Tắt' : 'Bật' ?></button></form><form method="post" class="d-inline" data-confirm-submit="Xóa popup này?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const modalEl=document.getElementById('popupModal'); if(!modalEl)return; const modal=new bootstrap.Modal(modalEl); const form=modalEl.querySelector('form'); const toLocal=v=>v?String(v).replace(' ','T').slice(0,16):''; document.querySelectorAll('.edit-popup').forEach(btn=>btn.addEventListener('click',()=>{const d=JSON.parse(btn.closest('tr').dataset.popup); document.getElementById('popupModalTitle').textContent='Sửa popup #' + d.id; form.elements.id.value=d.id; form.elements.type.value=d.type||'promotion'; form.elements.title.value=d.title||''; form.elements.content.value=d.content||''; form.elements.cta_text.value=d.cta_text||''; form.elements.cta_link.value=d.cta_link||''; form.elements.image_url.value=d.image||''; form.elements.active.checked=Number(d.active)===1; form.elements.starts_at.value=toLocal(d.starts_at); form.elements.ends_at.value=toLocal(d.ends_at); modalEl.querySelector('.current-image').textContent=d.image ? 'Ảnh hiện tại: '+d.image : ''; modal.show();})); modalEl.addEventListener('hidden.bs.modal',()=>{form.reset(); form.elements.id.value=''; document.getElementById('popupModalTitle').textContent='Popup'; modalEl.querySelector('.current-image').textContent='';});});
</script>
<?php admin_end(); ?>