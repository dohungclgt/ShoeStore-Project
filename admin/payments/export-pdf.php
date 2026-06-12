<?php
require_once __DIR__ . '/../_admin.php';
require_once __DIR__ . '/_report.php';

$admin = require_role(['Super Admin','Admin','Staff']);
$filters = payment_report_filters();
$rows = payment_report_rows($filters);
$summary = payment_report_summary($rows);

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('ShoeStore');
$pdf->SetAuthor($admin['name']);
$pdf->SetTitle('Báo cáo thanh toán');
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 9);
$html = '<h1 style="font-size:22px">ShoeStore</h1><h2>Báo cáo thanh toán</h2>';
$html .= '<p><strong>Khoảng thời gian:</strong> '.e($filters['from'] ?: 'Tất cả').' - '.e($filters['to'] ?: 'Tất cả').'<br><strong>Ngày xuất:</strong> '.date('d/m/Y H:i').'<br><strong>Người xuất:</strong> '.e($admin['name']).'</p>';
$html .= '<table border="1" cellpadding="5"><tr style="background-color:#f0f0f0"><th>Tổng doanh thu</th><th>COD</th><th>VNPay</th><th>Coupon</th><th>VAT</th><th>Thực thu</th></tr><tr><td>'.money($summary['revenue']).'</td><td>'.money($summary['cod_revenue']).'</td><td>'.money($summary['vnpay_revenue']).'</td><td>'.money($summary['discount']).'</td><td>'.money($summary['vat']).'</td><td>'.money($summary['net']).'</td></tr></table><br>';
$html .= '<table border="1" cellpadding="4"><thead><tr style="background-color:#111;color:#fff"><th>Mã đơn</th><th>Khách</th><th>PTTT</th><th>TT thanh toán</th><th>TT đơn</th><th>Tổng gốc</th><th>Giảm</th><th>VAT</th><th>Tổng cuối</th><th>Mã GD</th><th>Thời gian</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $html .= '<tr><td>'.e($r['code']).'</td><td>'.e($r['customer']).'</td><td>'.e($r['provider']).'</td><td>'.e(payment_status_label($r['payment_status'] ?? '')).'</td><td>'.e(order_status_label($r['order_status'])).'</td><td>'.money($r['subtotal']).'</td><td>'.money($r['discount']).'</td><td>'.money($r['vat']).'</td><td>'.money($r['total']).'</td><td>'.e($r['transaction_id'] ?? '').'</td><td>'.e($r['paid_at'] ?: $r['created_at']).'</td></tr>';
}
$html .= '</tbody></table><p><strong>Tổng cuối báo cáo:</strong> '.money($summary['revenue']).'</p>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('bao-cao-thanh-toan-'.date('YmdHis').'.pdf', 'D');
exit;
