<?php
require_once __DIR__ . '/../_admin.php';
require_once __DIR__ . '/../../includes/mailer.php';

$admin = require_role(['Super Admin', 'Admin', 'Staff']);
ensure_order_action_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));
    if (!in_array($status, ['pending', 'approved', 'rejected', 'received', 'refunded'], true)) {
        $status = 'pending';
    }
    $stmt = db()->prepare('SELECT r.*, o.code, u.email, u.id AS customer_id FROM returns r JOIN orders o ON o.id=r.order_id JOIN users u ON u.id=r.user_id WHERE r.id=?');
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    if (!$request) {
        flash('error', 'Không tìm thấy yêu cầu hoàn trả.');
        header('Location: index.php');
        exit;
    }

    db()->beginTransaction();
    try {
        db()->prepare('UPDATE returns SET status=?, admin_note=?, decided_at=CASE WHEN ? IN ("approved","rejected","refunded") THEN NOW() ELSE decided_at END WHERE id=?')->execute([$status, $adminNote, $status, $id]);
        db()->prepare('INSERT INTO return_logs(return_id,user_id,action,note) VALUES(?,?,?,?)')->execute([$id, $admin['id'], 'admin_' . $status, $adminNote]);
        audit_log('update_return_request', 'returns', $id, ['status' => $status, 'admin_note' => $adminNote]);
        create_notification((int)$request['customer_id'], 'Cập nhật yêu cầu hoàn/đổi/trả', 'Yêu cầu cho đơn hàng ' . $request['code'] . ' đã chuyển sang: ' . return_status_label($status) . '.');
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', 'Không thể cập nhật yêu cầu: ' . $e->getMessage());
        header('Location: index.php');
        exit;
    }

    send_mail($request['email'], 'Cập nhật yêu cầu hoàn/đổi/trả ' . $request['code'], render_email_template('return-request', [
        'headline' => 'Cập nhật yêu cầu hoàn/đổi/trả',
        'message' => 'Yêu cầu của bạn đã được admin cập nhật. Ghi chú: ' . e($adminNote),
        'order_code' => e($request['code']),
        'return_type' => e(return_type_label($request['type'] ?? 'refund')),
        'return_status' => e(return_status_label($status)),
    ]), 'Yêu cầu hoàn/đổi/trả đơn hàng ' . $request['code'] . ' đã chuyển sang ' . return_status_label($status) . '.');
    flash('success', 'Đã cập nhật hoàn trả.');
    header('Location: index.php');
    exit;
}

$rows = db()->query('SELECT r.*, o.code, u.name, u.email FROM returns r JOIN orders o ON o.id=r.order_id JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC')->fetchAll();
$itemsByReturn = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT ri.return_id, oi.product_name, ri.quantity FROM return_items ri JOIN order_items oi ON oi.id=ri.order_item_id WHERE ri.return_id IN ($ph)");
    $stmt->execute($ids);
    foreach ($stmt as $item) {
        $itemsByReturn[(int)$item['return_id']][] = $item;
    }
}

admin_boot('returns','Hoàn trả');
?>
<h1 class="section-title">Hoàn trả</h1>
<div class="table-card">
  <table class="table datatable align-middle">
    <thead><tr><th>Đơn</th><th>Khách</th><th>Loại</th><th>Sản phẩm</th><th>Lý do</th><th>Trạng thái</th><th>Ghi chú admin</th><th>Lưu</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): $formId='return-status-'.(int)$r['id']; ?>
      <tr>
        <td>
          <form id="<?= e($formId) ?>" method="post" data-confirm-submit="Xác nhận cập nhật yêu cầu hoàn trả?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          </form>
          <?= e($r['code']) ?>
          <?php if(!empty($r['evidence_image'])): ?><br><a target="_blank" href="<?= e(app_url($r['evidence_image'])) ?>">Ảnh minh chứng</a><?php endif; ?>
        </td>
        <td><?= e($r['name']) ?><br><small><?= e($r['email']) ?></small></td>
        <td><?= e(return_type_label($r['type'] ?? 'refund')) ?></td>
        <td>
          <?php foreach($itemsByReturn[(int)$r['id']] ?? [] as $item): ?>
            <div><?= e($item['product_name']) ?> x <?= (int)$item['quantity'] ?></div>
          <?php endforeach; ?>
        </td>
        <td><?= e($r['reason']) ?><?php if(!empty($r['detail'])): ?><br><small class="text-muted"><?= e($r['detail']) ?></small><?php endif; ?></td>
        <td>
          <select form="<?= e($formId) ?>" name="status" class="form-select form-select-sm">
            <option value="pending" <?= $r['status']==='pending'?'selected':'' ?>>Đang chờ admin duyệt</option>
            <option value="approved" <?= $r['status']==='approved'?'selected':'' ?>>Đã duyệt</option>
            <option value="rejected" <?= $r['status']==='rejected'?'selected':'' ?>>Từ chối</option>
            <option value="received" <?= $r['status']==='received'?'selected':'' ?>>Đã nhận hàng</option>
            <option value="refunded" <?= $r['status']==='refunded'?'selected':'' ?>>Đã hoàn tiền</option>
          </select>
        </td>
        <td><textarea form="<?= e($formId) ?>" name="admin_note" class="form-control form-control-sm" rows="2" placeholder="Lý do duyệt/từ chối"><?= e($r['admin_note'] ?? '') ?></textarea></td>
        <td><button form="<?= e($formId) ?>" class="btn btn-sm btn-dark">Lưu</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
