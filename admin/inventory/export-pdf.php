<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin','Admin','Staff']);
ensure_size_schema();

$categoryId = (int)($_GET['category_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
$stockStatus = (string)($_GET['stock_status'] ?? '');
$where = ["p.status <> 'deleted'"];
$params = [];
if ($categoryId) { $where[] = 'p.category_id=?'; $params[] = $categoryId; }
if ($productId) { $where[] = 'p.id=?'; $params[] = $productId; }
if ($stockStatus === 'out') $where[] = 'ps.stock=0';
if ($stockStatus === 'low') $where[] = 'ps.stock>0 AND ps.stock<=COALESCE(i.low_stock_threshold,5)';
if ($stockStatus === 'available') $where[] = 'ps.stock>COALESCE(i.low_stock_threshold,5)';
$stmt = db()->prepare("SELECT p.name,c.name category,ps.size,ps.stock,COALESCE(i.stock,0) total_stock,COALESCE(i.low_stock_threshold,5) low_stock_threshold FROM product_sizes ps JOIN products p ON p.id=ps.product_id JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id WHERE ".implode(' AND ', $where)." ORDER BY p.name, CAST(ps.size AS UNSIGNED), ps.size");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$metrics = db()->query("SELECT COUNT(*) products, COALESCE(SUM(i.stock),0) total_stock, SUM(CASE WHEN i.stock>0 AND i.stock<=i.low_stock_threshold THEN 1 ELSE 0 END) low_products, SUM(CASE WHEN i.stock=0 THEN 1 ELSE 0 END) out_products FROM inventory i JOIN products p ON p.id=i.product_id WHERE p.status <> 'deleted'")->fetch();

$pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('ShoeStore');
$pdf->SetAuthor('ShoeStore');
$pdf->SetTitle('Báo cáo kho hàng');
$pdf->SetMargins(10, 12, 10);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);
$html = '<h1 style="color:#111827">ShoeStore - Báo cáo kho hàng</h1>';
$html .= '<p>Ngày xuất: '.e(date('d/m/Y H:i')).'<br>Người xuất: '.e(current_user()['email'] ?? 'admin').'</p>';
$html .= '<table border="1" cellpadding="6"><tr style="background-color:#f3f4f6;font-weight:bold"><td>Tổng sản phẩm</td><td>Tổng tồn</td><td>Sắp hết</td><td>Hết hàng</td></tr>';
$html .= '<tr><td>'.(int)$metrics['products'].'</td><td>'.(int)$metrics['total_stock'].'</td><td>'.(int)$metrics['low_products'].'</td><td>'.(int)$metrics['out_products'].'</td></tr></table><br>';
$html .= '<table border="1" cellpadding="5"><tr style="background-color:#111827;color:#fff;font-weight:bold"><td>Sản phẩm</td><td>Danh mục</td><td>Size</td><td>Tồn size</td><td>Tổng tồn</td><td>Ngưỡng</td><td>Trạng thái</td></tr>';
foreach ($rows as $row) {
    $status = (int)$row['stock'] === 0 ? 'Hết hàng' : ((int)$row['stock'] <= (int)$row['low_stock_threshold'] ? 'Sắp hết' : 'Còn hàng');
    $html .= '<tr><td>'.e($row['name']).'</td><td>'.e($row['category']).'</td><td>'.e($row['size']).'</td><td>'.(int)$row['stock'].'</td><td>'.(int)$row['total_stock'].'</td><td>'.(int)$row['low_stock_threshold'].'</td><td>'.e($status).'</td></tr>';
}
$html .= '</table>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('bao-cao-kho-hang-'.date('Ymd-His').'.pdf', 'I');
exit;
