<?php
require_once __DIR__ . '/../_admin.php';
require_once __DIR__ . '/_report.php';
$admin = admin_boot('payments', 'Thống kê thanh toán');
$filters = payment_report_filters();
$rows = payment_report_rows($filters);
$summary = payment_report_summary($rows);
$query = http_build_query(array_filter($filters, fn($v) => $v !== ''));
?>
<h1 class="section-title">Thống kê thanh toán</h1>
<form class="table-card row g-2 mb-3">
  <div class="col-md-2"><label>Từ ngày</label><input type="date" name="from" class="form-control" value="<?= e($filters['from']) ?>"></div>
  <div class="col-md-2"><label>Đến ngày</label><input type="date" name="to" class="form-control" value="<?= e($filters['to']) ?>"></div>
  <div class="col-md-2"><label>Phương thức</label><select name="provider" class="form-select"><option value="">Tất cả</option><option value="COD" <?= $filters['provider']==='COD'?'selected':'' ?>>COD</option><option value="VNPAY" <?= $filters['provider']==='VNPAY'?'selected':'' ?>>VNPay</option></select></div>
  <div class="col-md-2"><label>Thanh toán</label><select name="payment_status" class="form-select"><option value="">Tất cả</option><?php foreach(['pending','paid','failed','unpaid','refunded'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['payment_status']===$s?'selected':'' ?>><?= e(payment_status_label($s)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label>Đơn hàng</label><select name="order_status" class="form-select"><option value="">Tất cả</option><?php foreach(['pending_payment','waiting_confirm','waiting_pickup','packing','shipping','delivered','completed','cancelled','returned'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['order_status']===$s?'selected':'' ?>><?= e(order_status_label($s)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2 d-flex align-items-end gap-2"><button class="btn btn-dark w-100">Lọc</button></div>
  <div class="col-12 d-flex gap-2"><a class="btn btn-outline-success" href="export-excel.php<?= $query ? '?'.$query : '' ?>">Xuất Excel</a><a class="btn btn-outline-danger" href="export-pdf.php<?= $query ? '?'.$query : '' ?>">Xuất PDF</a></div>
</form>
<div class="row g-3 mb-3">
  <?php foreach([
    'Tổng doanh thu'=>$summary['revenue'],'Doanh thu COD'=>$summary['cod_revenue'],'Doanh thu VNPay'=>$summary['vnpay_revenue'],'Giảm giá coupon'=>$summary['discount'],'VAT'=>$summary['vat'],'Thực thu'=>$summary['net']
  ] as $label=>$value): ?><div class="col-md-4 col-xl-2"><div class="table-card h-100"><small class="text-muted"><?= e($label) ?></small><div class="h5 mb-0"><?= money($value) ?></div></div></div><?php endforeach; ?>
  <div class="col-md-4 col-xl-2"><div class="table-card"><small class="text-muted">Đơn COD</small><div class="h5 mb-0"><?= (int)$summary['cod_orders'] ?></div></div></div>
  <div class="col-md-4 col-xl-2"><div class="table-card"><small class="text-muted">VNPay thành công</small><div class="h5 mb-0"><?= (int)$summary['vnpay_paid'] ?></div></div></div>
  <div class="col-md-4 col-xl-2"><div class="table-card"><small class="text-muted">VNPay thất bại</small><div class="h5 mb-0"><?= (int)$summary['vnpay_failed'] ?></div></div></div>
  <div class="col-md-4 col-xl-2"><div class="table-card"><small class="text-muted">Đang chờ</small><div class="h5 mb-0"><?= (int)$summary['pending'] ?></div></div></div>
</div>
<div class="table-card">
  <table class="table datatable align-middle">
    <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>PTTT</th><th>TT thanh toán</th><th>TT đơn</th><th>Tổng gốc</th><th>Giảm</th><th>VAT</th><th>Tổng cuối</th><th>Mã GD</th><th>Thời gian</th><th>Ghi chú</th></tr></thead>
    <tbody><?php foreach($rows as $r): ?><tr>
      <td><?= e($r['code']) ?></td><td><?= e($r['customer']) ?><br><small><?= e($r['email']) ?></small></td><td><?= e($r['provider']) ?></td><td><?= e(payment_status_label($r['payment_status'] ?? '')) ?></td><td><?= e(order_status_label($r['order_status'])) ?></td>
      <td><?= money($r['subtotal']) ?></td><td><?= money($r['discount']) ?><?= $r['coupon_code'] ? '<br><small>'.e($r['coupon_code']).'</small>' : '' ?></td><td><?= money($r['vat']) ?></td><td><?= money($r['total']) ?></td><td><?= e($r['transaction_id'] ?? '') ?></td><td><?= e($r['paid_at'] ?: $r['created_at']) ?></td><td><?= e($r['payment_attempts'] ? 'Lần thanh toán: '.$r['payment_attempts'] : '') ?></td>
    </tr><?php endforeach; ?></tbody>
  </table>
</div>
<?php admin_end(); ?>
