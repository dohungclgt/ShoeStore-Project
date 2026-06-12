<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$orderId = (int)($_GET['order_id'] ?? 0);
$stmt = db()->prepare('SELECT o.*, u.name customer_name, u.email, u.phone user_phone, u.address user_address
    FROM orders o
    JOIN users u ON u.id=o.user_id
    WHERE o.id=? AND (o.user_id=? OR ? IN (
        SELECT u2.id FROM users u2 JOIN roles r ON r.id=u2.role_id WHERE r.name IN ("Super Admin","Admin","Staff")
    ))');
$stmt->execute([$orderId, $user['id'], $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Không tìm thấy đơn hàng hoặc bạn không có quyền xem hóa đơn này.');
}

$itemsStmt = db()->prepare('SELECT oi.*, p.slug FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id');
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$payStmt = db()->prepare('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1');
$payStmt->execute([$orderId]);
$payment = $payStmt->fetch() ?: [];

$paymentMethod = $order['payment_method'] === 'MOCK' ? 'Thanh toán mô phỏng' : $order['payment_method'];
$paymentStatus = payment_status_label((string)($payment['status'] ?? ($order['payment_method'] === 'COD' ? 'unpaid' : 'pending')));
$reviewUrl = app_url('user/review-order.php?order_id=' . $orderId);
$shoppingUrl = app_url('products.php');
$couponText = $order['coupon_code'] ? e($order['coupon_code']) . ' (-' . money($order['discount']) . ')' : 'Không áp dụng';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('ShoeStore');
$pdf->SetAuthor('ShoeStore');
$pdf->SetTitle('Hóa đơn ' . $order['code']);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

$rows = '';
$index = 1;
foreach ($items as $item) {
    $line = (float)$item['price'] * (int)$item['quantity'];
    $rows .= '<tr>
        <td style="text-align:center">' . $index++ . '</td>
        <td><strong>' . e($item['product_name']) . '</strong></td>
        <td style="text-align:center">' . e($item['size'] ?? '') . '</td>
        <td style="text-align:center">' . (int)$item['quantity'] . '</td>
        <td style="text-align:right">' . money($item['price']) . '</td>
        <td style="text-align:right"><strong>' . money($line) . '</strong></td>
    </tr>';
}

$html = '
<style>
  .muted{color:#64748b}
  .box{border:1px solid #e2e8f0;border-radius:8px;padding:10px}
  .section-title{font-size:13px;font-weight:bold;color:#0f172a;margin-bottom:6px}
  .total-row td{font-size:12px}
</style>
<table cellpadding="0" cellspacing="0" width="100%">
  <tr>
    <td style="background-color:#0f172a;color:#ffffff;padding:16px 18px;width:58%">
      <div style="font-size:24px;font-weight:bold">ShoeStore</div>
      <div style="font-size:11px;color:#d1fae5">Giày chính hãng, thanh toán an toàn, hỗ trợ nhanh</div>
    </td>
    <td style="background-color:#14b8a6;color:#ffffff;padding:16px 18px;text-align:right;width:42%">
      <div style="font-size:20px;font-weight:bold">HÓA ĐƠN MUA HÀNG</div>
      <div>Mã đơn: <strong>' . e($order['code']) . '</strong></div>
      <div>Ngày tạo: ' . e(date('d/m/Y H:i', strtotime($order['created_at']))) . '</div>
    </td>
  </tr>
</table>
<br>
<table cellpadding="8" cellspacing="0" width="100%">
  <tr>
    <td class="box" width="50%">
      <div class="section-title">Thông tin cửa hàng</div>
      <div><strong>ShoeStore</strong></div>
      <div>Website: ' . e(BASE_URL) . '</div>
      <div>Email hỗ trợ: support@shoestore.local</div>
      <div>Hotline: 0900 000 000</div>
    </td>
    <td width="3%"></td>
    <td class="box" width="47%">
      <div class="section-title">Thông tin khách hàng</div>
      <div><strong>' . e($order['customer_name']) . '</strong></div>
      <div>Email: ' . e($order['email']) . '</div>
      <div>Điện thoại: ' . e($order['shipping_phone'] ?: $order['user_phone']) . '</div>
      <div>Địa chỉ giao hàng: ' . e($order['shipping_address']) . '</div>
    </td>
  </tr>
</table>
<br>
<table cellpadding="8" cellspacing="0" width="100%">
  <tr>
    <td class="box" width="50%">
      <div class="section-title">Thanh toán</div>
      <div>Phương thức: <strong>' . e($paymentMethod) . '</strong></div>
      <div>Trạng thái: <strong>' . e($paymentStatus) . '</strong></div>
      <div>Mã giao dịch: ' . e($payment['transaction_id'] ?? 'Không áp dụng') . '</div>
    </td>
    <td width="3%"></td>
    <td class="box" width="47%">
      <div class="section-title">Giao hàng</div>
      <div>Người nhận: <strong>' . e($order['shipping_name']) . '</strong></div>
      <div>Điện thoại: ' . e($order['shipping_phone']) . '</div>
      <div>Ghi chú: ' . e($order['note'] ?: 'Không có') . '</div>
    </td>
  </tr>
</table>
<br>
<table border="1" cellpadding="7" cellspacing="0" width="100%">
  <tr style="background-color:#0f172a;color:#ffffff;font-weight:bold">
    <th width="7%" style="text-align:center">#</th>
    <th width="36%">Sản phẩm</th>
    <th width="10%" style="text-align:center">Size</th>
    <th width="10%" style="text-align:center">SL</th>
    <th width="18%" style="text-align:right">Đơn giá</th>
    <th width="19%" style="text-align:right">Thành tiền</th>
  </tr>
  ' . $rows . '
</table>
<br>
<table cellpadding="6" cellspacing="0" width="100%">
  <tr><td width="62%"></td><td width="20%" class="muted">Tạm tính</td><td width="18%" style="text-align:right">' . money($order['subtotal']) . '</td></tr>
  <tr><td></td><td class="muted">Coupon</td><td style="text-align:right">' . $couponText . '</td></tr>
  <tr><td></td><td class="muted">Phí vận chuyển</td><td style="text-align:right">' . money($order['shipping_fee']) . '</td></tr>
  <tr><td></td><td class="muted">VAT</td><td style="text-align:right">' . money($order['vat']) . '</td></tr>
  <tr style="background-color:#ecfeff"><td></td><td style="font-size:14px;font-weight:bold;color:#0f172a">Tổng thanh toán</td><td style="text-align:right;font-size:14px;font-weight:bold;color:#0f766e">' . money($order['total']) . '</td></tr>
</table>
<br>
<div style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;line-height:1.55">
  <strong>Cảm ơn bạn đã mua sắm tại ShoeStore!</strong><br>
  Sự tin tưởng của bạn là động lực để chúng tôi tiếp tục nâng cao chất lượng dịch vụ.<br>
  Nếu hài lòng với sản phẩm, bạn có thể để lại đánh giá và bình luận để giúp ShoeStore phục vụ tốt hơn.<br>
  Việc đánh giá là hoàn toàn không bắt buộc.
</div>
<br>
<div style="font-size:10px;color:#475569">
  Đánh giá sản phẩm: ' . e($reviewUrl) . '<br>
  Mua sắm thêm: ' . e($shoppingUrl) . '
</div>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('invoice-' . $order['code'] . '.pdf', 'I');
exit;
