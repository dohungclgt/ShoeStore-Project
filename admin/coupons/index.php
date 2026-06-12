<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin', 'Admin', 'Staff']);

function coupon_datetime_value(?string $value): string
{
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
}

function coupon_selected(array $ids, int $id): string
{
    return in_array($id, $ids, true) ? 'selected' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $couponId = (int)($_POST['id'] ?? 0);

    db()->beginTransaction();
    try {
        if ($action === 'delete') {
            if ($couponId <= 0) {
                throw new RuntimeException('Coupon không hợp lệ.');
            }
            db()->prepare('UPDATE coupons SET active=0 WHERE id=?')->execute([$couponId]);
            audit_log('hide_coupon', 'coupons', $couponId, []);
            db()->commit();
            flash('success', 'Đã ẩn coupon.');
            header('Location: index.php');
            exit;
        }

        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $type = (string)($_POST['type'] ?? 'percent');
        $value = (float)($_POST['value'] ?? 0);
        $minOrder = (float)($_POST['min_order'] ?? 0);
        $startsAt = $_POST['starts_at'] !== '' ? $_POST['starts_at'] : null;
        $endsAt = $_POST['ends_at'] !== '' ? $_POST['ends_at'] : null;
        $usageLimit = $_POST['usage_limit'] !== '' ? (int)$_POST['usage_limit'] : null;
        $active = isset($_POST['active']) ? 1 : 0;

        if ($code === '') {
            throw new RuntimeException('Vui lòng nhập mã coupon.');
        }
        if (!in_array($type, ['percent', 'fixed', 'free_shipping'], true)) {
            throw new RuntimeException('Loại coupon không hợp lệ.');
        }
        if ($type === 'percent' && ($value <= 0 || $value > 100)) {
            throw new RuntimeException('Coupon phần trăm phải nằm trong khoảng 1 đến 100.');
        }
        if ($type !== 'percent' && $value < 0) {
            throw new RuntimeException('Giá trị coupon không hợp lệ.');
        }

        if ($couponId > 0) {
            db()->prepare('UPDATE coupons SET code=?, type=?, value=?, min_order=?, starts_at=?, ends_at=?, usage_limit=?, active=? WHERE id=?')
                ->execute([$code, $type, $value, $minOrder, $startsAt, $endsAt, $usageLimit, $active, $couponId]);
            db()->prepare('DELETE FROM coupon_products WHERE coupon_id=?')->execute([$couponId]);
            db()->prepare('DELETE FROM coupon_categories WHERE coupon_id=?')->execute([$couponId]);
            audit_log('update_coupon', 'coupons', $couponId, ['code' => $code]);
            $message = 'Đã cập nhật coupon.';
        } else {
            db()->prepare('INSERT INTO coupons(code,type,value,min_order,starts_at,ends_at,usage_limit,active) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$code, $type, $value, $minOrder, $startsAt, $endsAt, $usageLimit, $active]);
            $couponId = (int)db()->lastInsertId();
            audit_log('create_coupon', 'coupons', $couponId, ['code' => $code]);
            $message = 'Đã tạo coupon.';
        }

        $prodStmt = db()->prepare('INSERT INTO coupon_products(coupon_id,product_id) VALUES(?,?)');
        foreach (array_unique(array_map('intval', $_POST['product_ids'] ?? [])) as $pid) {
            if ($pid > 0) {
                $prodStmt->execute([$couponId, $pid]);
            }
        }
        $catStmt = db()->prepare('INSERT INTO coupon_categories(coupon_id,category_id) VALUES(?,?)');
        foreach (array_unique(array_map('intval', $_POST['category_ids'] ?? [])) as $cid) {
            if ($cid > 0) {
                $catStmt->execute([$couponId, $cid]);
            }
        }

        db()->commit();
        flash('success', $message);
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', 'Không lưu được coupon: ' . $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

$products = db()->query('SELECT id,name FROM products ORDER BY name')->fetchAll();
$categories = db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$coupons = db()->query("SELECT c.*,(SELECT COUNT(*) FROM coupon_usage u WHERE u.coupon_id=c.id) used_count,(SELECT GROUP_CONCAT(p.name SEPARATOR ', ') FROM coupon_products cp JOIN products p ON p.id=cp.product_id WHERE cp.coupon_id=c.id) product_names,(SELECT GROUP_CONCAT(cat.name SEPARATOR ', ') FROM coupon_categories cc JOIN categories cat ON cat.id=cc.category_id WHERE cc.coupon_id=c.id) category_names FROM coupons c ORDER BY c.id DESC")->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editCoupon = null;
$editProductIds = [];
$editCategoryIds = [];
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM coupons WHERE id=?');
    $stmt->execute([$editId]);
    $editCoupon = $stmt->fetch() ?: null;
    if ($editCoupon) {
        $stmt = db()->prepare('SELECT product_id FROM coupon_products WHERE coupon_id=?');
        $stmt->execute([$editId]);
        $editProductIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $stmt = db()->prepare('SELECT category_id FROM coupon_categories WHERE coupon_id=?');
        $stmt->execute([$editId]);
        $editCategoryIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

admin_boot('coupons', 'Coupon');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="section-title">Coupon</h1>
  <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#couponModal"><i class="fa-solid fa-plus me-1"></i>Thêm coupon</button>
</div>

<div class="modal fade <?= $editCoupon ? 'show' : '' ?>" id="couponModal" tabindex="-1" <?= $editCoupon ? 'style="display:block;background:rgba(0,0,0,.45)" aria-modal="true" role="dialog"' : '' ?>>
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content" data-confirm-submit="<?= $editCoupon ? 'Xác nhận cập nhật coupon?' : 'Xác nhận tạo coupon?' ?>">
      <div class="modal-header">
        <h5 class="modal-title"><?= $editCoupon ? 'Sửa coupon' : 'Thêm coupon' ?></h5>
        <a class="btn-close" href="index.php" aria-label="Đóng"></a>
      </div>
      <div class="modal-body row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editCoupon['id'] ?? 0) ?>">
        <div class="col-md-4">
          <label>Mã</label>
          <input name="code" class="form-control" required placeholder="CODE" value="<?= e($editCoupon['code'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label>Loại</label>
          <select name="type" class="form-select">
            <?php foreach (['percent' => 'Phần trăm', 'fixed' => 'Tiền cố định', 'free_shipping' => 'Miễn phí vận chuyển'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (($editCoupon['type'] ?? 'percent') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label>Giá trị</label>
          <input name="value" type="number" step="0.01" class="form-control" value="<?= e((string)($editCoupon['value'] ?? '0')) ?>">
        </div>
        <div class="col-md-4">
          <label>Đơn tối thiểu</label>
          <input name="min_order" type="number" step="0.01" class="form-control" value="<?= e((string)($editCoupon['min_order'] ?? '0')) ?>">
        </div>
        <div class="col-md-4">
          <label>Bắt đầu</label>
          <input name="starts_at" type="datetime-local" class="form-control" value="<?= e(coupon_datetime_value($editCoupon['starts_at'] ?? null)) ?>">
        </div>
        <div class="col-md-4">
          <label>Kết thúc</label>
          <input name="ends_at" type="datetime-local" class="form-control" value="<?= e(coupon_datetime_value($editCoupon['ends_at'] ?? null)) ?>">
        </div>
        <div class="col-md-6">
          <label>Áp dụng sản phẩm</label>
          <select name="product_ids[]" class="form-select" multiple size="6">
            <?php foreach ($products as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= coupon_selected($editProductIds, (int)$p['id']) ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label>Áp dụng danh mục</label>
          <select name="category_ids[]" class="form-select" multiple size="6">
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= coupon_selected($editCategoryIds, (int)$c['id']) ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label>Số lượt dùng tối đa</label>
          <input name="usage_limit" type="number" class="form-control" value="<?= e((string)($editCoupon['usage_limit'] ?? '')) ?>">
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <label><input type="checkbox" name="active" <?= (!$editCoupon || (int)$editCoupon['active']) ? 'checked' : '' ?>> Hoạt động</label>
        </div>
      </div>
      <div class="modal-footer">
        <a class="btn btn-outline-secondary" href="index.php">Hủy</a>
        <button class="btn btn-dark">Lưu</button>
      </div>
    </form>
  </div>
</div>

<div class="table-card">
  <table class="table datatable align-middle">
    <thead>
      <tr><th>Code</th><th>Loại</th><th>Giá trị</th><th>Áp dụng</th><th>Lượt dùng</th><th>Thời gian còn lại</th><th>Trạng thái</th><th>Thao tác</th></tr>
    </thead>
    <tbody>
      <?php foreach ($coupons as $c): $expired = $c['ends_at'] && strtotime($c['ends_at']) < time(); $left = $c['ends_at'] ? max(0, strtotime($c['ends_at']) - time()) : null; ?>
        <tr>
          <td><?= e($c['code']) ?></td>
          <td><?= e($c['type']) ?></td>
          <td><?= e($c['value']) ?></td>
          <td><small>SP: <?= e($c['product_names'] ?: 'Tất cả') ?><br>DM: <?= e($c['category_names'] ?: 'Tất cả') ?></small></td>
          <td><?= (int)$c['used_count'] ?><?= $c['usage_limit'] ? '/' . (int)$c['usage_limit'] : '' ?></td>
          <td><?= $left === null ? 'Không giới hạn' : floor($left / 86400) . ' ngày' ?></td>
          <td><?= ((int)$c['active'] && !$expired) ? 'Hoạt động' : 'Tắt/Hết hạn' ?></td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="index.php?edit=<?= (int)$c['id'] ?>"><i class="fa-solid fa-pen"></i></a>
            <form method="post" class="d-inline" data-confirm-submit="Ẩn coupon này?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
