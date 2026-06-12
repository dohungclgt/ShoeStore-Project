<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin','Admin','Staff']);
ensure_size_schema();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save_product';
    if (in_array($action, ['delete_product', 'toggle_product'], true)) {
        $pid = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT id,status FROM products WHERE id=?');
        $stmt->execute([$pid]);
        $product = $stmt->fetch();
        if (!$product) {
            flash('error', 'Không tìm thấy sản phẩm.');
            header('Location: index.php'); exit;
        }
        if ($action === 'delete_product') {
            db()->prepare("UPDATE products SET status='deleted' WHERE id=?")->execute([$pid]);
            audit_log('soft_delete_product','products',$pid);
            flash('success','Đã xóa mềm sản phẩm.');
        } else {
            $newStatus = $product['status'] === 'active' ? 'inactive' : 'active';
            db()->prepare('UPDATE products SET status=? WHERE id=?')->execute([$newStatus, $pid]);
            audit_log('toggle_product','products',$pid,['status'=>$newStatus]);
            flash('success','Đã cập nhật trạng thái sản phẩm.');
        }
        header('Location: index.php'); exit;
    }
    if ($action === 'update_sizes') {
        $pid = (int)$_POST['id'];
        $sizes = parse_size_stock_lines($_POST['sizes'] ?? '');
        db()->beginTransaction();
        try {
            db()->prepare('DELETE FROM product_sizes WHERE product_id=?')->execute([$pid]);
            $stmt = db()->prepare('INSERT INTO product_sizes(product_id,size,stock) VALUES(?,?,?)');
            foreach ($sizes as $size => $stock) $stmt->execute([$pid,$size,$stock]);
            sync_product_total_stock($pid);
            audit_log('update_product_sizes','products',$pid,['sizes'=>$sizes]);
            db()->commit();
            flash('success','Đã cập nhật size và tồn kho.');
        } catch(Throwable $e) {
            if(db()->inTransaction()) db()->rollBack();
            flash('error','Không cập nhật được size: '.$e->getMessage());
        }
        header('Location: index.php'); exit;
    }

    $image=isset($_FILES['image'])?upload_file($_FILES['image'],'uploads/products',['image/jpeg','image/png','image/webp']):null;
    $slug=trim((string)($_POST['slug'] ?? ''));
    if ($slug === '') $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$_POST['name'])),'-'));
    $sizes = parse_size_stock_lines($_POST['sizes'] ?? '');
    if (!$sizes) $sizes = ['38'=>5,'39'=>5,'40'=>5,'41'=>5,'42'=>5,'43'=>5];
    db()->beginTransaction();
    try {
        if(!empty($_POST['id'])){
            $pid=(int)$_POST['id'];
            db()->prepare('UPDATE products SET category_id=?,name=?,slug=?,description=?,price=?,sale_price=?,brand=?,status=?,image=COALESCE(?,image),featured=?,best_seller=?,size_range=? WHERE id=?')->execute([$_POST['category_id'],$_POST['name'],$slug,$_POST['description'],$_POST['price'],$_POST['sale_price']?:null,$_POST['brand'],$_POST['status'],$image,isset($_POST['featured'])?1:0,isset($_POST['best_seller'])?1:0,implode(',', array_keys($sizes)),$pid]);
            audit_log('update_product','products',$pid,['slug'=>$slug]);
        } else {
            db()->prepare('INSERT INTO products(category_id,name,slug,description,price,sale_price,brand,status,image,featured,best_seller,size_range) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$_POST['category_id'],$_POST['name'],$slug,$_POST['description'],$_POST['price'],$_POST['sale_price']?:null,$_POST['brand'],$_POST['status'],$image ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80',isset($_POST['featured'])?1:0,isset($_POST['best_seller'])?1:0,implode(',', array_keys($sizes))]);
            $pid=(int)db()->lastInsertId();
            audit_log('create_product','products',$pid,['slug'=>$slug]);
        }
        db()->prepare('DELETE FROM product_sizes WHERE product_id=?')->execute([$pid]);
        $stmt = db()->prepare('INSERT INTO product_sizes(product_id,size,stock) VALUES(?,?,?)');
        foreach ($sizes as $size => $stock) $stmt->execute([$pid,$size,$stock]);
        sync_product_total_stock($pid);
        db()->commit();
        flash('success','Đã lưu sản phẩm.');
    } catch(Throwable $e) {
        if(db()->inTransaction()) db()->rollBack();
        flash('error','Không lưu được sản phẩm: '.$e->getMessage());
    }
    header('Location: index.php'); exit;
}

$cats=db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$rows=db()->query('SELECT p.*,c.name cat,COALESCE(i.stock,0) stock FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id ORDER BY p.id DESC')->fetchAll();
$sizesByProduct = [];
$sizeRows = db()->query('SELECT * FROM product_sizes ORDER BY CAST(size AS UNSIGNED), size')->fetchAll();
foreach($sizeRows as $s) $sizesByProduct[(int)$s['product_id']][] = $s;
admin_boot('products','Quản lý sản phẩm');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="section-title">Sản phẩm</h1><button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#productModal"><i class="fa-solid fa-plus me-1"></i>Thêm sản phẩm</button></div>
<div class="modal fade" id="productModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" enctype="multipart/form-data" class="modal-content" data-confirm-submit="Xác nhận lưu sản phẩm này?"><div class="modal-header"><h5 class="modal-title" id="productModalTitle">Thêm sản phẩm</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id"><div class="col-md-4"><label>Tên</label><input name="name" class="form-control" required placeholder="Tên sản phẩm"></div><div class="col-md-4"><label>Slug</label><input name="slug" class="form-control" placeholder="tu-dong-neu-de-trong"></div><div class="col-md-4"><label>Danh mục</label><select name="category_id" class="form-select"><?php foreach($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label>Giá</label><input name="price" type="number" class="form-control" required></div><div class="col-md-3"><label>Sale</label><input name="sale_price" type="number" class="form-control"></div><div class="col-md-3"><label>Thương hiệu</label><input name="brand" class="form-control"></div><div class="col-md-3"><label>Trạng thái</label><select name="status" class="form-select"><option value="active">Đang bán</option><option value="inactive">Tạm ẩn</option><option value="deleted">Đã xóa</option></select></div><div class="col-md-6"><label>Ảnh</label><input type="file" name="image" class="form-control"><div class="small text-muted current-image"></div></div><div class="col-12"><label>Mô tả</label><textarea name="description" class="form-control"></textarea></div><div class="col-12"><label>Size và tồn kho từng size</label><textarea name="sizes" class="form-control" rows="4">38: 5&#10;39: 5&#10;40: 5&#10;41: 5&#10;42: 5&#10;43: 5</textarea><small class="text-muted">Mỗi dòng một size theo dạng: 40: 12</small></div><div class="col-12"><label class="me-3"><input type="checkbox" name="featured"> Nổi bật</label><label><input type="checkbox" name="best_seller"> Bán chạy</label></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-dark">Lưu</button></div></form></div></div>
<div class="table-card"><table class="table datatable align-middle"><thead><tr><th>ID</th><th>Tên</th><th>Danh mục</th><th>Giá</th><th>Tồn tổng</th><th>Size tồn kho</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach($rows as $r): $sizesText=''; foreach($sizesByProduct[(int)$r['id']] ?? [] as $s){ $sizesText .= $s['size'].': '.(int)$s['stock']."\n"; } ?><tr data-product='<?= e(json_encode(["id"=>(int)$r["id"],"name"=>$r["name"],"slug"=>$r["slug"],"category_id"=>(int)$r["category_id"],"description"=>$r["description"],"price"=>(float)$r["price"],"sale_price"=>$r["sale_price"],"brand"=>$r["brand"],"status"=>$r["status"],"image"=>$r["image"],"featured"=>(int)$r["featured"],"best_seller"=>(int)$r["best_seller"],"sizes"=>trim($sizesText)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'><td><?= (int)$r['id'] ?></td><td><?= e($r['name']) ?><br><small><?= e($r['slug']) ?></small></td><td><?= e($r['cat']) ?></td><td><?= money($r['sale_price'] ?: $r['price']) ?></td><td><?= (int)$r['stock'] ?></td><td><form method="post" class="d-flex gap-2 align-items-start" data-confirm-submit="Cập nhật tồn kho size?"><?= csrf_field() ?><input type="hidden" name="action" value="update_sizes"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><textarea name="sizes" class="form-control form-control-sm" rows="3" style="min-width:150px"><?= e($sizesText) ?></textarea><button class="btn btn-sm btn-dark">Lưu size</button></form></td><td><?= e($r['status']==='active'?'Đang bán':($r['status']==='deleted'?'Đã xóa':'Tạm ẩn')) ?></td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-dark edit-product">Sửa</button><form method="post" class="d-inline" data-confirm-submit="Bạn có chắc chắn muốn xóa sản phẩm này không?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form><form method="post" class="d-inline" data-confirm-submit="Cập nhật ẩn/hiện sản phẩm?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $r['status']==='active'?'Ẩn':'Hiện' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const modalEl=document.getElementById('productModal'); if(!modalEl)return; const modal=new bootstrap.Modal(modalEl); const form=modalEl.querySelector('form'); document.querySelectorAll('.edit-product').forEach(btn=>btn.addEventListener('click',()=>{const data=JSON.parse(btn.closest('tr').dataset.product); document.getElementById('productModalTitle').textContent='Sửa sản phẩm #' + data.id; form.elements.id.value=data.id; form.elements.name.value=data.name||''; form.elements.slug.value=data.slug||''; form.elements.category_id.value=data.category_id; form.elements.price.value=data.price||0; form.elements.sale_price.value=data.sale_price||''; form.elements.brand.value=data.brand||''; form.elements.status.value=data.status||'active'; form.elements.description.value=data.description||''; form.elements.sizes.value=data.sizes||''; form.elements.featured.checked=Number(data.featured)===1; form.elements.best_seller.checked=Number(data.best_seller)===1; modalEl.querySelector('.current-image').textContent=data.image ? 'Ảnh hiện tại: '+data.image : ''; modal.show();})); modalEl.addEventListener('hidden.bs.modal',()=>{form.reset(); form.elements.id.value=''; document.getElementById('productModalTitle').textContent='Thêm sản phẩm'; modalEl.querySelector('.current-image').textContent='';});});
</script>
<?php admin_end(); ?>
