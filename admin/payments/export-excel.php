<?php
require_once __DIR__ . '/../_admin.php';
require_once __DIR__ . '/_report.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$admin = require_role(['Super Admin','Admin','Staff']);
$filters = payment_report_filters();
$rows = payment_report_rows($filters);
$summary = payment_report_summary($rows);

$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Bao cao thanh toan');
$sheet->setCellValue('A1', 'ShoeStore');
$sheet->setCellValue('A2', 'Báo cáo thanh toán');
$sheet->setCellValue('A3', 'Khoảng thời gian: ' . ($filters['from'] ?: 'Tất cả') . ' - ' . ($filters['to'] ?: 'Tất cả'));
$sheet->setCellValue('A4', 'Người xuất: ' . $admin['name'] . ' · Ngày xuất: ' . date('d/m/Y H:i'));
$sheet->fromArray(['Tổng doanh thu', $summary['revenue'], 'Doanh thu COD', $summary['cod_revenue'], 'Doanh thu VNPay', $summary['vnpay_revenue']], null, 'A6');
$sheet->fromArray(['Giảm giá coupon', $summary['discount'], 'VAT', $summary['vat'], 'Thực thu', $summary['net']], null, 'A7');
$header = ['Mã đơn','Khách hàng','Email','Phương thức','TT thanh toán','TT đơn hàng','Tổng gốc','Giảm giá','VAT','Phí ship','Tổng cuối','Mã VNPay','Thời gian','Ghi chú'];
$sheet->fromArray($header, null, 'A9');
$rowNum = 10;
foreach ($rows as $r) {
    $sheet->fromArray([$r['code'],$r['customer'],$r['email'],$r['provider'],payment_status_label($r['payment_status'] ?? ''),order_status_label($r['order_status']),(float)$r['subtotal'],(float)$r['discount'],(float)$r['vat'],(float)$r['shipping_fee'],(float)$r['total'],$r['transaction_id'],$r['paid_at'] ?: $r['created_at'],$r['coupon_code'] ? 'Coupon: '.$r['coupon_code'] : ''], null, 'A'.$rowNum++);
}
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15);
$sheet->getStyle('A9:N9')->getFont()->setBold(true);
$sheet->getStyle('A9:N9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');
foreach (range('A','N') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="bao-cao-thanh-toan-'.date('YmdHis').'.xlsx"');
(new Xlsx($ss))->save('php://output');
exit;
