<?php
require_once __DIR__ . '/../_admin.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_role(['Super Admin', 'Admin', 'Staff']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $stmt = db()->prepare('SELECT o.*,u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?');
    $stmt->execute([$_POST['id']]);
    $order = $stmt->fetch();
    db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$_POST['status'],$_POST['id']]);
    if ($order) {
        $order['status'] = $_POST['status'];
        create_notification((int)$order['user_id'], 'Cập nhật đơn hàng', 'Đơn hàng '.$order['code'].' đã chuyển sang '.order_status_label($_POST['status']).'.');
        $message = in_array($_POST['status'], ['delivered','completed','da_giao','hoan_thanh'], true)
            ? 'Cảm ơn bạn đã mua sắm tại ShoeStore. Đơn hàng của bạn đã được giao thành công. Nếu bạn hài lòng với sản phẩm, hãy để lại đánh giá để giúp chúng tôi phục vụ tốt hơn.'
            : 'Đơn hàng của bạn đã chuyển sang ' . order_status_label($_POST['status']) . '.';
        send_mail($order['email'], 'Cập nhật đơn hàng '.$order['code'], render_email_template('order-status-updated', order_email_data($order, 'Cập nhật trạng thái đơn hàng', $message)), 'Đơn hàng '.$order['code'].' đã chuyển sang '.order_status_label($_POST['status']).'.');
        audit_log('update_order_status','orders',(int)$_POST['id'],['status'=>$_POST['status']]);
    }
    flash('success','Đã cập nhật đơn hàng.');
    header('Location: index.php');
    exit;
}
$rows=db()->query('SELECT o.*,u.name,u.email FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC')->fetchAll();
$itemsByOrder = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($ph) ORDER BY id");
    $stmt->execute($ids);
    foreach ($stmt as $item) $itemsByOrder[(int)$item['order_id']][] = $item;
}
admin_boot('orders','Quản lý đơn hàng');
?>
<h1 class="section-title">Đơn hàng</h1><div class="table-card"><table class="table datatable"><thead><tr><th>Mã</th><th>Khách</th><th>Sản phẩm/Size</th><th>Tổng</th><th>PTTT</th><th>Trạng thái</th><th>Lưu</th></tr></thead><tbody><?php foreach($rows as $o): $formId='order-status-'.(int)$o['id']; ?><tr><td><form id="<?= e($formId) ?>" method="post" data-confirm-submit="Xác nhận cập nhật trạng thái đơn hàng <?= e($o['code']) ?>?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"></form><?= e($o['code']) ?></td><td><?= e($o['name']) ?><br><small><?= e($o['email']) ?></small></td><td><?php foreach($itemsByOrder[(int)$o['id']] ?? [] as $it): ?><div><?= e($it['product_name']) ?> · Size <?= e($it['size'] ?? '') ?> x <?= (int)$it['quantity'] ?></div><?php endforeach; ?></td><td><?= money($o['total']) ?></td><td><?= e($o['payment_method']) ?></td><td><select form="<?= e($formId) ?>" name="status" class="form-select form-select-sm"><?= order_status_options($o['status']) ?></select></td><td><button form="<?= e($formId) ?>" class="btn btn-sm btn-dark">Lưu</button></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_end(); ?>
