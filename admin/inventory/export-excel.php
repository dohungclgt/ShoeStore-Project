<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin','Admin','Staff']);
ensure_size_schema();

$categoryId = (int)($_GET['category_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
$stockStatus = (string)($_GET['stock_status'] ?? '');
$minStock = ($_GET['min_stock'] ?? '') !== '' ? (int)$_GET['min_stock'] : null;
$maxStock = ($_GET['max_stock'] ?? '') !== '' ? (int)$_GET['max_stock'] : null;
$where = ["p.status <> 'deleted'"];
$params = [];
if ($categoryId) { $where[] = 'p.category_id=?'; $params[] = $categoryId; }
if ($productId) { $where[] = 'p.id=?'; $params[] = $productId; }
if ($minStock !== null) { $where[] = 'ps.stock>=?'; $params[] = $minStock; }
if ($maxStock !== null) { $where[] = 'ps.stock<=?'; $params[] = $maxStock; }
if ($stockStatus === 'out') $where[] = 'ps.stock=0';
if ($stockStatus === 'low') $where[] = 'ps.stock>0 AND ps.stock<=COALESCE(i.low_stock_threshold,5)';
if ($stockStatus === 'available') $where[] = 'ps.stock>COALESCE(i.low_stock_threshold,5)';
$stmt = db()->prepare("SELECT p.name,c.name category,ps.size,ps.stock,COALESCE(i.stock,0) total_stock,COALESCE(i.low_stock_threshold,5) low_stock_threshold FROM product_sizes ps JOIN products p ON p.id=ps.product_id JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id WHERE ".implode(' AND ', $where)." ORDER BY p.name, CAST(ps.size AS UNSIGNED), ps.size");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kho hang');
$sheet->mergeCells('A1:H1')->setCellValue('A1', 'ShoeStore - Báo cáo kho hàng');
$sheet->setCellValue('A2', 'Ngày xuất: ' . date('d/m/Y H:i'));
$sheet->setCellValue('A3', 'Người xuất: ' . (current_user()['email'] ?? 'admin'));
$headers = ['Sản phẩm','Danh mục','Size','Tồn size','Tổng tồn','Ngưỡng','Trạng thái','Cảnh báo'];
$sheet->fromArray($headers, null, 'A5');
$r = 6;
foreach ($rows as $row) {
    $status = (int)$row['stock'] === 0 ? 'Hết hàng' : ((int)$row['stock'] <= (int)$row['low_stock_threshold'] ? 'Sắp hết' : 'Còn hàng');
    $sheet->fromArray([$row['name'],$row['category'],$row['size'],(int)$row['stock'],(int)$row['total_stock'],(int)$row['low_stock_threshold'],$status,$status === 'Còn hàng' ? '' : 'Cần kiểm tra'], null, 'A'.$r++);
}
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A5:H5')->getFont()->setBold(true);
$sheet->getStyle('A5:H'.max(5, $r-1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
foreach (range('A','H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="bao-cao-kho-hang-'.date('Ymd-His').'.xlsx"');
header('Cache-Control: max-age=0');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
exit;
