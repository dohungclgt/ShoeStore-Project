<?php
require_once __DIR__ . '/_admin.php';
admin_boot('dashboard','Dashboard');
$metrics = [
  'Doanh thu hôm nay' => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status IN ('da_thanh_toan','da_giao','hoan_thanh')")->fetchColumn(),
  'Doanh thu tháng' => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) AND status IN ('da_thanh_toan','da_giao','hoan_thanh')")->fetchColumn(),
  'Đơn hàng mới' => db()->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
  'Ticket đang chờ' => db()->query("SELECT COUNT(*) FROM tickets WHERE status!='closed'")->fetchColumn(),
];
$paymentStats = db()->query("SELECT provider,status,COUNT(*) count,COALESCE(SUM(amount),0) amount FROM payments GROUP BY provider,status")->fetchAll();
$chartRows = db()->query("SELECT DATE(created_at) day, COALESCE(SUM(total),0) revenue, COUNT(*) orders FROM orders GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 7")->fetchAll();
$chartRows = array_reverse($chartRows);
?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4"><div><h1 class="section-title mb-1">Dashboard</h1><p class="section-subtitle mb-0">Tổng quan vận hành, doanh thu, đơn hàng và tồn kho.</p></div></div>
<div class="row g-3 mb-4"><?php foreach($metrics as $k=>$v): ?><div class="col-md-3"><div class="metric"><span class="text-muted"><?= e($k) ?></span><h3><?= str_contains($k,'Doanh thu')?money($v):(int)$v ?></h3></div></div><?php endforeach; ?></div>
<div class="row g-3 mb-4">
  <?php foreach($paymentStats as $ps): ?><div class="col-md-3"><div class="metric"><span class="text-muted"><?= e($ps['provider']) ?> · <?= e($ps['status']) ?></span><h3><?= money($ps['amount']) ?></h3><small><?= (int)$ps['count'] ?> giao dịch</small></div></div><?php endforeach; ?>
</div>
<div class="row g-4 mb-4"><div class="col-lg-8"><div class="table-card chart-card"><h2 class="h5">Biểu đồ doanh thu</h2><canvas id="revenueChart"></canvas></div></div><div class="col-lg-4"><div class="table-card"><h2 class="h5">Tồn kho thấp</h2><table class="table"><tr><th>Sản phẩm</th><th>Tồn</th></tr><?php foreach(db()->query('SELECT p.name,i.stock FROM products p JOIN inventory i ON i.product_id=p.id WHERE i.stock<=i.low_stock_threshold ORDER BY i.stock ASC LIMIT 8') as $i): ?><tr><td><?= e($i['name']) ?></td><td><?= (int)$i['stock'] ?></td></tr><?php endforeach; ?></table></div></div></div>
<div class="table-card"><h2 class="h5">Đơn hàng mới</h2><table class="table"><tr><th>Mã</th><th>Khách</th><th>Tổng</th><th>Trạng thái</th></tr><?php foreach(db()->query('SELECT o.*,u.name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 8') as $o): ?><tr><td><?= e($o['code']) ?></td><td><?= e($o['name']) ?></td><td><?= money($o['total']) ?></td><td><?= e(order_status_label($o['status'])) ?></td></tr><?php endforeach; ?></table></div>
<div class="table-card mt-4"><h2 class="h5">Log giao dịch</h2><table class="table"><tr><th>Đơn</th><th>Cổng</th><th>Giao dịch</th><th>Trạng thái</th><th>Thời gian</th></tr><?php foreach(db()->query('SELECT o.code,p.provider,p.transaction_id,p.status,p.updated_at,p.created_at FROM payments p JOIN orders o ON o.id=p.order_id ORDER BY p.updated_at DESC,p.created_at DESC LIMIT 10') as $p): ?><tr><td><?= e($p['code']) ?></td><td><?= e($p['provider']) ?></td><td><?= e($p['transaction_id'] ?? '') ?></td><td><?= e($p['status']) ?></td><td><?= e($p['updated_at'] ?: $p['created_at']) ?></td></tr><?php endforeach; ?></table></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const el=document.getElementById('revenueChart'); if(!el) return; new Chart(el,{type:'line',data:{labels:<?= json_encode(array_column($chartRows,'day')) ?>,datasets:[{label:'Doanh thu',data:<?= json_encode(array_map('floatval',array_column($chartRows,'revenue'))) ?>,borderColor:'#e11d2e',backgroundColor:'rgba(225,29,46,.12)',fill:true,tension:.35},{label:'Đơn hàng',data:<?= json_encode(array_map('intval',array_column($chartRows,'orders'))) ?>,borderColor:'#111827',tension:.35,yAxisID:'y1'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}}}});});
</script>
<?php admin_end(); ?>
