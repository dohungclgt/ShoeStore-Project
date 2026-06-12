<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin', 'Admin']);
ensure_support_schema();

$rows = db()->query('SELECT id,link FROM notifications WHERE link IS NOT NULL AND link<>""')->fetchAll();
$updated = 0;
foreach ($rows as $row) {
    $fixed = normalize_notification_link($row['link']);
    if ($fixed !== $row['link']) {
        db()->prepare('UPDATE notifications SET link=? WHERE id=?')->execute([$fixed, (int)$row['id']]);
        $updated++;
    }
}

admin_boot('encoding', 'Sửa notification link');
?>
<div class="table-card">
  <h1 class="section-title">Sửa notification link</h1>
  <p class="mb-0">Đã chuẩn hóa <?= (int)$updated ?> notification link theo cấu hình hiện tại.</p>
  <a class="btn btn-dark mt-3" href="<?= e(app_url('admin/tools/check_urls.php')) ?>">Kiểm tra URL</a>
</div>
<?php admin_end(); ?>
