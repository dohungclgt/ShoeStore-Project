<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
ensure_news_schema();
function news_content_html(string $html): string
{
    return strip_tags($html, '<p><br><h2><h3><ul><ol><li><strong><em><a>');
}
$stmt = db()->prepare("SELECT * FROM news WHERE slug=? AND status='published'");
$stmt->execute([$_GET['slug'] ?? '']);
$n = $stmt->fetch();
if (!$n) { http_response_code(404); exit('News not found'); }
render_header($n['title']);
?>
<main class="container py-5" style="max-width:920px">
  <article>
    <img src="<?= e($n['thumbnail']) ?>" class="img-fluid rounded mb-4" alt="<?= e($n['title']) ?>">
    <div class="small text-muted mb-2"><?= e($n['author']) ?> · <?= e(date('d/m/Y', strtotime($n['created_at']))) ?> · <?= e($n['tags'] ?? '') ?></div>
    <h1 class="section-title"><?= e($n['title']) ?></h1>
    <p class="lead"><?= e($n['excerpt']) ?></p>
    <div class="table-card news-content fs-5" style="line-height:1.8"><?= news_content_html($n['content']) ?></div>
  </article>
</main>
<?php render_footer(); ?>
