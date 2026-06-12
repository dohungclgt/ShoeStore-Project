<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin', 'Admin']);

$root = realpath(__DIR__ . '/../..');
$patterns = [
    'localhost' => '/localhost/i',
    'http://localhost' => '#http://localhost#i',
    'ngrok test domain' => '#https://5e91#i',
    'root user path' => '#(?<!shoestore)/user/#i',
    'root admin path' => '#(?<!shoestore)/admin/#i',
];
$issues = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) || str_contains($path, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR)) continue;
    if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['php', 'js', 'html'], true)) continue;
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!$lines) continue;
    foreach ($lines as $i => $line) {
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $line)) {
                $rel = str_replace('\\', '/', str_replace($root . DIRECTORY_SEPARATOR, '', $path));
                $allowedConfigLocal = $rel === 'config/app.php' && in_array($name, ['localhost', 'http://localhost', 'ngrok test domain'], true);
                $allowedToolPattern = $rel === 'admin/tools/check_urls.php';
                if ($allowedConfigLocal || $allowedToolPattern) continue;
                $issues[] = ['file' => $rel, 'line' => $i + 1, 'type' => $name, 'text' => mb_strimwidth(trim($line), 0, 220, '...', 'UTF-8')];
            }
        }
    }
}

admin_boot('encoding', 'Kiểm tra URL');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="section-title mb-0">Kiểm tra URL hard-code</h1>
  <a class="btn btn-outline-dark" href="<?= e(app_url('admin/tools/fix_notification_links.php')) ?>">Sửa notification link cũ</a>
</div>
<div class="table-card">
  <p class="text-muted">Quét file `.php`, `.js`, `.html` ngoài `vendor` và `uploads`.</p>
  <table class="table align-middle">
    <thead><tr><th>File</th><th>Dòng</th><th>Loại</th><th>Nội dung</th></tr></thead>
    <tbody>
      <?php if (!$issues): ?><tr><td colspan="4" class="text-muted">Không phát hiện URL nghi ngờ.</td></tr><?php endif; ?>
      <?php foreach ($issues as $issue): ?>
        <tr><td><?= e($issue['file']) ?></td><td><?= (int)$issue['line'] ?></td><td><?= e($issue['type']) ?></td><td><code><?= e($issue['text']) ?></code></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
