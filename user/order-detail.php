<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/order-ui.php';

$user = require_login();
ensure_payment_schema();
ensure_order_action_schema();

$orderId = (int)($_GET['order_id'] ?? 0);
$stmt = db()->prepare("SELECT o.*,p.status payment_status,p.provider payment_provider,p.transaction_id FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=? LIMIT 1");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    flash('error', 'Không tìm thấy đơn hàng.');
    header('Location: orders.php');
    exit;
}

$itemsStmt = db()->prepare('SELECT oi.*,p.image,p.slug FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id');
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();
$actions = order_action_flags($order);

render_header('Chi tiết đơn hàng');
?>
<main class="container py-5 order-page" id="orderDetailPage" data-detail-url="<?= e(app_url('api/orders/detail.php?order_id=' . (int)$order['id'])) ?>">
  <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
    <div>
      <a class="text-muted text-decoration-none" href="<?= e(app_url('user/orders.php')) ?>"><i class="fa-solid fa-arrow-left me-1"></i> Đơn hàng của tôi</a>
      <h1 class="section-title mt-2 mb-1">Chi tiết đơn hàng <?= e($order['code']) ?></h1>
      <div class="text-muted">Đặt lúc <?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-start">
      <span class="order-status-badge <?= e(order_status_badge_class($order['status'])) ?>" data-role="status-badge"><?= e(order_status_label($order['status'])) ?></span>
      <a class="btn btn-outline-secondary" href="<?= e(app_url('invoice/generate-pdf.php?order_id=' . (int)$order['id'])) ?>"><i class="fa-solid fa-file-pdf me-1"></i> PDF</a>
      <a class="btn btn-outline-secondary" href="<?= e(app_url('invoice/generate-excel.php?order_id=' . (int)$order['id'])) ?>"><i class="fa-solid fa-file-excel me-1"></i> Excel</a>
    </div>
  </div>

  <div class="order-timeline mb-4" data-role="timeline">
    <?php foreach (order_timeline_steps($order['status'], $order['payment_status'] ?? null) as $step): ?>
      <div class="order-step <?= !empty($step['done']) ? 'done' : '' ?> <?= !empty($step['danger']) ? 'danger' : '' ?>">
        <span></span><strong><?= e($step['label']) ?></strong>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <section class="table-card mb-4">
        <h2 class="h5 mb-3">Sản phẩm</h2>
        <div class="order-item-list">
          <?php foreach ($items as $item): ?>
            <div class="order-detail-item">
              <img src="<?= e($item['image'] ?: app_url('assets/img/review-placeholder.svg')) ?>" alt="<?= e($item['product_name']) ?>">
              <div class="flex-grow-1">
                <a class="fw-bold text-decoration-none" href="<?= e($item['slug'] ? app_url('product.php?slug=' . rawurlencode($item['slug'])) : '#') ?>"><?= e($item['product_name']) ?></a>
                <div class="text-muted small">Size <?= e($item['size'] ?? '-') ?> · Số lượng <?= (int)$item['quantity'] ?></div>
              </div>
              <div class="text-end">
                <div><?= money($item['price']) ?></div>
                <strong><?= money((float)$item['price'] * (int)$item['quantity']) ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="table-card">
        <h2 class="h5 mb-3">Thông tin giao hàng</h2>
        <div class="row g-3">
          <div class="col-md-6"><span class="text-muted d-block">Người nhận</span><strong><?= e($order['shipping_name']) ?></strong></div>
          <div class="col-md-6"><span class="text-muted d-block">Số điện thoại</span><strong><?= e($order['shipping_phone']) ?></strong></div>
          <div class="col-12"><span class="text-muted d-block">Địa chỉ</span><strong><?= e($order['shipping_address']) ?></strong></div>
          <?php if (!empty($order['note'])): ?><div class="col-12"><span class="text-muted d-block">Ghi chú</span><?= e($order['note']) ?></div><?php endif; ?>
        </div>
      </section>
    </div>

    <div class="col-lg-4">
      <section class="table-card mb-4">
        <h2 class="h5 mb-3">Thanh toán</h2>
        <div class="d-flex align-items-center gap-2 mb-3"><i class="fa-solid <?= e(order_payment_icon($order['payment_method'])) ?>"></i><strong><?= e($order['payment_method']) ?></strong><span class="text-muted">· <span data-role="payment-status"><?= e(payment_status_label($order['payment_status'] ?? '')) ?></span></span></div>
        <?php if (!empty($order['transaction_id'])): ?><div class="small text-muted mb-3">Mã giao dịch: <?= e($order['transaction_id']) ?></div><?php endif; ?>
        <div class="order-total-row"><span>Tạm tính</span><strong><?= money($order['subtotal']) ?></strong></div>
        <div class="order-total-row"><span>Coupon <?= $order['coupon_code'] ? '(' . e($order['coupon_code']) . ')' : '' ?></span><strong>-<?= money($order['discount']) ?></strong></div>
        <div class="order-total-row"><span>VAT</span><strong><?= money($order['vat']) ?></strong></div>
        <div class="order-total-row"><span>Phí ship</span><strong><?= money($order['shipping_fee']) ?></strong></div>
        <div class="order-total-row order-total-final"><span>Tổng tiền</span><strong><?= money($order['total']) ?></strong></div>
      </section>

      <section class="table-card">
        <h2 class="h5 mb-3">Hành động</h2>
        <div class="d-grid gap-2">
          <a data-action="pay" class="btn btn-danger <?= $actions['pay'] ? '' : 'd-none' ?>" href="<?= e(app_url('api/vnpay.php?action=create&order_id=' . (int)$order['id'])) ?>">Thanh toán ngay</a>
          <a data-action="cancel" class="btn btn-outline-danger <?= $actions['cancel'] ? '' : 'd-none' ?>" href="<?= e(app_url('user/orders.php?focus=' . (int)$order['id'] . '#order-' . (int)$order['id'])) ?>">Hủy đơn hàng</a>
          <a data-action="return" class="btn btn-outline-primary <?= $actions['return'] ? '' : 'd-none' ?>" href="<?= e(app_url('user/orders.php?focus=' . (int)$order['id'] . '#order-' . (int)$order['id'])) ?>">Hoàn/đổi/trả</a>
          <a data-action="review" class="btn btn-outline-dark <?= $actions['review'] ? '' : 'd-none' ?>" href="<?= e(app_url('user/review-order.php?order_id=' . (int)$order['id'])) ?>">Đánh giá sản phẩm</a>
          <a class="btn btn-dark" href="<?= e(app_url('products.php')) ?>">Mua sắm thêm</a>
        </div>
      </section>
    </div>
  </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const page = document.getElementById('orderDetailPage');
  if (!page) return;
  const esc = value => $('<div>').text(value || '').html();
  const renderTimeline = steps => (steps || []).map(step => `<div class="order-step ${step.done ? 'done' : ''} ${step.danger ? 'danger' : ''}"><span></span><strong>${esc(step.label)}</strong></div>`).join('');
  const setAction = (action, visible) => document.querySelectorAll(`[data-action="${action}"]`).forEach(el => el.classList.toggle('d-none', !visible));
  function refreshDetail() {
    fetch(page.dataset.detailUrl, {headers:{'Accept':'application/json'}})
      .then(r => r.ok ? r.json() : null)
      .then(res => {
        if (!res || !res.order) return;
        const order = res.order;
        const badge = page.querySelector('[data-role="status-badge"]');
        if (badge) { badge.className = `order-status-badge ${order.badge_class}`; badge.textContent = order.status_label; }
        const payment = page.querySelector('[data-role="payment-status"]');
        if (payment) payment.textContent = order.payment_status_label;
        const timeline = page.querySelector('[data-role="timeline"]');
        if (timeline) timeline.innerHTML = renderTimeline(order.timeline);
        setAction('pay', order.actions?.pay);
        setAction('cancel', order.actions?.cancel);
        setAction('return', order.actions?.return);
        setAction('review', order.actions?.review);
      })
      .catch(() => {});
  }
  setInterval(refreshDetail, 4000);
});
</script>
<?php render_footer(); ?>
