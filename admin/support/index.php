<?php
require_once __DIR__ . '/../_admin.php';
ensure_support_schema();
$admin = require_role(['Super Admin','Admin','Staff']);
$staff = db()->query("SELECT u.id,u.name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name IN ('Super Admin','Admin','Staff') ORDER BY u.name")->fetchAll();
admin_boot('support', 'Hỗ trợ');
?>
<main class="ticket-admin-page" data-initial-ticket="<?= (int)($_GET['ticket_id'] ?? 0) ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="section-title mb-0">Ticket hỗ trợ</h1>
    <span class="badge bg-danger" id="ticketAdminBadge" style="display:none">0</span>
  </div>
  <div class="table-card mb-3">
    <div class="row g-2">
      <div class="col-md-3"><select class="form-select" id="ticketFilterStatus"><option value="">Tất cả trạng thái</option><option value="open">Đang mở</option><option value="pending">Đang chờ xử lý</option><option value="answered">Đã phản hồi</option><option value="closed">Đã đóng</option></select></div>
      <div class="col-md-6"><input class="form-control" id="ticketSearch" placeholder="Tìm theo tiêu đề, tên hoặc email khách hàng"></div>
      <div class="col-md-3"><button class="btn btn-dark w-100" id="ticketRefresh">Lọc ticket</button></div>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-lg-5"><div class="table-card"><div id="ticketList" class="ticket-list"></div></div></div>
    <div class="col-lg-7"><div class="table-card ticket-detail">
      <div id="ticketDetailEmpty" class="text-muted">Chọn ticket để xem chi tiết.</div>
      <div id="ticketDetail" class="d-none">
        <div class="d-flex justify-content-between gap-2 align-items-start mb-3">
          <div><h2 class="h5 mb-1" id="ticketTitle"></h2><div><span class="badge bg-secondary" id="ticketStatus"></span> <small class="text-muted" id="ticketCustomer"></small></div></div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-4"><select class="form-select form-select-sm" id="ticketStatusSelect"><option value="open">Mở lại</option><option value="pending">Đang chờ xử lý</option><option value="answered">Đã phản hồi</option><option value="closed">Đóng ticket</option></select></div>
          <div class="col-md-5"><select class="form-select form-select-sm" id="ticketAssignSelect"><option value="">Chưa gán</option><?php foreach ($staff as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><button class="btn btn-sm btn-outline-dark w-100" id="ticketSaveStatus">Lưu</button></div>
        </div>
        <div id="ticketMessages" class="ticket-messages"></div>
        <form id="ticketReplyForm" class="ticket-reply mt-3" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="ticket_id" id="ticketReplyId">
          <input type="hidden" name="status" value="answered">
          <textarea name="message" class="form-control mb-2" required placeholder="Nhập phản hồi cho khách hàng"></textarea>
          <div class="d-flex gap-2"><input type="file" name="attachment" class="form-control"><button class="btn btn-dark">Gửi phản hồi</button></div>
        </form>
      </div>
    </div></div>
  </div>
</main>
<?php admin_end(); ?>
