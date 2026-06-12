<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
ensure_support_schema();
$user = require_login();
render_header('Ticket hỗ trợ');
?>
<main class="container py-5 ticket-page" data-initial-ticket="<?= (int)($_GET['ticket_id'] ?? 0) ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="section-title mb-0">Ticket hỗ trợ</h1>
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#ticketModal"><i class="fa-solid fa-plus me-1"></i>Tạo ticket</button>
  </div>
  <div class="row g-3">
    <div class="col-lg-4"><div class="table-card"><div id="ticketList" class="ticket-list"></div></div></div>
    <div class="col-lg-8">
      <div class="table-card ticket-detail">
        <div id="ticketDetailEmpty" class="text-muted">Chọn một ticket để xem chi tiết.</div>
        <div id="ticketDetail" class="d-none">
          <div class="d-flex justify-content-between gap-2 align-items-start mb-3">
            <div><h2 class="h5 mb-1" id="ticketTitle"></h2><span class="badge bg-secondary" id="ticketStatus"></span></div>
          </div>
          <div id="ticketMessages" class="ticket-messages"></div>
          <form id="ticketReplyForm" class="ticket-reply mt-3" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="ticket_id" id="ticketReplyId">
            <textarea name="message" class="form-control mb-2" required placeholder="Nhập phản hồi của bạn"></textarea>
            <div class="d-flex gap-2"><input type="file" name="attachment" class="form-control"><button class="btn btn-dark">Gửi</button></div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="ticketModal" tabindex="-1">
    <div class="modal-dialog"><form id="ticketCreateForm" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Tạo ticket hỗ trợ</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body">
        <?= csrf_field() ?>
        <label>Chủ đề</label><select name="topic" class="form-select mb-2"><option value="order">Đơn hàng</option><option value="payment">Thanh toán</option><option value="return">Hoàn/đổi/trả</option><option value="warranty">Bảo hành</option><option value="other">Khác</option></select>
        <label>Tiêu đề</label><input name="subject" class="form-control mb-2" required>
        <label>Nội dung</label><textarea name="message" class="form-control mb-2" required></textarea>
        <label>Đính kèm</label><input type="file" name="attachment" class="form-control">
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Hủy</button><button class="btn btn-dark">Gửi ticket</button></div>
    </form></div>
  </div>
</main>
<?php render_footer(); ?>
