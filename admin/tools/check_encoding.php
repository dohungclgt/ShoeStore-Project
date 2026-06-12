<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin', 'Admin']);

$pattern = '/(\x{00C3}|\x{00C2}|\x{00C4}|\x{00C6}|\x{00E1}\x{00BA}|\x{00E1}\x{00BB}|\x{00E2}\x{20AC}|\x{FFFD}|\?\?\?)/u';

function encoding_excerpt(?string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value) ?: '';
    return mb_strimwidth($value, 0, 220, '...', 'UTF-8');
}

$dbIssues = [];
$cols = db()->query("SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND DATA_TYPE IN ('char','varchar','text','mediumtext','longtext','tinytext','enum','set') ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll();
foreach ($cols as $col) {
    $table = $col['TABLE_NAME'];
    $column = $col['COLUMN_NAME'];
    try {
        $stmt = db()->query('SELECT * FROM `' . str_replace('`', '``', $table) . '` LIMIT 500');
    } catch (Throwable $e) {
        continue;
    }
    foreach ($stmt as $row) {
        $value = (string)($row[$column] ?? '');
        if ($value !== '' && preg_match($pattern, $value)) {
            $dbIssues[] = [
                'table' => $table,
                'column' => $column,
                'id' => $row['id'] ?? '',
                'value' => encoding_excerpt($value),
            ];
        }
    }
}

$fileIssues = [];
$root = realpath(__DIR__ . '/../..');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) || str_contains($path, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR)) continue;
    if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['php', 'js', 'css', 'html', 'sql', 'txt', 'md'], true)) continue;
    $content = @file_get_contents($path);
    if ($content === false) continue;
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $fileIssues[] = ['file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path), 'line' => 1, 'value' => 'UTF-8 BOM'];
    }
    if (!mb_check_encoding($content, 'UTF-8')) {
        $fileIssues[] = ['file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path), 'line' => 0, 'value' => 'File không phải UTF-8 hợp lệ'];
        continue;
    }
    foreach (preg_split('/\R/u', $content) as $i => $line) {
        if (preg_match($pattern, $line)) {
            $fileIssues[] = ['file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path), 'line' => $i + 1, 'value' => encoding_excerpt($line)];
        }
    }
}

admin_boot('encoding', 'Kiểm tra encoding');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="section-title">Kiểm tra encoding</h1>
  <a class="btn btn-outline-dark" href="<?= e(app_url('admin/tools/check_encoding.php')) ?>"><i class="fa-solid fa-rotate"></i> Quét lại</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-6"><div class="metric"><span class="text-muted">Database nghi ngờ</span><h3><?= count($dbIssues) ?></h3></div></div>
  <div class="col-md-6"><div class="metric"><span class="text-muted">File nghi ngờ</span><h3><?= count($fileIssues) ?></h3></div></div>
</div>
<div class="table-card mb-4">
  <h2 class="h5">Database</h2>
  <table class="table align-middle">
    <thead><tr><th>Table</th><th>Cột</th><th>ID</th><th>Giá trị nghi ngờ</th></tr></thead>
    <tbody>
      <?php if (!$dbIssues): ?><tr><td colspan="4" class="text-muted">Không phát hiện dữ liệu nghi ngờ.</td></tr><?php endif; ?>
      <?php foreach ($dbIssues as $issue): ?>
        <tr><td><?= e($issue['table']) ?></td><td><?= e($issue['column']) ?></td><td><?= e((string)$issue['id']) ?></td><td><code><?= e($issue['value']) ?></code></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="table-card">
  <h2 class="h5">Source files</h2>
  <table class="table align-middle">
    <thead><tr><th>File</th><th>Dòng</th><th>Nội dung nghi ngờ</th></tr></thead>
    <tbody>
      <?php if (!$fileIssues): ?><tr><td colspan="3" class="text-muted">Không phát hiện file nghi ngờ.</td></tr><?php endif; ?>
      <?php foreach ($fileIssues as $issue): ?>
        <tr><td><?= e($issue['file']) ?></td><td><?= e((string)$issue['line']) ?></td><td><code><?= e($issue['value']) ?></code></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
