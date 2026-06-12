<?php
declare(strict_types=1);

function render_header(string $title, bool $admin = false): void
{
    $user = current_user();
    $cartCount = array_sum($_SESSION['cart'] ?? []);
    $notifications = [];
    $unreadNotifications = 0;
    if ($user) {
        ensure_support_schema();
        $stmt = db()->prepare('SELECT id,type,title,COALESCE(message,body) AS message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6');
        $stmt->execute([$user['id']]);
        $notifications = $stmt->fetchAll();
        $countStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $countStmt->execute([$user['id']]);
        $unreadNotifications = (int)$countStmt->fetchColumn();
    }
    ?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> - ShoeStore</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css" rel="stylesheet">
  <link href="<?= e(app_url('assets/css/style.css')) ?>?v=20260603" rel="stylesheet">
  <link href="<?= e(app_url('assets/css/chatbot.css')) ?>?v=20260603" rel="stylesheet">
</head>
<body class="<?= $admin ? 'admin-shell' : '' ?>">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= e(app_url('index.php')) ?>"><i class="fa-solid fa-shoe-prints me-2"></i>ShoeStore</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= e(app_url('products.php')) ?>">Sản phẩm</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(app_url('policies.php')) ?>">Chính sách</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(app_url('user/orders.php')) ?>">Đơn hàng</a></li>
        <?php if ($user && in_array($user['role_name'], ['Super Admin', 'Admin', 'Staff'], true)): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(app_url('admin/dashboard.php')) ?>">Admin</a></li>
        <?php endif; ?>
      </ul>
      <div class="d-flex gap-2 align-items-center">
        <a class="btn btn-outline-light btn-sm" href="<?= e(app_url('cart.php')) ?>"><i class="fa-solid fa-cart-shopping"></i> <?= (int)$cartCount ?></a>
        <?php if ($user): ?>
          <div class="dropdown">
            <button class="btn btn-outline-light btn-sm position-relative" data-bs-toggle="dropdown" type="button" id="notificationBell">
              <i class="fa-solid fa-bell"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="<?= $unreadNotifications ? '' : 'display:none' ?>"><?= (int)$unreadNotifications ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-menu notification-dropdown" id="notificationMenu">
              <div class="notification-menu-head"><strong>Thông báo</strong><button class="btn btn-link btn-sm p-0" type="button" id="notificationReadAll">Đánh dấu tất cả là đã đọc</button></div>
              <?php if (!$notifications): ?><div class="dropdown-item text-muted">Chưa có thông báo.</div><?php endif; ?>
              <?php foreach ($notifications as $n): ?>
                <div class="dropdown-item notification-item <?= $n['is_read'] ? '' : 'unread' ?>" data-id="<?= (int)$n['id'] ?>">
                  <span class="notification-icon"><i class="fa-solid <?= e(notification_icon($n['type'] ?? 'system')) ?>"></i></span>
                  <span class="notification-copy">
                  <strong><?= e($n['title']) ?></strong>
                  <p class="mb-0 small text-muted"><?= e($n['message']) ?></p>
                    <a class="btn btn-sm btn-outline-dark mt-2 notification-detail-link" data-id="<?= (int)$n['id'] ?>" href="<?= e(notification_resolve_link($n, $admin)) ?>">Xem chi tiết</a>
                  </span>
                </div>
              <?php endforeach; ?>
              <div class="notification-menu-foot"><a href="<?= e(app_url($admin ? 'admin/notifications.php' : 'user/notifications.php')) ?>">Xem tất cả</a></div>
            </div>
          </div>
          <a class="btn btn-light btn-sm" href="<?= e(app_url('user/profile.php')) ?>"><?= e($user['name']) ?></a>
          <button class="btn btn-outline-light btn-sm theme-toggle" type="button" title="Đổi giao diện"><i class="fa-solid fa-moon"></i></button>
          <a class="btn btn-outline-light btn-sm" href="<?= e(app_url('auth/logout.php')) ?>">Thoát</a>
        <?php else: ?>
          <button class="btn btn-outline-light btn-sm theme-toggle" type="button" title="Đổi giao diện"><i class="fa-solid fa-moon"></i></button>
          <a class="btn btn-light btn-sm" href="<?= e(app_url('auth/login.php')) ?>">Đăng nhập</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<?php if ($msg = flash('success')): ?><div class="container mt-3"><div class="alert alert-success"><?= e($msg) ?></div></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="container mt-3"><div class="alert alert-danger"><?= e($msg) ?></div></div><?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
<footer class="site-footer mt-5 py-4">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
    <div><strong>ShoeStore</strong><p class="mb-0 text-muted">Giày chính hãng, thanh toán an toàn, hỗ trợ nhanh.</p></div>
    <div class="text-md-end"><a href="<?= e(app_url('policies.php')) ?>">Chính sách</a> · <a href="<?= e(app_url('user/tickets.php')) ?>">Hỗ trợ</a> · <a href="<?= e(app_url('products.php')) ?>">Mua sắm</a></div>
  </div>
</footer>
<button id="chatbot-toggle" class="chatbot-toggle" title="Chatbot"><i class="fa-solid fa-comments"></i></button>
<div id="chatbot-panel" class="chatbot-panel d-none">
  <div class="chatbot-head" id="chatbot-drag-handle">
    <span><i class="fa-solid fa-grip-lines me-2"></i>ShoeStore AI</span>
    <div class="d-flex gap-2">
      <button type="button" id="chatbot-minimize" class="btn btn-sm btn-outline-light" title="Thu nhỏ"><i class="fa-solid fa-minus"></i></button>
      <button type="button" id="chatbot-close" class="btn-close btn-close-white" title="Đóng"></button>
    </div>
  </div>
  <div id="chatbot-log" class="chatbot-log"></div>
  <form id="chatbot-form" class="chatbot-form">
    <input class="form-control" name="message" placeholder="Hỏi về sản phẩm, đơn hàng, chính sách">
    <button class="btn btn-dark"><i class="fa-solid fa-paper-plane"></i></button>
  </form>
</div>
<script>window.SHOESTORE={csrf:"<?= e(csrf_token()) ?>",base:"<?= e(app_url()) ?>",vatRate:<?= json_encode(defined('VAT_RATE') ? (float)VAT_RATE : 0.05) ?>};</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>
<script src="<?= e(app_url('assets/js/app.js')) ?>?v=20260603"></script>
</body>
</html>
<?php
}

function admin_sidebar(string $active): void
{
    $items = [
        'dashboard' => ['Dashboard', 'admin/dashboard.php'],
        'products' => ['Sản phẩm', 'admin/products/index.php'],
        'orders' => ['Đơn hàng', 'admin/orders/index.php'],
        'payments' => ['Thanh toán', 'admin/payments/index.php'],
        'returns' => ['Hoàn trả', 'admin/returns/index.php'],
        'coupons' => ['Coupon', 'admin/coupons/index.php'],
        'customers' => ['Khách hàng', 'admin/customers/index.php'],
        'inventory' => ['Kho hàng', 'admin/inventory/index.php'],
        'support' => ['Hỗ trợ', 'admin/support/index.php'],
        'policies' => ['Chính sách', 'admin/policies/index.php'],
        'reviews' => ['Đánh giá', 'admin/reviews/index.php'],
        'news' => ['Tin tức', 'admin/news/index.php'],
        'chatbot-kb' => ['Chatbot KB', 'admin/chatbot-kb/index.php'],
        'fraud' => ['Fraud', 'admin/fraud/index.php'],
        'popupads' => ['Popup quảng cáo', 'admin/popupads/index.php'],
        'auditlogs' => ['Nhật ký', 'admin/auditlogs/index.php'],
        'encoding' => ['Encoding', 'admin/tools/check_encoding.php'],
    ];
    echo '<aside class="admin-sidebar"><div class="list-group list-group-flush">';
    foreach ($items as $key => [$label, $url]) {
        $class = $key === $active ? 'active' : '';
        echo '<a class="list-group-item list-group-item-action ' . $class . '" href="' . e(app_url($url)) . '">' . e($label) . '</a>';
    }
    echo '</div></aside>';
}

