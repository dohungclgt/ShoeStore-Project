<?php
require_once __DIR__ . '/../_admin.php';
$user = require_role(['Super Admin','Admin','Staff']);
ensure_size_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int)($_POST['product_id'] ?? 0);
    $size = trim((string)($_POST['size'] ?? ''));
    $type = (string)($_POST['type'] ?? 'import');
    $qty = max(0, (int)($_POST['quantity'] ?? 0));
    $note = trim((string)($_POST['note'] ?? ''));
    if (!$productId || $size === '' || !in_array($type, ['import','export','adjust'], true) || $qty <= 0) {
        flash('error', 'Vui lòng nhập đầy đủ sản phẩm, size, loại giao dịch và số lượng hợp lệ.');
        header('Location: index.php'); exit;
    }
    db()->beginTransaction();
    try {
        $stmt = db()->prepare('SELECT stock FROM product_sizes WHERE product_id=? AND size=? FOR UPDATE');
        $stmt->execute([$productId, $size]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            db()->prepare('INSERT INTO product_sizes(product_id,size,stock) VALUES(?,?,0)')->execute([$productId, $size]);
            $current = 0;
        }
        $current = (int)$current;
        if ($type === 'import') {
            $newStock = $current + $qty;
            $logQty = $qty;
        } elseif ($type === 'export') {
            if ($current < $qty) throw new RuntimeException('Tồn kho size này không đủ để xuất.');
            $newStock = $current - $qty;
            $logQty = $qty;
        } else {
            $newStock = $qty;
            $logQty = abs($newStock - $current);
        }
        db()->prepare('UPDATE product_sizes SET stock=? WHERE product_id=? AND size=?')->execute([$newStock, $productId, $size]);
        sync_product_total_stock($productId);
        db()->prepare('INSERT INTO inventory_logs(product_id,size,user_id,type,quantity,note) VALUES(?,?,?,?,?,?)')->execute([$productId, $size, $_SESSION['user_id'] ?? null, $type, $logQty, $note]);
        audit_log('inventory_'.$type, 'inventory', $productId, ['size'=>$size,'from'=>$current,'to'=>$newStock,'quantity'=>$qty]);
        db()->commit();
        flash('success', 'Đã cập nhật kho.');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', 'Không cập nhật được kho: ' . $e->getMessage());
    }
    header('Location: index.php'); exit;
}

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
$whereSql = implode(' AND ', $where);

$sql = "SELECT p.id product_id,p.name,p.image,c.name category,ps.size,ps.stock,COALESCE(i.stock,0) total_stock,COALESCE(i.low_stock_threshold,5) low_stock_threshold FROM product_sizes ps JOIN products p ON p.id=ps.product_id JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id WHERE $whereSql ORDER BY p.name, CAST(ps.size AS UNSIGNED), ps.size";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$products = db()->query("SELECT p.id,p.name FROM products p WHERE p.status <> 'deleted' ORDER BY p.name")->fetchAll();
$categories = db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$logs = db()->query("SELECT l.*,p.name product_name,u.email FROM inventory_logs l JOIN products p ON p.id=l.product_id LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 80")->fetchAll();
$metrics = db()->query("SELECT COUNT(*) products, COALESCE(SUM(i.stock),0) total_stock, SUM(CASE WHEN i.stock>0 AND i.stock<=i.low_stock_threshold THEN 1 ELSE 0 END) low_products, SUM(CASE WHEN i.stock=0 THEN 1 ELSE 0 END) out_products FROM inventory i JOIN products p ON p.id=i.product_id WHERE p.status <> 'deleted'")->fetch();
$importTotal = (int)db()->query("SELECT COALESCE(SUM(quantity),0) FROM inventory_logs WHERE type='import'")->fetchColumn();
$exportTotal = (int)db()->query("SELECT COALESCE(SUM(quantity),0) FROM inventory_logs WHERE type='export'")->fetchColumn();
$query = $_GET ? ('?' . http_build_query($_GET)) : '';
admin_boot('inventory','Kho hàng');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="section-title">Kho hàng</h1><div class="d-flex gap-2"><a class="btn btn-outline-success" href="<?= e(app_url('admin/inventory/export-excel.php'.$query)) ?>"><i class="fa-solid fa-file-excel me-1"></i>Excel</a><a class="btn btn-outline-danger" href="<?= e(app_url('admin/inventory/export-pdf.php'.$query)) ?>"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a><button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#stockModal"><i class="fa-solid fa-right-left me-1"></i>Nhập/xuất kho</button></div></div>
<div class="row g-3 mb-4">
  <?php foreach([['Tổng sản phẩm',$metrics['products'] ?? 0,'fa-boxes-stacked'],['Tổng tồn kho',$metrics['total_stock'] ?? 0,'fa-warehouse'],['Sắp hết hàng',$metrics['low_products'] ?? 0,'fa-triangle-exclamation'],['Hết hàng',$metrics['out_products'] ?? 0,'fa-circle-xmark'],['Tổng nhập kho',$importTotal,'fa-arrow-down'],['Tổng xuất kho',$exportTotal,'fa-arrow-up']] as $m): ?>
  <div class="col-md-2 col-6"><div class="table-card h-100"><div class="small text-muted"><i class="fa-solid <?= e($m[2]) ?> me-1"></i><?= e($m[0]) ?></div><div class="fs-3 fw-bold"><?= (int)$m[1] ?></div></div></div>
  <?php endforeach; ?>
</div>
<form method="get" class="table-card row g-2 mb-4"><div class="col-md-3"><label>Sản phẩm</label><select name="product_id" class="form-select"><option value="0">Tất cả</option><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $productId===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label>Danh mục</label><select name="category_id" class="form-select"><option value="0">Tất cả</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $categoryId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label>Trạng thái</label><select name="stock_status" class="form-select"><option value="">Tất cả</option><option value="available" <?= $stockStatus==='available'?'selected':'' ?>>Còn hàng</option><option value="low" <?= $stockStatus==='low'?'selected':'' ?>>Sắp hết</option><option value="out" <?= $stockStatus==='out'?'selected':'' ?>>Hết hàng</option></select></div><div class="col-md-1"><label>Tồn từ</label><input name="min_stock" type="number" class="form-control" value="<?= e((string)($_GET['min_stock'] ?? '')) ?>"></div><div class="col-md-1"><label>Đến</label><input name="max_stock" type="number" class="form-control" value="<?= e((string)($_GET['max_stock'] ?? '')) ?>"></div><div class="col-md-2 d-flex align-items-end gap-2"><button class="btn btn-dark flex-fill">Lọc</button><a class="btn btn-outline-secondary" href="index.php">Xóa</a></div></form>
<div class="table-card mb-4"><table class="table datatable align-middle"><thead><tr><th>Ảnh</th><th>Sản phẩm</th><th>Danh mục</th><th>Size</th><th>Tồn size</th><th>Tổng tồn</th><th>Ngưỡng</th><th>Trạng thái</th></tr></thead><tbody><?php foreach($rows as $r): $status=$r['stock']==0?'Hết hàng':($r['stock'] <= $r['low_stock_threshold']?'Sắp hết':'Còn hàng'); $badge=$status==='Hết hàng'?'danger':($status==='Sắp hết'?'warning':'success'); ?><tr><td><img src="<?= e(app_url($r['image'] ?: 'assets/img/review-placeholder.svg')) ?>" style="width:58px;height:58px;object-fit:cover;border-radius:8px" alt=""></td><td><?= e($r['name']) ?></td><td><?= e($r['category']) ?></td><td><strong><?= e($r['size']) ?></strong></td><td><?= (int)$r['stock'] ?></td><td><?= (int)$r['total_stock'] ?></td><td><?= (int)$r['low_stock_threshold'] ?></td><td><span class="badge bg-<?= e($badge) ?>"><?= e($status) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
<div class="table-card"><h2 class="h5 mb-3">Lịch sử kho</h2><table class="table datatable align-middle"><thead><tr><th>Thời gian</th><th>Sản phẩm</th><th>Size</th><th>Loại</th><th>Số lượng</th><th>Người thực hiện</th><th>Ghi chú</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['product_name']) ?></td><td><?= e($l['size'] ?? '') ?></td><td><?= e($l['type']) ?></td><td><?= (int)$l['quantity'] ?></td><td><?= e($l['email'] ?? 'system') ?></td><td><?= e($l['note']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="modal fade" id="stockModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content" data-confirm-submit="Xác nhận cập nhật tồn kho?"><div class="modal-header"><h5 class="modal-title">Nhập/xuất/điều chỉnh kho</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body row g-2"><?= csrf_field() ?><div class="col-12"><label>Sản phẩm</label><select name="product_id" class="form-select" required><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label>Size</label><input name="size" class="form-control" required placeholder="40"></div><div class="col-md-6"><label>Loại giao dịch</label><select name="type" class="form-select"><option value="import">Nhập</option><option value="export">Xuất</option><option value="adjust">Điều chỉnh về số lượng</option></select></div><div class="col-md-6"><label>Số lượng</label><input name="quantity" type="number" min="1" class="form-control" required></div><div class="col-12"><label>Ghi chú</label><textarea name="note" class="form-control"></textarea></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-dark">Lưu</button></div></form></div></div>
<?php admin_end(); ?>