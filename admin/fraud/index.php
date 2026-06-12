<?php
require_once __DIR__ . '/../_admin.php';
admin_boot('fraud','Fraud');
$rows=db()->query("SELECT u.email,COUNT(o.id) orders_count,SUM(o.status='da_huy') canceled_count,SUM(o.total) total_value FROM users u JOIN orders o ON o.user_id=u.id GROUP BY u.id HAVING canceled_count>=2 OR total_value>10000000 ORDER BY canceled_count DESC,total_value DESC")->fetchAll();
?>
<h1 class="section-title">Fraud</h1><div class="table-card"><p class="text-muted">Danh sách cần xem xét dựa trên đơn hủy nhiều hoặc giá trị cao.</p><table class="table datatable"><thead><tr><th>Email</th><th>Số đơn</th><th>Đơn hủy</th><th>Giá trị</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['email']) ?></td><td><?= (int)$r['orders_count'] ?></td><td><?= (int)$r['canceled_count'] ?></td><td><?= money($r['total_value']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_end(); ?>
