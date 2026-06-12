<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
ensure_support_schema();
$user = require_login();
$filter = $_GET['filter'] ?? '';
$where = 'user_id=?';
$params = [$user['id']];
if ($filter === 'unread') $where .= ' AND is_read=0';
elseif ($filter === 'read') $where .= ' AND is_read=1';
$stmt = db()->prepare("SELECT id,type,title,COALESCE(message,body) message,link,is_read,created_at FROM notifications WHERE $where ORDER BY created_at DESC LIMIT 150");
$stmt->execute($params);
$items = $stmt->fetchAll();
render_header('Thông báo');
?>
<main class="container py-5">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
    <h1 class="section-title mb-0">Thông báo</h1>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm <?= $filter===''?'btn-dark':'btn-outline-dark' ?>" href="<?= e(app_url('user/notifications.php')) ?>">Tất cả</a>
      <a class="btn btn-sm <?= $filter==='unread'?'btn-dark':'btn-outline-dark' ?>" href="<?= e(app_url('user/notifications.php?filter=unread')) ?>">Chưa đọc</a>
      <a class="btn btn-sm <?= $filter==='read'?'btn-dark':'btn-outline-dark' ?>" href="<?= e(app_url('user/notifications.php?filter=read')) ?>">Đã đọc</a>
      <button class="btn btn-sm btn-outline-primary" id="notificationPageReadAll">Đánh dấu tất cả đã đọc</button>
    </div>
  </div>
  <div class="notification-list">
    <?php foreach ($items as $n): ?>
      <article class="notification-row <?= $n['is_read'] ? '' : 'unread' ?>">
        <span class="notification-icon"><i class="fa-solid <?= e(notification_icon($n['type'])) ?>"></i></span>
        <div class="notification-copy">
          <strong><?= e($n['title']) ?></strong>
          <p><?= e($n['message']) ?></p>
          <small><?= e($n['created_at']) ?></small>
          <a class="btn btn-sm btn-dark mt-2 notification-detail-link" data-id="<?= (int)$n['id'] ?>" href="<?= e(notification_resolve_link($n, false)) ?>">Xem chi tiết</a>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$items): ?><div class="alert alert-info">Chưa có thông báo.</div><?php endif; ?>
  </div>
</main>
<?php render_footer(); ?>
