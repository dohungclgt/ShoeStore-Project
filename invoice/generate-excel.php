<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$orderId = (int)($_GET['order_id'] ?? 0);
$stmt = db()->prepare('SELECT o.*, u.name customer_name, u.email, u.phone user_phone
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

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Hoa don');
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

$sheet->mergeCells('A1:F1')->setCellValue('A1', 'ShoeStore');
$sheet->mergeCells('A2:F2')->setCellValue('A2', 'HÓA ĐƠN MUA HÀNG');
$sheet->mergeCells('A3:F3')->setCellValue('A3', 'Mã đơn hàng: ' . $order['code'] . ' | Ngày tạo: ' . date('d/m/Y H:i', strtotime($order['created_at'])));

$sheet->setCellValue('A5', 'Thông tin khách hàng');
$sheet->setCellValue('A6', 'Khách hàng');
$sheet->setCellValue('B6', $order['customer_name']);
$sheet->setCellValue('A7', 'Email');
$sheet->setCellValue('B7', $order['email']);
$sheet->setCellValue('A8', 'Điện thoại');
$sheet->setCellValue('B8', $order['shipping_phone'] ?: $order['user_phone']);
$sheet->setCellValue('A9', 'Địa chỉ giao hàng');
$sheet->setCellValue('B9', $order['shipping_address']);

$sheet->setCellValue('D5', 'Thông tin thanh toán');
$sheet->setCellValue('D6', 'Phương thức');
$sheet->setCellValue('E6', $order['payment_method'] === 'MOCK' ? 'Thanh toán mô phỏng' : $order['payment_method']);
$sheet->setCellValue('D7', 'Trạng thái');
$sheet->setCellValue('E7', payment_status_label((string)($payment['status'] ?? 'pending')));
$sheet->setCellValue('D8', 'Mã giao dịch');
$sheet->setCellValue('E8', $payment['transaction_id'] ?? 'Không áp dụng');
$sheet->setCellValue('D9', 'Coupon');
$sheet->setCellValue('E9', $order['coupon_code'] ?: 'Không áp dụng');

$headerRow = 12;
$sheet->fromArray(['#', 'Sản phẩm', 'Size', 'Số lượng', 'Đơn giá', 'Thành tiền'], null, 'A' . $headerRow);
$row = $headerRow + 1;
$i = 1;
foreach ($items as $item) {
    $line = (float)$item['price'] * (int)$item['quantity'];
    $sheet->fromArray([$i++, $item['product_name'], $item['size'] ?? '', (int)$item['quantity'], (float)$item['price'], $line], null, 'A' . $row);
    $row++;
}

$summaryStart = $row + 1;
$sheet->setCellValue('E' . $summaryStart, 'Tạm tính');
$sheet->setCellValue('F' . $summaryStart, (float)$order['subtotal']);
$sheet->setCellValue('E' . ($summaryStart + 1), 'Coupon giảm giá');
$sheet->setCellValue('F' . ($summaryStart + 1), -(float)$order['discount']);
$sheet->setCellValue('E' . ($summaryStart + 2), 'Phí vận chuyển');
$sheet->setCellValue('F' . ($summaryStart + 2), (float)$order['shipping_fee']);
$sheet->setCellValue('E' . ($summaryStart + 3), 'VAT');
$sheet->setCellValue('F' . ($summaryStart + 3), (float)$order['vat']);
$sheet->setCellValue('E' . ($summaryStart + 4), 'Tổng thanh toán');
$sheet->setCellValue('F' . ($summaryStart + 4), (float)$order['total']);

$thanksRow = $summaryStart + 7;
$sheet->mergeCells('A' . $thanksRow . ':F' . ($thanksRow + 3));
$sheet->setCellValue('A' . $thanksRow, "Cảm ơn bạn đã mua sắm tại ShoeStore!\nSự tin tưởng của bạn là động lực để chúng tôi tiếp tục nâng cao chất lượng dịch vụ.\nNếu hài lòng với sản phẩm, bạn có thể để lại đánh giá và bình luận để giúp ShoeStore phục vụ tốt hơn.\nViệc đánh giá là hoàn toàn không bắt buộc.");
$sheet->setCellValue('A' . ($thanksRow + 5), 'Đánh giá sản phẩm: ' . app_url('user/review-order.php?order_id=' . $orderId));
$sheet->setCellValue('A' . ($thanksRow + 6), 'Mua sắm thêm: ' . app_url('products.php'));

$sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(22)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1:F1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
$sheet->getStyle('A2:F2')->getFont()->setBold(true)->setSize(18)->getColor()->setARGB('FF0F766E');
$sheet->getStyle('A1:F3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A5:B9')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$sheet->getStyle('D5:E9')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$sheet->getStyle('A5')->getFont()->setBold(true);
$sheet->getStyle('D5')->getFont()->setBold(true);
$sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
$sheet->getStyle('A' . $headerRow . ':F' . max($headerRow, $row - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$sheet->getStyle('A' . $headerRow . ':F' . max($headerRow, $row - 1))->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
$sheet->getStyle('D' . ($headerRow + 1) . ':D' . ($row - 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E' . ($headerRow + 1) . ':F' . ($summaryStart + 4))->getNumberFormat()->setFormatCode('#,##0 "VND"');
$sheet->getStyle('E' . ($summaryStart + 4) . ':F' . ($summaryStart + 4))->getFont()->setBold(true)->setSize(13);
$sheet->getStyle('E' . ($summaryStart + 4) . ':F' . ($summaryStart + 4))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0F2FE');
$sheet->getStyle('A' . $thanksRow)->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
$sheet->getStyle('A' . $thanksRow . ':F' . ($thanksRow + 3))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
$sheet->getStyle('A' . $thanksRow . ':F' . ($thanksRow + 3))->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

foreach (['A'=>7,'B'=>34,'C'=>12,'D'=>12,'E'=>18,'F'=>20] as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getRowDimension($thanksRow)->setRowHeight(64);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="invoice-' . $order['code'] . '.xlsx"');
header('Cache-Control: max-age=0');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
exit;
