<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/support-system.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function absolute_url(string $path = ''): string
{
    return app_url($path);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_url('auth/login.php'));
        exit;
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role_name'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function money(float|int|string $amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' VND';
}

function order_status_label(string $status): string
{
    return [
        'pending_payment' => 'Chờ thanh toán',
        'waiting_confirm' => 'Chờ xác nhận',
        'waiting_pickup' => 'Chờ lấy hàng',
        'packing' => 'Đang đóng gói',
        'shipping' => 'Đang giao hàng',
        'delivered' => 'Đã giao hàng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
        'returned' => 'Hoàn trả',
        'cho_thanh_toan' => 'Chờ thanh toán',
        'da_thanh_toan' => 'Đã thanh toán',
        'cho_xac_nhan' => 'Chờ xác nhận',
        'dang_dong_goi' => 'Đang đóng gói',
        'dang_van_chuyen' => 'Đang vận chuyển',
        'da_giao' => 'Đã giao',
        'hoan_thanh' => 'Hoàn thành',
        'da_huy' => 'Đã hủy',
        'hoan_tra' => 'Hoàn trả',
    ][$status] ?? $status;
}

function payment_status_label(string $status): string
{
    return [
        'pending' => 'Đang chờ',
        'paid' => 'Đã thanh toán',
        'success' => 'Đã thanh toán',
        'failed' => 'Thất bại',
        'unpaid' => 'Chưa thanh toán',
        'refunded' => 'Đã hoàn tiền',
    ][$status] ?? $status;
}

function normalize_payment_method(string $method): string
{
    return strtoupper($method);
}

function clear_paid_cart_items(int $orderId): void
{
    $ids = $_SESSION['pending_order_items'][$orderId] ?? [];
    if (!$ids) {
        $stmt = db()->prepare('SELECT product_id,size FROM order_items WHERE order_id=?');
        $stmt->execute([$orderId]);
        $ids = array_map(fn($row) => (int)$row['product_id'] . ':' . (string)$row['size'], $stmt->fetchAll());
    }
    foreach ($ids as $key) {
        unset($_SESSION['cart'][$key], $_SESSION['cart'][(int)$key]);
    }
    unset($_SESSION['checkout_items'], $_SESSION['pending_order_items'][$orderId]);
}

function ensure_payment_schema(): void
{
    static $done = false;
    if ($done) return;
    try { db()->exec("ALTER TABLE orders MODIFY payment_method ENUM('COD','MOMO','VNPAY','MOCK') NOT NULL"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE orders MODIFY status ENUM('pending_payment','waiting_confirm','waiting_pickup','packing','shipping','delivered','completed','cancelled','returned','cho_thanh_toan','da_thanh_toan','cho_xac_nhan','dang_dong_goi','dang_van_chuyen','da_giao','hoan_thanh','da_huy','hoan_tra') NOT NULL DEFAULT 'waiting_confirm'"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE payments MODIFY provider ENUM('','COD','VNPAY','MOMO','MOCK') NOT NULL"); } catch (Throwable $e) {}
    try { db()->exec("UPDATE payments p JOIN orders o ON o.id=p.order_id SET p.provider=o.payment_method WHERE p.provider='' AND o.payment_method IN ('COD','VNPAY','MOMO','MOCK')"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE payments MODIFY provider ENUM('COD','VNPAY','MOMO','MOCK') NOT NULL"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE payments MODIFY status ENUM('pending','paid','success','failed','unpaid','refunded') NOT NULL DEFAULT 'pending'"); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE payments ADD COLUMN payment_attempts INT NOT NULL DEFAULT 0 AFTER status'); } catch (Throwable $e) {}
    $done = true;
}

function ensure_order_action_schema(): void
{
    static $done = false;
    if ($done) return;
    try { db()->exec("ALTER TABLE orders MODIFY status ENUM('pending_payment','waiting_confirm','waiting_pickup','packing','shipping','delivered','completed','cancelled','returned','cho_thanh_toan','da_thanh_toan','cho_xac_nhan','dang_dong_goi','dang_van_chuyen','da_giao','hoan_thanh','da_huy','hoan_tra') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting_confirm'"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE orders ADD COLUMN cancel_reason TEXT NULL AFTER note"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE orders ADD COLUMN cancelled_at DATETIME NULL AFTER cancel_reason"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE orders ADD COLUMN stock_restored_at DATETIME NULL AFTER cancelled_at"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE returns MODIFY status ENUM('pending','approved','rejected','received','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE returns ADD COLUMN type ENUM('refund','exchange','return') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'refund' AFTER user_id"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE returns ADD COLUMN detail TEXT NULL AFTER reason"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE returns ADD COLUMN evidence_image VARCHAR(255) NULL AFTER detail"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE returns ADD COLUMN admin_note TEXT NULL AFTER status"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE returns ADD COLUMN decided_at DATETIME NULL AFTER admin_note"); } catch (Throwable $e) {}
    try { db()->exec("CREATE TABLE IF NOT EXISTS return_items (id INT AUTO_INCREMENT PRIMARY KEY, return_id INT NOT NULL, order_item_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE, FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
    try { db()->exec("CREATE TABLE IF NOT EXISTS return_logs (id INT AUTO_INCREMENT PRIMARY KEY, return_id INT NOT NULL, user_id INT NULL, action VARCHAR(80) NOT NULL, note TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
    $done = true;
}

function ensure_news_schema(): void
{
    static $done = false;
    if ($done) return;
    db()->exec("CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(220) NOT NULL,
        slug VARCHAR(240) NOT NULL UNIQUE,
        thumbnail VARCHAR(255) NOT NULL,
        excerpt TEXT NOT NULL,
        content MEDIUMTEXT NOT NULL,
        author VARCHAR(120) NOT NULL DEFAULT 'ShoeStore Team',
        tags VARCHAR(255) NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'published',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT KEY news_search(title, excerpt, content, tags)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $count = (int)db()->query('SELECT COUNT(*) FROM news')->fetchColumn();
    if ($count < 6) {
        $rows = [
            ['Cách chọn giày chạy bộ phù hợp','cach-chon-giay-chay-bo-phu-hop','https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=900&q=80','Chọn giày chạy bộ cần cân bằng độ êm, trọng lượng, form chân và bề mặt tập luyện để giảm chấn thương.','Một đôi giày chạy bộ tốt không chỉ nhẹ mà còn phải ổn định ở gót, thoáng khí ở thân giày và có đệm phù hợp với cự ly bạn thường chạy. Khi mua online, hãy đo chân vào cuối ngày, kiểm tra bảng size và ưu tiên mẫu có chính sách đổi size rõ ràng.','ShoeStore Team','Running, Hướng dẫn'],
            ['Xu hướng sneaker năm nay','xu-huong-sneaker-nam-nay','https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=900&q=80','Sneaker năm nay nghiêng về phom cổ điển, phối màu sạch và chất liệu dễ bảo quản.','Các phối màu trắng, xám, xanh navy và đen vẫn chiếm ưu thế vì dễ phối với đồ đi làm lẫn streetwear. Bên cạnh đó, các mẫu đế dày vừa phải và chất liệu da lộn xử lý chống bám bụi đang được ưa chuộng.','ShoeStore Editorial','Xu hướng, Lifestyle'],
            ['Cách phân biệt giày chính hãng','cach-phan-biet-giay-chinh-hang','https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80','Kiểm tra tem, đường may, hộp, mã sản phẩm và nơi bán giúp bạn tránh mua nhầm hàng kém chất lượng.','Giày chính hãng thường có tem rõ nét, mã sản phẩm đồng nhất với hộp, keo dán sạch và đường may đều. Người mua nên ưu tiên cửa hàng có hóa đơn, chính sách đổi trả và thông tin tồn kho minh bạch.','ShoeStore Team','Chính hãng, Mẹo mua hàng'],
            ['Cách bảo quản sneaker trắng','cach-bao-quan-sneaker-trang','https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=900&q=80','Sneaker trắng cần được vệ sinh đúng cách để giữ màu và tránh ố vàng sau thời gian dài sử dụng.','Không nên ngâm giày trắng quá lâu hoặc phơi trực tiếp dưới nắng gắt. Hãy dùng khăn microfiber, bàn chải mềm và dung dịch vệ sinh nhẹ; sau đó nhét giấy khô vào trong giày để giữ form.','ShoeCare Lab','Bảo quản, Sneaker trắng'],
            ['Nên chọn Nike, Adidas hay New Balance?','nen-chon-nike-adidas-hay-new-balance','https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=900&q=80','Mỗi thương hiệu có thế mạnh riêng: hiệu năng, phong cách, độ êm và form chân khác nhau.','Nike thường mạnh về thiết kế thể thao và độ phản hồi; Adidas nổi bật với sự linh hoạt trong lifestyle; New Balance được đánh giá cao ở độ thoải mái và form rộng. Lựa chọn tốt nhất phụ thuộc vào mục đích sử dụng và dáng chân.','ShoeStore Editorial','So sánh, Thương hiệu'],
            ['Cách chọn size giày online chuẩn','cach-chon-size-giay-online-chuan','https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=900&q=80','Đo chiều dài chân, đọc review form giày và kiểm tra chính sách đổi size là ba bước quan trọng.','Hãy đặt chân lên giấy, đo từ gót đến ngón dài nhất và cộng thêm khoảng 0,5 cm cho độ thoải mái. Nếu chân bè, nên chọn mẫu có form rộng hoặc tăng nửa size khi thương hiệu cho phép.','ShoeStore Team','Size guide, Online shopping'],
        ];
        $stmt = db()->prepare('INSERT IGNORE INTO news(title,slug,thumbnail,excerpt,content,author,tags,status,created_at) VALUES(?,?,?,?,?,?,?,"published",NOW())');
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }
    $done = true;
}

function ensure_size_schema(): void
{
    static $done = false;
    if ($done) return;
    try { db()->exec("ALTER TABLE products MODIFY status ENUM('active','inactive','deleted') NOT NULL DEFAULT 'active'"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE inventory_logs ADD COLUMN size VARCHAR(20) NULL AFTER product_id"); } catch (Throwable $e) {}
    db()->exec("CREATE TABLE IF NOT EXISTS product_sizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        size VARCHAR(20) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY product_size_unique(product_id,size),
        FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    try { db()->exec('ALTER TABLE order_items ADD COLUMN size VARCHAR(20) NULL AFTER product_name'); } catch (Throwable $e) {}
    $count = (int)db()->query('SELECT COUNT(*) FROM product_sizes')->fetchColumn();
    if ($count === 0) {
        $products = db()->query("SELECT p.id,p.size_range,COALESCE(i.stock,0) stock FROM products p LEFT JOIN inventory i ON i.product_id=p.id")->fetchAll();
        $stmt = db()->prepare('INSERT IGNORE INTO product_sizes(product_id,size,stock) VALUES(?,?,?)');
        foreach ($products as $p) {
            $range = trim((string)($p['size_range'] ?: '38-43'));
            $sizes = [];
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $range, $m)) {
                for ($s = (int)$m[1]; $s <= (int)$m[2]; $s++) $sizes[] = (string)$s;
            } else {
                $sizes = array_values(array_filter(array_map('trim', preg_split('/[,; ]+/', $range))));
            }
            if (!$sizes) $sizes = ['38','39','40','41','42','43'];
            $perSize = max(0, (int)floor(((int)$p['stock']) / max(1, count($sizes))));
            foreach ($sizes as $size) $stmt->execute([(int)$p['id'], $size, $perSize]);
        }
    }
    $done = true;
}

function parse_size_stock_lines(string $text): array
{
    $rows = [];
    foreach (preg_split('/\R+/', trim($text)) as $line) {
        if (preg_match('/^\s*([A-Za-z0-9.\/-]+)\s*[:=,\s]\s*(\d+)\s*$/', $line, $m)) {
            $rows[$m[1]] = (int)$m[2];
        }
    }
    return $rows;
}

function sync_product_total_stock(int $productId): void
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(stock),0) FROM product_sizes WHERE product_id=?');
    $stmt->execute([$productId]);
    db()->prepare('INSERT INTO inventory(product_id,stock) VALUES(?,?) ON DUPLICATE KEY UPDATE stock=VALUES(stock)')->execute([$productId, (int)$stmt->fetchColumn()]);
}

function decrement_product_size_stock(int $productId, string $size, int $quantity): void
{
    $stmt = db()->prepare('SELECT stock FROM product_sizes WHERE product_id=? AND size=? FOR UPDATE');
    $stmt->execute([$productId, $size]);
    $stock = $stmt->fetchColumn();
    if ($stock === false || (int)$stock < $quantity) {
        throw new RuntimeException('Size đã chọn không đủ tồn kho.');
    }
    db()->prepare('UPDATE product_sizes SET stock=stock-? WHERE product_id=? AND size=?')->execute([$quantity, $productId, $size]);
    sync_product_total_stock($productId);
}

function increment_product_size_stock(int $productId, ?string $size, int $quantity): void
{
    if ($size !== null && $size !== '') {
        db()->prepare('INSERT INTO product_sizes(product_id,size,stock) VALUES(?,?,?) ON DUPLICATE KEY UPDATE stock=stock+VALUES(stock)')->execute([$productId, $size, $quantity]);
        sync_product_total_stock($productId);
    }
}

function return_type_label(string $type): string
{
    return [
        'refund' => 'Hoàn tiền',
        'exchange' => 'Đổi hàng',
        'return' => 'Trả hàng',
    ][$type] ?? $type;
}

function return_status_label(string $status): string
{
    return [
        'pending' => 'Đang chờ admin duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'received' => 'Đã nhận hàng',
        'refunded' => 'Đã hoàn tiền',
    ][$status] ?? $status;
}

function restore_order_stock(int $orderId, ?int $userId = null): void
{
    $stmt = db()->prepare('SELECT id, stock_restored_at FROM orders WHERE id=? FOR UPDATE');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || $order['stock_restored_at']) {
        return;
    }
    $items = db()->prepare('SELECT product_id, size, quantity FROM order_items WHERE order_id=?');
    $items->execute([$orderId]);
    $update = db()->prepare('UPDATE inventory SET stock=stock+? WHERE product_id=?');
    $log = db()->prepare("INSERT INTO inventory_logs(product_id,user_id,type,quantity,note) VALUES(?,?,?,?,?)");
    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        $pid = (int)$item['product_id'];
        $update->execute([$qty, $pid]);
        increment_product_size_stock($pid, $item['size'] ?? null, $qty);
        $log->execute([$pid, $userId, 'adjust', $qty, 'Hoàn kho do hủy đơn #' . $orderId]);
    }
    db()->prepare('UPDATE orders SET stock_restored_at=NOW() WHERE id=?')->execute([$orderId]);
}

function ticket_status_label(string $status): string
{
    return [
        'open' => 'Đang mở',
        'pending' => 'Đang chờ xử lý',
        'answered' => 'Đã phản hồi',
        'closed' => 'Đã đóng',
    ][$status] ?? $status;
}

function upload_file(array $file, string $dir, array $allowed): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Upload khong hop le hoac vuot qua 5MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Định dạng tệp không được hỗ trợ.');
    }
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        default => 'bin',
    };
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetDir = __DIR__ . '/../' . trim($dir, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    move_uploaded_file($file['tmp_name'], $targetDir . '/' . $name);
    return trim($dir, '/') . '/' . $name;
}

function audit_log(string $action, string $entity, ?int $entityId = null, array $data = []): void
{
    $uid = $_SESSION['user_id'] ?? null;
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity, entity_id, data, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$uid, $action, $entity, $entityId, json_encode($data, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
}

function create_notification(int $userId, string $title, string $body, string $type = 'system', ?string $link = null): void
{
    ensure_support_schema();
    if ($link !== null && $link !== '' && $link !== '#') {
        $link = normalize_notification_link($link);
    }
    $stmt = db()->prepare('INSERT INTO notifications(user_id,type,title,message,body,link,created_at) VALUES(?,?,?,?,?,?,NOW())');
    $stmt->execute([$userId, $type, $title, $body, $body, $link]);
}

function notify_admins(string $title, string $body, string $type = 'system', ?string $link = null): void
{
    ensure_support_schema();
    $stmt = db()->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name IN ('Super Admin','Admin','Staff')");
    foreach ($stmt as $admin) {
        create_notification((int)$admin['id'], $title, $body, $type, $link);
    }
}

function ensure_review_schema(): void
{
    static $done = false;
    if ($done) return;
    foreach (['uploads/reviews/images', 'uploads/reviews/videos'] as $dir) {
        $path = __DIR__ . '/../' . $dir;
        if (!is_dir($path)) mkdir($path, 0775, true);
    }
    try { db()->exec('ALTER TABLE reviews ADD COLUMN order_id INT NULL AFTER product_id'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE reviews ADD COLUMN image VARCHAR(255) NULL AFTER comment'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE reviews MODIFY approved TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) {}
    try { db()->exec("CREATE TABLE IF NOT EXISTS review_media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type ENUM('image','video') NOT NULL,
        mime_type VARCHAR(120) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(review_id) REFERENCES reviews(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
    try { db()->exec('UPDATE reviews SET approved=1 WHERE approved=0'); } catch (Throwable $e) {}
    $done = true;
}

function upload_review_media_files(array $files): array
{
    $uploaded = [];
    $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
    if ($count === 0) return [];
    $imageMimes = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $videoMimes = ['video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Chỉ được tải lên hình ảnh hoặc video hợp lệ.');
        }
        $tmp = $files['tmp_name'][$i];
        $mime = $finfo->file($tmp);
        $ext = strtolower(pathinfo((string)$files['name'][$i], PATHINFO_EXTENSION));
        $isImage = isset($imageMimes[$mime]) && in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
        $isVideo = isset($videoMimes[$mime]) && in_array($ext, ['mp4','webm','mov'], true);
        if (!$isImage && !$isVideo) {
            throw new RuntimeException('Chỉ được tải lên hình ảnh hoặc video hợp lệ.');
        }
        $max = $isImage ? 5 * 1024 * 1024 : 50 * 1024 * 1024;
        if (($files['size'][$i] ?? 0) > $max) {
            throw new RuntimeException('Chỉ được tải lên hình ảnh hoặc video hợp lệ.');
        }
        $type = $isImage ? 'image' : 'video';
        $dir = 'uploads/reviews/' . ($isImage ? 'images' : 'videos');
        $targetDir = __DIR__ . '/../' . $dir;
        if (!is_dir($targetDir)) mkdir($targetDir, 0775, true);
        $name = bin2hex(random_bytes(18)) . '.' . ($isImage ? $imageMimes[$mime] : $videoMimes[$mime]);
        if (!move_uploaded_file($tmp, $targetDir . '/' . $name)) {
            throw new RuntimeException('Chỉ được tải lên hình ảnh hoặc video hợp lệ.');
        }
        $uploaded[] = ['path' => $dir . '/' . $name, 'type' => $type, 'mime' => $mime];
    }
    return $uploaded;
}

function rate_limit(string $key, int $limit, int $seconds): void
{
    $bucket = 'rate_' . $key;
    $now = time();
    $_SESSION[$bucket] = array_filter($_SESSION[$bucket] ?? [], fn ($ts) => $ts > $now - $seconds);
    if (count($_SESSION[$bucket]) >= $limit) {
        http_response_code(429);
        exit('Too many requests');
    }
    $_SESSION[$bucket][] = $now;
}
