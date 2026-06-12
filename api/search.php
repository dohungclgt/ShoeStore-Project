<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$size = trim($_GET['size'] ?? '');
$color = trim($_GET['color'] ?? '');
$min = trim($_GET['min_price'] ?? '');
$max = trim($_GET['max_price'] ?? '');
$inStock = ($_GET['in_stock'] ?? '') === '1';
$sale = ($_GET['sale'] ?? '') === '1';
$where = ['p.status="active"'];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.size_range LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($category !== '') { $where[] = 'c.slug=?'; $params[] = $category; }
if ($brand !== '') { $where[] = 'p.brand LIKE ?'; $params[] = "%$brand%"; }
if ($size !== '') { $where[] = 'p.size_range LIKE ?'; $params[] = "%$size%"; }
if ($color !== '') { $where[] = 'p.description LIKE ?'; $params[] = "%$color%"; }
if ($min !== '' && is_numeric($min)) { $where[] = 'COALESCE(p.sale_price,p.price)>=?'; $params[] = (float)$min; }
if ($max !== '' && is_numeric($max)) { $where[] = 'COALESCE(p.sale_price,p.price)<=?'; $params[] = (float)$max; }
if ($inStock) { $where[] = 'COALESCE(i.stock,0)>0'; }
if ($sale) { $where[] = 'p.sale_price IS NOT NULL AND p.sale_price<p.price'; }
$stmt = db()->prepare('SELECT p.*,c.name category_name,COALESCE(i.stock,0) stock FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id WHERE '.implode(' AND ', $where).' ORDER BY p.featured DESC,p.created_at DESC LIMIT 12');
$stmt->execute($params);
json_response(['items' => $stmt->fetchAll()]);
