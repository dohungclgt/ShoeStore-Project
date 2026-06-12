<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$minPrice = trim($_GET['min_price'] ?? '');
$maxPrice = trim($_GET['max_price'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$size = trim($_GET['size'] ?? '');
$params = [];
$where = ['p.status="active"'];
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)'; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; }
if ($category !== '') { $where[] = 'c.slug=?'; $params[]=$category; }
if ($minPrice !== '' && is_numeric($minPrice)) { $where[] = 'COALESCE(p.sale_price,p.price)>=?'; $params[]=(float)$minPrice; }
if ($maxPrice !== '' && is_numeric($maxPrice)) { $where[] = 'COALESCE(p.sale_price,p.price)<=?'; $params[]=(float)$maxPrice; }
if ($brand !== '') { $where[] = 'p.brand LIKE ?'; $params[]="%$brand%"; }
if ($size !== '') { $where[] = 'p.size_range LIKE ?'; $params[]="%$size%"; }
$stmt = db()->prepare('SELECT p.*, c.name category_name, COALESCE(i.stock,0) stock FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id WHERE '.implode(' AND ',$where).' ORDER BY p.created_at DESC');
$stmt->execute($params);
$products = $stmt->fetchAll();
render_header('Sản phẩm');
?>
<main class="container py-5">
  <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4"><div><h1 class="section-title mb-1">Sản phẩm</h1><p class="section-subtitle mb-0">Lọc theo nhu cầu, tồn kho và ưu đãi đang áp dụng.</p></div></div>
  <form class="table-card row g-2 mb-4"><div class="col-md-3"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Tìm giày chạy bộ, lifestyle..."></div><div class="col-md-2"><input class="form-control" name="brand" value="<?= e($brand) ?>" placeholder="Thương hiệu"></div><div class="col-md-2"><input class="form-control" name="min_price" value="<?= e($minPrice) ?>" placeholder="Giá từ"></div><div class="col-md-2"><input class="form-control" name="max_price" value="<?= e($maxPrice) ?>" placeholder="Giá đến"></div><div class="col-md-1"><input class="form-control" name="size" value="<?= e($size) ?>" placeholder="Size"></div><div class="col-md-2"><button class="btn btn-dark w-100">Tìm kiếm</button></div></form>
  <div class="row g-4"><?php foreach ($products as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?></div>
  <?php if (!$products): ?><div class="alert alert-info">Không tìm thấy sản phẩm phù hợp.</div><?php endif; ?>
</main>
<?php render_footer(); ?>
