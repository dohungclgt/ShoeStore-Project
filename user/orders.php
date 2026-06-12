<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/order-ui.php';

$user = require_login();
ensure_payment_schema();
ensure_order_action_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);
    $stmt = db()->prepare('SELECT o.*, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=? AND o.user_id=?');
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();

    if (!$order) {
        flash('error', 'Không tìm thấy đơn hàng.');
        header('Location: orders.php');
        exit;
    }

    if ($action === 'cancel') {
        $reason = trim((string)($_POST['cancel_reason'] ?? ''));
        if (!in_array($order['status'], order_cancelable_statuses(), true)) {
            flash('error', 'Đơn hàng này không còn được phép hủy.');
            header('Location: orders.php');
            exit;
        }
        if ($reason === '') {
            flash('error', 'Vui lòng nhập lý do hủy đơn.');
            header('Location: orders.php');
            exit;
        }

        db()->beginTransaction();
        try {
            db()->prepare("UPDATE orders SET status='cancelled', cancel_reason=?, cancelled_at=NOW() WHERE id=? AND user_id=?")->execute([$reason, $orderId, $user['id']]);
            db()->prepare("UPDATE payments SET status=CASE WHEN status='paid' THEN status ELSE 'failed' END WHERE order_id=? AND provider IN ('MOMO','VNPAY')")->execute([$orderId]);
            restore_order_stock($orderId, (int)$user['id']);
            audit_log('cancel_order', 'orders', $orderId, ['reason' => $reason, 'code' => $order['code']]);
            notify_admins('Khách hủy đơn hàng', 'Đơn hàng ' . $order['code'] . ' đã bị khách hủy. Lý do: ' . $reason, 'order', app_url('admin/orders/index.php?order_id=' . $orderId));
            create_notification((int)$user['id'], 'Đã hủy đơn hàng', 'Đơn hàng ' . $order['code'] . ' đã được hủy.', 'order', app_url('user/order-detail.php?order_id=' . $orderId));
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('error', 'Không thể hủy đơn hàng: ' . $e->getMessage());
            header('Location: orders.php');
            exit;
        }

        $order['status'] = 'cancelled';
        send_mail($order['email'], 'Đã hủy đơn hàng ' . $order['code'], render_email_template('order-cancelled', array_merge(order_email_data($order, 'Đơn hàng đã được hủy', 'Đơn hàng của bạn đã được hủy và tồn kho đã được hoàn lại.'), ['cancel_reason' => e($reason)])), 'Đơn hàng ' . $order['code'] . ' đã được hủy. Lý do: ' . $reason);
        flash('success', 'Đã hủy đơn hàng và hoàn lại tồn kho.');
        header('Location: orders.php');
        exit;
    }

    if ($action === 'return_request') {
        $type = $_POST['return_type'] ?? 'refund';
        $reason = trim((string)($_POST['return_reason'] ?? ''));
        $detail = trim((string)($_POST['return_detail'] ?? ''));
        $selectedItems = array_values(array_filter(array_map('intval', $_POST['order_item_ids'] ?? [])));
        if (!in_array($order['status'], order_returnable_statuses(), true)) {
            flash('error', 'Đơn hàng này chưa đủ điều kiện hoàn/đổi/trả.');
            header('Location: orders.php');
            exit;
        }
        if (!in_array($type, ['refund', 'exchange', 'return'], true)) {
            $type = 'refund';
        }
        if ($reason === '') {
            flash('error', 'Vui lòng nhập lý do hoàn/đổi/trả.');
            header('Location: orders.php');
            exit;
        }
        if (!$selectedItems) {
            flash('error', 'Vui lòng chọn sản phẩm liên quan.');
            header('Location: orders.php');
            exit;
        }

        try {
            $evidence = upload_file($_FILES['evidence_image'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'uploads/returns', ['image/jpeg', 'image/png', 'image/webp']);
            db()->beginTransaction();
            db()->prepare('INSERT INTO returns(order_id,user_id,type,reason,detail,evidence_image,status,created_at) VALUES(?,?,?,?,?,?,?,NOW())')->execute([$orderId, $user['id'], $type, $reason, $detail, $evidence, 'pending']);
            $returnId = (int)db()->lastInsertId();
            $itemStmt = db()->prepare('SELECT id, product_id, quantity FROM order_items WHERE id=? AND order_id=?');
            $insertItem = db()->prepare('INSERT INTO return_items(return_id,order_item_id,product_id,quantity) VALUES(?,?,?,?)');
            foreach ($selectedItems as $itemId) {
                $itemStmt->execute([$itemId, $orderId]);
                $item = $itemStmt->fetch();
                if ($item) {
                    $insertItem->execute([$returnId, $item['id'], $item['product_id'], $item['quantity']]);
                }
            }
            db()->prepare('INSERT INTO return_logs(return_id,user_id,action,note) VALUES(?,?,?,?)')->execute([$returnId, $user['id'], 'created', $reason]);
            audit_log('create_return_request', 'returns', $returnId, ['order_id' => $orderId, 'type' => $type]);
            notify_admins('Yêu cầu hoàn/đổi/trả mới', 'Đơn hàng ' . $order['code'] . ' có yêu cầu ' . return_type_label($type) . '.', 'return', app_url('admin/returns/index.php?id=' . $returnId));
            create_notification((int)$user['id'], 'Đang chờ admin duyệt', 'Yêu cầu ' . return_type_label($type) . ' cho đơn hàng ' . $order['code'] . ' đã được gửi.', 'return', app_url('user/orders.php?return_id=' . $returnId));
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('error', 'Không thể tạo yêu cầu hoàn/đổi/trả: ' . $e->getMessage());
            header('Location: orders.php');
            exit;
        }

        send_mail($order['email'], 'Đã nhận yêu cầu hoàn/đổi/trả ' . $order['code'], render_email_template('return-request', [
            'headline' => 'Đã nhận yêu cầu hoàn/đổi/trả',
            'message' => 'Yêu cầu của bạn đang chờ admin duyệt.',
            'order_code' => e($order['code']),
            'return_type' => e(return_type_label($type)),
            'return_status' => e(return_status_label('pending')),
            'orders_url' => app_url('user/orders.php?return_id=' . $returnId),
        ]), 'Yêu cầu ' . return_type_label($type) . ' cho đơn hàng ' . $order['code'] . ' đang chờ admin duyệt.');
        flash('success', 'Đã gửi yêu cầu. Trạng thái: Đang chờ admin duyệt.');
        header('Location: orders.php');
        exit;
    }
}

$stmt = db()->prepare("SELECT o.*,p.status payment_status,p.provider payment_provider FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.user_id=? ORDER BY o.created_at DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = db()->prepare("SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id IN ($ph) ORDER BY oi.id");
    $itemStmt->execute($ids);
    foreach ($itemStmt as $item) {
        $itemsByOrder[(int)$item['order_id']][] = $item;
    }
}

$filters = [
    '' => 'Tất cả',
    'pending_payment' => 'Chờ thanh toán',
    'waiting_confirm' => 'Chờ xác nhận',
    'waiting_pickup' => 'Chờ lấy hàng',
    'packing' => 'Đang đóng gói',
    'shipping' => 'Đang giao hàng',
    'delivered' => 'Đã giao hàng',
    'cancelled' => 'Đã hủy',
    'returned' => 'Hoàn/đổi/trả',
];

render_header('Đơn hàng của tôi');
?>
<main class="container py-5 order-page">
  <div class="order-hero mb-4">
    <div>
      <span class="hero-kicker"><i class="fa-solid fa-receipt"></i> ShoeStore Orders</span>
      <h1>Đơn hàng của tôi</h1>
      <p>Theo dõi trạng thái, thanh toán, tải hóa đơn và gửi yêu cầu hỗ trợ cho từng đơn hàng.</p>
    </div>
  </div>

  <div class="order-toolbar mb-4">
    <div class="order-filter-tabs" id="orderFilterTabs">
      <?php foreach ($filters as $value => $label): ?>
        <button type="button" class="order-filter-btn <?= $value === '' ? 'active' : '' ?>" data-status="<?= e($value) ?>"><?= e($label) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="input-icon order-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input id="orderSearch" class="form-control" placeholder="Tìm mã đơn hàng">
    </div>
  </div>

  <div id="ordersLiveRegion" class="orders-grid" data-poll-url="<?= e(app_url('api/orders/poll.php')) ?>">
    <?php if (!$orders): ?>
      <div class="table-card text-center py-5"><h2 class="h5">Bạn chưa có đơn hàng nào.</h2><a class="btn btn-dark mt-2" href="<?= e(app_url('products.php')) ?>">Mua sắm ngay</a></div>
    <?php endif; ?>
    <?php foreach ($orders as $order): ?>
      <?php
        $orderId = (int)$order['id'];
        $items = $itemsByOrder[$orderId] ?? [];
        $flags = order_action_flags($order);
      ?>
      <article class="order-card" id="order-<?= $orderId ?>" data-order-id="<?= $orderId ?>" data-code="<?= e(mb_strtolower($order['code'], 'UTF-8')) ?>" data-status-group="<?= e(order_status_group($order['status'])) ?>">
        <div class="order-card-head">
          <div>
            <div class="order-code"><?= e($order['code']) ?></div>
            <div class="text-muted small"><i class="fa-regular fa-clock me-1"></i><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></div>
          </div>
          <span class="order-status-badge <?= e(order_status_badge_class($order['status'])) ?>" data-role="status-badge"><?= e(order_status_label($order['status'])) ?></span>
        </div>

        <div class="order-timeline compact" data-role="timeline">
          <?php foreach (order_timeline_steps($order['status'], $order['payment_status'] ?? null) as $step): ?>
            <div class="order-step <?= !empty($step['done']) ? 'done' : '' ?> <?= !empty($step['danger']) ? 'danger' : '' ?>"><span></span><strong><?= e($step['label']) ?></strong></div>
          <?php endforeach; ?>
        </div>

        <div class="order-card-body">
          <div class="order-products">
            <?php foreach (array_slice($items, 0, 3) as $item): ?>
              <div class="order-product-line">
                <img src="<?= e($item['image'] ?: app_url('assets/img/review-placeholder.svg')) ?>" alt="<?= e($item['product_name']) ?>">
                <div><strong><?= e($item['product_name']) ?></strong><small>Size <?= e($item['size'] ?? '-') ?> · x<?= (int)$item['quantity'] ?></small></div>
              </div>
            <?php endforeach; ?>
            <?php if (count($items) > 3): ?><div class="text-muted small">+<?= count($items) - 3 ?> sản phẩm khác</div><?php endif; ?>
          </div>
          <div class="order-summary">
            <div><span>Thanh toán</span><strong><i class="fa-solid <?= e(order_payment_icon($order['payment_method'])) ?> me-1"></i><?= e($order['payment_method']) ?> · <span data-role="payment-status"><?= e(payment_status_label($order['payment_status'] ?? '')) ?></span></strong></div>
            <div><span>Tổng tiền</span><strong class="price"><?= money($order['total']) ?></strong></div>
          </div>
        </div>

        <div class="order-card-actions" data-role="actions">
          <a class="btn btn-sm btn-dark" href="<?= e(app_url('user/order-detail.php?order_id=' . $orderId)) ?>"><i class="fa-solid fa-eye me-1"></i>Xem chi tiết</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('invoice/generate-pdf.php?order_id=' . $orderId)) ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('invoice/generate-excel.php?order_id=' . $orderId)) ?>"><i class="fa-solid fa-file-excel"></i> Excel</a>
          <a class="btn btn-sm btn-danger <?= $flags['pay'] ? '' : 'd-none' ?>" data-action="pay" href="<?= e(app_url('api/vnpay.php?action=create&order_id=' . $orderId)) ?>">Thanh toán ngay</a>
          <button class="btn btn-sm btn-outline-danger <?= $flags['cancel'] ? '' : 'd-none' ?>" data-action="cancel" type="button" data-bs-toggle="modal" data-bs-target="#cancelOrder<?= $orderId ?>">Hủy đơn hàng</button>
          <button class="btn btn-sm btn-outline-primary <?= $flags['return'] ? '' : 'd-none' ?>" data-action="return" type="button" data-bs-toggle="modal" data-bs-target="#returnOrder<?= $orderId ?>">Hoàn/đổi/trả</button>
          <a class="btn btn-sm btn-outline-dark <?= $flags['review'] ? '' : 'd-none' ?>" data-action="review" href="<?= e(app_url('user/review-order.php?order_id=' . $orderId)) ?>">Đánh giá sản phẩm</a>
        </div>
      </article>

      <div class="modal fade" id="cancelOrder<?= $orderId ?>" tabindex="-1">
        <div class="modal-dialog">
          <form method="post" class="modal-content cancel-order-form">
            <div class="modal-header"><h5 class="modal-title">Hủy đơn hàng <?= e($order['code']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="order_id" value="<?= $orderId ?>">
              <label class="form-label">Lý do hủy</label>
              <textarea name="cancel_reason" class="form-control" rows="4" required></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button><button class="btn btn-danger">Xác nhận hủy</button></div>
          </form>
        </div>
      </div>

      <div class="modal fade" id="returnOrder<?= $orderId ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <form method="post" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Yêu cầu hoàn/đổi/trả <?= e($order['code']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="return_request">
              <input type="hidden" name="order_id" value="<?= $orderId ?>">
              <div class="col-md-4"><label class="form-label">Loại yêu cầu</label><select name="return_type" class="form-select"><option value="refund">Hoàn tiền</option><option value="exchange">Đổi hàng</option><option value="return">Trả hàng</option></select></div>
              <div class="col-md-8"><label class="form-label">Ảnh minh chứng</label><input type="file" name="evidence_image" class="form-control" accept="image/jpeg,image/png,image/webp"></div>
              <div class="col-12">
                <label class="form-label">Sản phẩm liên quan</label>
                <div class="row g-2">
                  <?php foreach ($items as $item): ?>
                    <div class="col-md-6"><label class="border rounded p-2 d-flex gap-2 align-items-center"><input type="checkbox" name="order_item_ids[]" value="<?= (int)$item['id'] ?>" checked><span><?= e($item['product_name']) ?> x <?= (int)$item['quantity'] ?></span></label></div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12"><label class="form-label">Lý do</label><textarea name="return_reason" class="form-control" rows="3" required></textarea></div>
              <div class="col-12"><label class="form-label">Mô tả chi tiết</label><textarea name="return_detail" class="form-control" rows="4"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button><button class="btn btn-dark">Gửi yêu cầu</button></div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('orderSearch');
  const filterButtons = document.querySelectorAll('.order-filter-btn');
  let activeStatus = '';

  function applyFilters() {
    const q = (search?.value || '').trim().toLowerCase();
    document.querySelectorAll('.order-card').forEach(card => {
      const statusOk = !activeStatus || card.dataset.statusGroup === activeStatus;
      const queryOk = !q || (card.dataset.code || '').includes(q);
      card.classList.toggle('d-none', !(statusOk && queryOk));
    });
  }

  filterButtons.forEach(btn => btn.addEventListener('click', () => {
    filterButtons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeStatus = btn.dataset.status || '';
    applyFilters();
  }));
  search?.addEventListener('input', applyFilters);

  document.querySelectorAll('.cancel-order-form').forEach(form => {
    form.addEventListener('submit', e => {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();
      Swal.fire({title:'Bạn có chắc chắn muốn hủy đơn hàng này không?',icon:'warning',showCancelButton:true,confirmButtonText:'Xác nhận hủy',cancelButtonText:'Đóng'}).then(r => {
        if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); }
      });
    });
  });

  function renderTimeline(steps) {
    return (steps || []).map(step => `<div class="order-step ${step.done ? 'done' : ''} ${step.danger ? 'danger' : ''}"><span></span><strong>${$('<div>').text(step.label || '').html()}</strong></div>`).join('');
  }

  function setAction(card, action, visible) {
    card.querySelectorAll(`[data-action="${action}"]`).forEach(el => el.classList.toggle('d-none', !visible));
  }

  function pollOrders() {
    const region = document.getElementById('ordersLiveRegion');
    if (!region || document.querySelector('.modal.show')) return;
    fetch(region.dataset.pollUrl, {headers:{'Accept':'application/json'}})
      .then(r => r.ok ? r.json() : null)
      .then(res => {
        if (!res || !res.items) return;
        res.items.forEach(order => {
          const card = document.querySelector(`.order-card[data-order-id="${order.id}"]`);
          if (!card) return;
          card.dataset.statusGroup = order.status_group;
          const badge = card.querySelector('[data-role="status-badge"]');
          if (badge) {
            badge.className = `order-status-badge ${order.badge_class}`;
            badge.textContent = order.status_label;
          }
          const payment = card.querySelector('[data-role="payment-status"]');
          if (payment) payment.textContent = order.payment_status_label;
          const timeline = card.querySelector('[data-role="timeline"]');
          if (timeline) timeline.innerHTML = renderTimeline(order.timeline);
          setAction(card, 'pay', order.actions?.pay);
          setAction(card, 'cancel', order.actions?.cancel);
          setAction(card, 'return', order.actions?.return);
          setAction(card, 'review', order.actions?.review);
        });
        applyFilters();
      })
      .catch(() => {});
  }

  setInterval(pollOrders, 4000);
  pollOrders();

  const focusId = new URLSearchParams(location.search).get('focus');
  if (focusId) document.getElementById(`order-${focusId}`)?.scrollIntoView({behavior:'smooth', block:'center'});
});
</script>
<?php render_footer(); ?>
