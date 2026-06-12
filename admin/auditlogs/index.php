<?php
require_once __DIR__ . '/../_admin.php';
admin_boot('auditlogs','Nhật ký hệ thống');
?>
<h1 class="section-title">Nhật ký hệ thống</h1><div class="table-card"><table class="table datatable"><thead><tr><th>Người dùng</th><th>Hành động</th><th>Đối tượng</th><th>IP</th><th>Ngày</th></tr></thead><tbody><?php foreach(db()->query('SELECT a.*,u.email FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC') as $a): ?><tr><td><?= e($a['email'] ?? 'system') ?></td><td><?= e($a['action']) ?></td><td><?= e($a['entity']) ?> #<?= e((string)$a['entity_id']) ?></td><td><?= e($a['ip_address']) ?></td><td><?= e($a['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_end(); ?>
