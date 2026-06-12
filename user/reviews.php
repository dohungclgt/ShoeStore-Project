<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int)($_POST['product_id'] ?? 0);
    db()->prepare('INSERT INTO reviews(user_id,product_id,rating,comment) VALUES(?,?,?,?)')
        ->execute([$user['id'], $productId, (int)$_POST['rating'], trim((string)$_POST['comment'])]);

    $productLink = 'admin/reviews/index.php';
    $productStmt = db()->prepare('SELECT slug FROM products WHERE id=?');
    $productStmt->execute([$productId]);
    $productSlug = (string)$productStmt->fetchColumn();
    if ($productSlug !== '') {
        $productLink = 'product.php?slug=' . rawurlencode($productSlug);
    }

    notify_admins('Có đánh giá mới', 'Một đánh giá sản phẩm mới đang chờ duyệt.', 'review', $productLink);
    flash('success', 'Đánh giá đang chờ duyệt.');
    header('Location: reviews.php');
    exit;
}

$products = db()->query('SELECT id,name FROM products WHERE status="active" ORDER BY name')->fetchAll();
$reviews = db()->prepare('SELECT r.*,p.name product_name FROM reviews r JOIN products p ON p.id=r.product_id WHERE r.user_id=? ORDER BY r.created_at DESC');
$reviews->execute([$user['id']]);

render_header('Đánh giá');
?>
<main class="container py-5">
  <h1 class="section-title">Đánh giá sản phẩm</h1>
  <form method="post" class="table-card row g-2 mb-4" data-confirm-submit="Gửi đánh giá sản phẩm này?">
    <?= csrf_field() ?>
    <div class="col-md-4">
      <select name="product_id" class="form-select" required>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><input type="number" name="rating" min="1" max="5" value="5" class="form-control"></div>
    <div class="col-md-5"><input name="comment" class="form-control" required placeholder="Nội dung đánh giá"></div>
    <div class="col-md-1"><button class="btn btn-dark">Gửi</button></div>
  </form>
  <div class="table-card">
    <table class="table">
      <?php foreach ($reviews as $r): ?>
        <tr>
          <td><?= e($r['product_name']) ?></td>
          <td><?= (int)$r['rating'] ?>/5</td>
          <td><?= e($r['approved'] ? 'Đã duyệt' : 'Chờ duyệt') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</main>
<?php render_footer(); ?>
