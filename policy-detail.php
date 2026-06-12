<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
ensure_support_schema();
$slug = trim((string)($_GET['slug'] ?? ''));
$stmt = db()->prepare("SELECT * FROM policies WHERE slug=? AND (status='active' OR active=1) LIMIT 1");
$stmt->execute([$slug]);
$policy = $stmt->fetch();
if (!$policy) {
    http_response_code(404);
    render_header('Không tìm thấy chính sách');
    echo '<main class="container py-5"><div class="alert alert-warning">Không tìm thấy chính sách phù hợp.</div></main>';
    render_footer();
    exit;
}
render_header($policy['title']);
?>
<main class="container py-5" style="max-width:900px">
  <a href="<?= e(app_url('policies.php')) ?>" class="btn btn-link px-0">&larr; Tất cả chính sách</a>
  <article class="table-card">
    <h1 class="section-title"><?= e($policy['title']) ?></h1>
    <?php if (!empty($policy['excerpt'])): ?><p class="lead text-muted"><?= e($policy['excerpt']) ?></p><?php endif; ?>
    <div class="policy-content"><?= nl2br(e($policy['content'])) ?></div>
    <div class="mt-4"><a class="btn btn-dark" href="<?= e(app_url('user/tickets.php')) ?>"><i class="fa-solid fa-headset me-1"></i>Tạo ticket hỗ trợ</a></div>
  </article>
</main>
<?php render_footer(); ?>
