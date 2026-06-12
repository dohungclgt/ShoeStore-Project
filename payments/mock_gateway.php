<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_login();
ensure_payment_schema();
ensure_commerce_schema();

$orderId = (int)($_GET['order_id'] ?? 0);
$stmt = db()->prepare("SELECT o.*, p.id payment_id, p.status payment_status, u.email, u.name user_name FROM orders o JOIN payments p ON p.order_id=o.id JOIN users u ON u.id=o.user_id WHERE o.id=? AND o.user_id=? AND p.provider='MOCK' LIMIT 1");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    render_header('Không tìm thấy giao dịch');
    ?>
    <main class="container py-5">
      <div class="table-card text-center mx-auto" style="max-width:640px">
        <h1 class="h4">Không tìm thấy giao dịch thanh toán mô phỏng</h1>
        <p class="text-muted mb-4">Vui lòng quay lại giỏ hàng và thử lại.</p>
        <a class="btn btn-dark" href="<?= e(app_url('cart.php')) ?>">Quay lại giỏ hàng</a>
      </div>
    </main>
    <?php
    render_footer();
    exit;
}
if ($order['payment_status'] === 'paid') {
    flash('success', 'Đơn hàng này đã thanh toán.');
    header('Location: ' . app_url('user/order-detail.php?order_id=' . $orderId));
    exit;
}

render_header('ShoeStore Pay');
?>
<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="table-card p-0 overflow-hidden">
        <div class="p-4 text-white" style="background:linear-gradient(135deg,#111827,#0f766e)">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="fs-4 fw-bold"><i class="fa-solid fa-shield-halved me-2"></i>ShoeStore Pay</div>
              <div class="small opacity-75">Cổng thanh toán mô phỏng an toàn cho môi trường test</div>
            </div>
            <span class="badge bg-light text-dark">Sandbox</span>
          </div>
        </div>
        <div class="p-4">
          <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
            <span>Mã đơn hàng</span>
            <strong><?= e($order['code']) ?></strong>
          </div>
          <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
            <span>Số tiền cần thanh toán</span>
            <strong class="fs-4 text-success"><?= money($order['total']) ?></strong>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <div class="small text-muted">Người mua</div>
                <strong><?= e($order['shipping_name'] ?: $order['user_name']) ?></strong>
                <div class="small"><?= e($order['shipping_phone']) ?></div>
                <div class="small text-muted"><?= e($order['email']) ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <div class="small text-muted">Thời gian còn lại</div>
                <strong id="mockTimer" class="fs-5">10:00</strong>
                <div class="small text-muted">Giao dịch sẽ tự hết hạn nếu không xử lý.</div>
              </div>
            </div>
          </div>
          <form method="post" action="<?= e(app_url('payments/mock_return.php')) ?>" class="d-grid gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
            <button name="result" value="success" class="btn btn-success btn-lg"><i class="fa-solid fa-circle-check me-2"></i>Thanh toán thành công</button>
            <button name="result" value="failed" class="btn btn-outline-danger"><i class="fa-solid fa-circle-xmark me-2"></i>Thanh toán thất bại</button>
            <button name="result" value="cancel" class="btn btn-outline-secondary"><i class="fa-solid fa-ban me-2"></i>Hủy giao dịch</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
document.addEventListener('DOMContentLoaded',()=>{let left=600;const el=document.getElementById('mockTimer');setInterval(()=>{left=Math.max(0,left-1);const m=String(Math.floor(left/60)).padStart(2,'0');const s=String(left%60).padStart(2,'0');el.textContent=m+':'+s;},1000);});
</script>
<?php render_footer(); ?>
