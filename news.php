<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
ensure_news_schema();
$rows = db()->query("SELECT * FROM news WHERE status='published' ORDER BY created_at DESC")->fetchAll();
render_header('Tin tức Sneaker');
?>
<main class="container py-5">
  <div class="d-flex justify-content-between align-items-end mb-4">
    <div><h1 class="section-title mb-1">Tin tức Sneaker</h1><p class="section-subtitle mb-0">Hướng dẫn chọn giày, bảo quản và cập nhật xu hướng sneaker.</p></div>
  </div>
  <div class="row g-4">
    <?php foreach ($rows as $n): ?>
      <div class="col-md-6 col-lg-4">
        <article class="card product-card h-100">
          <img src="<?= e($n['thumbnail']) ?>" class="card-img-top" alt="<?= e($n['title']) ?>">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between small text-muted mb-2"><span><?= e($n['author']) ?></span><span><?= e(date('d/m/Y', strtotime($n['created_at']))) ?></span></div>
            <h2 class="h5"><?= e($n['title']) ?></h2>
            <p class="text-muted"><?= e($n['excerpt']) ?></p>
            <div class="mb-3"><span class="badge text-bg-light"><?= e($n['tags'] ?? '') ?></span></div>
            <a class="btn btn-dark mt-auto" href="<?= e(app_url('news-detail.php?slug=' . urlencode($n['slug']))) ?>">Đọc thêm</a>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php render_footer(); ?>
