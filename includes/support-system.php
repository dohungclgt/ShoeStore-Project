<?php
declare(strict_types=1);

function ensure_support_schema(): void
{
    static $done = false;
    if ($done) return;
    $pdo = db();

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS policies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        slug VARCHAR(200) NOT NULL UNIQUE,
        content MEDIUMTEXT NOT NULL,
        status ENUM('active','hidden') NOT NULL DEFAULT 'active',
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT KEY policy_search(title, content)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
    foreach ([
        "ALTER TABLE policies ADD COLUMN slug VARCHAR(200) NULL AFTER title",
        "ALTER TABLE policies ADD COLUMN status ENUM('active','hidden') NOT NULL DEFAULT 'active' AFTER content",
        "ALTER TABLE policies ADD COLUMN excerpt TEXT NULL AFTER slug",
        "ALTER TABLE policies ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE policies ADD COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP",
        "ALTER TABLE policies ADD UNIQUE KEY policies_slug_unique(slug)",
        "ALTER TABLE policies MODIFY content MEDIUMTEXT NOT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) {} }
    try { $pdo->exec("UPDATE policies SET slug=LOWER(REPLACE(REPLACE(REPLACE(title,' ','-'),'Đ','d'),'đ','d')) WHERE slug IS NULL OR slug=''"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE policies SET status=IF(COALESCE(active,1)=1,'active','hidden')"); } catch (Throwable $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        assigned_to INT NULL,
        subject VARCHAR(180) NOT NULL,
        topic VARCHAR(80) NULL,
        status ENUM('open','pending','answered','closed') NOT NULL DEFAULT 'open',
        attachment VARCHAR(255) NULL,
        user_unread INT NOT NULL DEFAULT 0,
        admin_unread INT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
    foreach ([
        "ALTER TABLE tickets MODIFY status ENUM('open','pending','answered','closed') NOT NULL DEFAULT 'open'",
        "ALTER TABLE tickets ADD COLUMN assigned_to INT NULL AFTER user_id",
        "ALTER TABLE tickets ADD COLUMN topic VARCHAR(80) NULL AFTER subject",
        "ALTER TABLE tickets ADD COLUMN user_unread INT NOT NULL DEFAULT 0",
        "ALTER TABLE tickets ADD COLUMN admin_unread INT NOT NULL DEFAULT 1",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) {} }

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        attachment VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        message_id INT NULL,
        user_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(180) NOT NULL,
        mime_type VARCHAR(120) NOT NULL,
        file_size INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        FOREIGN KEY(message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        role_target VARCHAR(50) NULL,
        type ENUM('order','payment','ticket','return','review','system') NOT NULL DEFAULT 'system',
        title VARCHAR(180) NOT NULL,
        message TEXT NULL,
        body TEXT NULL,
        link VARCHAR(255) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id,is_read),
        INDEX(role_target,is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
    foreach ([
        "ALTER TABLE notifications MODIFY user_id INT NULL",
        "ALTER TABLE notifications ADD COLUMN role_target VARCHAR(50) NULL AFTER user_id",
        "ALTER TABLE notifications ADD COLUMN type ENUM('order','payment','ticket','return','review','system') NOT NULL DEFAULT 'system' AFTER role_target",
        "ALTER TABLE notifications MODIFY type ENUM('order','payment','ticket','return','review','policy','system') NOT NULL DEFAULT 'system'",
        "ALTER TABLE notifications ADD COLUMN message TEXT NULL AFTER title",
        "ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER body",
        "UPDATE notifications SET message=body WHERE message IS NULL AND body IS NOT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) {} }

    seed_default_policies();
    $done = true;
}

function slugify_vi(string $text): string
{
    $map = ['à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a','è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e','ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i','ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o','ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u','ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d'];
    $text = strtr(mb_strtolower($text, 'UTF-8'), $map);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
}

function seed_default_policies(): void
{
    $rows = [
        ['Chính sách mua hàng','Khách hàng có thể đặt hàng trực tiếp trên website ShoeStore bằng cách chọn sản phẩm, chọn size, thêm vào giỏ hàng và hoàn tất thông tin nhận hàng. Giá bán, tồn kho và khuyến mãi được hiển thị tại thời điểm đặt hàng. Sau khi đặt hàng thành công, hệ thống tạo mã đơn để khách theo dõi trạng thái. ShoeStore có quyền liên hệ xác nhận lại nếu thông tin nhận hàng chưa đầy đủ hoặc sản phẩm vừa hết hàng.'],
        ['Chính sách thanh toán','ShoeStore hỗ trợ thanh toán COD và VNPay Sandbox. Với thanh toán online, đơn hàng chỉ chuyển sang bước xử lý sau khi cổng VNPay trả kết quả thành công. Nếu giao dịch thất bại, khách có thể thanh toán lại hoặc chọn COD. ShoeStore không lưu thông tin thẻ hoặc tài khoản ngân hàng của khách hàng.'],
        ['Chính sách giao hàng','Đơn hàng được xử lý sau khi xác nhận thanh toán hoặc xác nhận COD. Thời gian giao hàng thường từ 2 đến 5 ngày làm việc tùy khu vực nhận hàng và đơn vị vận chuyển. Khách hàng có thể theo dõi trạng thái trong trang Đơn hàng. Khi phát sinh chậm trễ, bộ phận chăm sóc khách hàng sẽ hỗ trợ kiểm tra vận đơn.'],
        ['Chính sách hoàn tiền','ShoeStore hỗ trợ hoàn tiền khi đơn hàng bị hủy hợp lệ, sản phẩm lỗi do nhà sản xuất, giao sai mẫu hoặc yêu cầu trả hàng được duyệt. Khoản hoàn tiền được xử lý về phương thức thanh toán ban đầu khi điều kiện cổng thanh toán cho phép, hoặc theo thỏa thuận với khách hàng. Thời gian xử lý thường từ 3 đến 7 ngày làm việc sau khi yêu cầu được duyệt.'],
        ['Chính sách đổi hàng','Khách hàng có thể yêu cầu đổi hàng nếu sản phẩm còn nguyên tem, chưa qua sử dụng và yêu cầu được tạo trong thời hạn hỗ trợ đổi trả. ShoeStore ưu tiên đổi size hoặc đổi mẫu còn hàng có giá trị tương đương. Nếu sản phẩm đổi có giá cao hơn, khách thanh toán phần chênh lệch; nếu thấp hơn, ShoeStore sẽ tư vấn phương án hoàn phần chênh lệch phù hợp.'],
        ['Chính sách trả hàng','Yêu cầu trả hàng được xem xét khi sản phẩm lỗi, giao sai, không đúng mô tả hoặc đáp ứng điều kiện trả hàng của cửa hàng. Khách cần cung cấp lý do và hình ảnh minh chứng nếu có. Sản phẩm trả về cần được đóng gói cẩn thận, giữ phụ kiện đi kèm và không phát sinh hư hỏng do sử dụng sai cách.'],
        ['Chính sách bảo hành','ShoeStore hỗ trợ bảo hành với lỗi kỹ thuật hoặc lỗi sản xuất được xác minh. Bảo hành không áp dụng cho hao mòn tự nhiên, hư hỏng do sử dụng sai mục đích, tự sửa chữa hoặc bảo quản không đúng hướng dẫn. Khi cần bảo hành, khách tạo ticket kèm hình ảnh và mô tả tình trạng để nhân viên kiểm tra.'],
        ['Chính sách bảo mật thông tin','Thông tin cá nhân của khách hàng chỉ dùng cho mục đích xử lý đơn hàng, giao hàng, chăm sóc khách hàng và cải thiện dịch vụ. ShoeStore không bán hoặc chia sẻ thông tin cá nhân cho bên thứ ba ngoài các đối tác cần thiết để hoàn tất đơn hàng như đơn vị vận chuyển hoặc cổng thanh toán.'],
        ['Chính sách kiểm tra hàng','Khách hàng được kiểm tra ngoại quan sản phẩm khi nhận hàng, bao gồm mẫu mã, size, số lượng và tình trạng đóng gói. Việc kiểm tra không bao gồm sử dụng thử trong thời gian dài hoặc làm ảnh hưởng tem, phụ kiện và bao bì. Nếu phát hiện bất thường, khách nên chụp ảnh và liên hệ ShoeStore ngay để được hỗ trợ.'],
        ['Chính sách hủy đơn hàng','Khách hàng có thể hủy đơn khi đơn còn ở trạng thái chờ thanh toán, chờ xác nhận hoặc chờ lấy hàng. Khi đơn đã đóng gói, đang giao, đã giao, hoàn thành hoặc đã hoàn trả, thao tác hủy sẽ không được hỗ trợ trực tiếp và khách cần tạo ticket để nhân viên kiểm tra phương án phù hợp.'],
        ['Chính sách COD','Với COD, khách thanh toán tiền mặt cho đơn vị giao hàng khi nhận sản phẩm. ShoeStore có thể gọi xác nhận trước khi xử lý đơn COD nhằm hạn chế sai thông tin nhận hàng. Nếu khách từ chối nhận hàng nhiều lần không có lý do hợp lệ, cửa hàng có thể giới hạn phương thức COD cho các đơn tiếp theo.'],
        ['Chính sách VNPay','VNPay cho phép thanh toán qua ngân hàng, thẻ nội địa hoặc các phương thức được VNPay hỗ trợ. Sau khi thanh toán thành công, đơn hàng được cập nhật trạng thái tự động. Nếu tiền đã trừ nhưng đơn chưa cập nhật, khách vui lòng tạo ticket kèm mã giao dịch để ShoeStore kiểm tra với cổng thanh toán.'],
    ];
    $extra = [
        ['Chính sách xử lý khiếu nại','ShoeStore tiếp nhận khiếu nại qua ticket hỗ trợ, email hoặc thông tin liên hệ công bố trên website. Khách hàng cần cung cấp mã đơn, mô tả vấn đề, hình ảnh hoặc video minh chứng nếu có. Điều kiện áp dụng: khiếu nại liên quan đến sản phẩm, giao hàng, thanh toán, đổi trả hoặc trải nghiệm dịch vụ tại ShoeStore. Thời gian phản hồi ban đầu thường trong 24 giờ làm việc; các trường hợp cần đối soát với vận chuyển hoặc cổng thanh toán có thể mất 3 đến 7 ngày làm việc. Lưu ý quan trọng: ShoeStore ưu tiên giải quyết trên dữ liệu đơn hàng, lịch sử trao đổi và chứng từ hợp lệ để bảo đảm quyền lợi khách hàng.'],
        ['Chính sách hỗ trợ khách hàng','Bộ phận chăm sóc khách hàng hỗ trợ tư vấn sản phẩm, chọn size, kiểm tra đơn hàng, thanh toán, bảo hành và đổi trả. Khách có thể tạo ticket trong tài khoản để theo dõi toàn bộ lịch sử hỗ trợ. Điều kiện áp dụng: yêu cầu hỗ trợ cần có thông tin liên hệ chính xác và nội dung mô tả rõ vấn đề. Thời gian xử lý thông thường là trong giờ làm việc, các yêu cầu khẩn cấp liên quan đến thanh toán hoặc giao hàng được ưu tiên. Lưu ý quan trọng: không cung cấp mật khẩu, mã OTP hoặc thông tin thẻ cho bất kỳ ai khi yêu cầu hỗ trợ.'],
    ];
    $rows = array_merge($rows, $extra);
    $stmt = db()->prepare("INSERT INTO policies(title,slug,excerpt,content,status,active,created_at) VALUES(?,?,?,?,?,1,NOW())
        ON DUPLICATE KEY UPDATE title=VALUES(title), excerpt=VALUES(excerpt), content=VALUES(content), status='active', active=1, updated_at=NOW()");
    foreach ($rows as [$title, $content]) {
        $detail = $content . "\n\nĐiều kiện áp dụng:\n- Áp dụng cho đơn hàng và tài khoản phát sinh trên ShoeStore.\n- Thông tin xử lý dựa trên trạng thái đơn hàng, chứng từ thanh toán, tồn kho và lịch sử hỗ trợ hợp lệ.\n\nThời gian xử lý:\n- Yêu cầu thông thường được phản hồi trong 24 giờ làm việc.\n- Trường hợp cần đối soát với vận chuyển, kho hoặc cổng thanh toán có thể cần 3-7 ngày làm việc.\n\nLưu ý quan trọng:\n- Khách hàng nên giữ mã đơn hàng, hình ảnh sản phẩm và chứng từ thanh toán để được hỗ trợ nhanh hơn.\n- ShoeStore có thể từ chối yêu cầu nếu thông tin không hợp lệ hoặc sản phẩm không đáp ứng điều kiện chính sách.";
        $stmt->execute([$title, slugify_vi($title), mb_strimwidth($content, 0, 180, '...', 'UTF-8'), $detail, 'active']);
    }
    try { db()->exec("UPDATE policies SET excerpt=LEFT(content, 180) WHERE excerpt IS NULL OR excerpt=''"); } catch (Throwable $e) {}
    try { db()->prepare("UPDATE policies SET status='hidden',active=0 WHERE slug=?")->execute(['chinh-sach-momo']); } catch (Throwable $e) {}
}

function openrouter_log(string $message, array $context = []): void
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    unset($context['api_key'], $context['Authorization']);
    file_put_contents($dir . '/openrouter_error.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function openrouter_chat(array $messages, array $options = []): array
{
    $cfg = require __DIR__ . '/../config/openrouter.php';
    $apiKey = trim((string)($cfg['api_key'] ?? ''));
    if ($apiKey === '') return ['ok' => false, 'message' => 'OpenRouter API key chưa được cấu hình.', 'status' => 0, 'content' => null];
    $models = array_values(array_unique(array_filter([$cfg['model'] ?? 'deepseek/deepseek-chat-v3', $cfg['fallback_model'] ?? 'deepseek/deepseek-chat'])));
    foreach ($models as $model) {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.35,
            'max_tokens' => $options['max_tokens'] ?? 700,
        ];
        $ch = curl_init((string)$cfg['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . ($cfg['site_url'] ?? BASE_URL),
                'X-Title: ' . ($cfg['app_name'] ?? 'ShoeStore AI'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = json_decode((string)$raw, true);
        $content = trim((string)($data['choices'][0]['message']['content'] ?? ''));
        if ($status >= 200 && $status < 300 && $content !== '') {
            return ['ok' => true, 'message' => 'OK', 'status' => $status, 'model' => $model, 'content' => $content];
        }
        openrouter_log('OpenRouter request failed', [
            'model' => $model,
            'http_status' => $status,
            'curl_error' => $err,
            'response_body' => mb_substr((string)$raw, 0, 2000, 'UTF-8'),
        ]);
    }
    return ['ok' => false, 'message' => 'Xin lỗi, trợ lý AI đang tạm thời gặp sự cố. Vui lòng thử lại sau.', 'status' => $status ?? 0, 'content' => null];
}

function find_relevant_policies(string $question, int $limit = 4): array
{
    $norm = slugify_vi($question);
    $direct = [
        'hoan-tien' => ['chinh-sach-hoan-tien'],
        'doi-tra' => ['chinh-sach-doi-hang','chinh-sach-tra-hang'],
        'doi-hang' => ['chinh-sach-doi-hang'],
        'tra-hang' => ['chinh-sach-tra-hang'],
        'bao-hanh' => ['chinh-sach-bao-hanh'],
        'mua-hang' => ['chinh-sach-mua-hang'],
        'giao-hang' => ['chinh-sach-giao-hang'],
        'thanh-toan' => ['chinh-sach-thanh-toan'],
        'cod' => ['chinh-sach-cod'],
        'vnpay' => ['chinh-sach-vnpay'],
        'huy-don' => ['chinh-sach-huy-don-hang'],
        'kiem-tra-hang' => ['chinh-sach-kiem-tra-hang'],
        'bao-mat' => ['chinh-sach-bao-mat-thong-tin'],
    ];
    foreach ($direct as $needle => $slugs) {
        if (str_contains($norm, $needle)) {
            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $stmt = db()->prepare("SELECT id,title,slug,content FROM policies WHERE (status='active' OR active=1) AND slug IN ($placeholders) ORDER BY FIELD(slug,$placeholders) LIMIT " . (int)$limit);
            $stmt->execute(array_merge($slugs, $slugs));
            $rows = $stmt->fetchAll();
            if ($rows) return $rows;
        }
    }
    $terms = array_values(array_filter(explode('-', $norm), fn($w) => mb_strlen($w) >= 3));
    $where = ["(status='active' OR active=1)"];
    $params = [];
    foreach (array_slice($terms, 0, 5) as $term) {
        $where[] = '(LOWER(title) LIKE ? OR LOWER(slug) LIKE ? OR LOWER(content) LIKE ?)';
        array_push($params, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%');
    }
    if (count($where) === 1) {
        $where[] = '1=1';
    }
    $sql = 'SELECT id,title,slug,content FROM policies WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC, id DESC LIMIT ' . (int)$limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if ($rows) return $rows;
    $like = '%' . preg_replace('/\s+/', '%', $question) . '%';
    $stmt = db()->prepare("SELECT id,title,slug,content FROM policies WHERE (status='active' OR active=1) AND (title LIKE ? OR content LIKE ?) ORDER BY id DESC LIMIT " . (int)$limit);
    $stmt->execute([$like, $like]);
    return $stmt->fetchAll();
}

function upload_ticket_attachment(array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 50 * 1024 * 1024) {
        throw new RuntimeException('Tệp đính kèm không hợp lệ hoặc vượt quá 50MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',
        'video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov',
        'application/pdf'=>'pdf','text/plain'=>'txt',
    ];
    if (!isset($allowed[$mime])) throw new RuntimeException('Định dạng tệp không được hỗ trợ.');
    $dir = 'uploads/tickets';
    $targetDir = __DIR__ . '/../' . $dir;
    if (!is_dir($targetDir)) mkdir($targetDir, 0775, true);
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$file['name']);
    $name = bin2hex(random_bytes(14)) . '-' . trim($safeName, '-');
    if ($name === '') $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $name)) {
        throw new RuntimeException('Không lưu được tệp đính kèm.');
    }
    return ['path' => $dir . '/' . $name, 'name' => (string)$file['name'], 'mime' => $mime, 'size' => (int)$file['size']];
}

function notification_icon(string $type): string
{
    return [
        'order' => 'fa-box',
        'payment' => 'fa-credit-card',
        'ticket' => 'fa-headset',
        'return' => 'fa-rotate-left',
        'review' => 'fa-star',
        'policy' => 'fa-file-contract',
        'system' => 'fa-circle-info',
    ][$type] ?? 'fa-circle-info';
}

function ensure_commerce_schema(): void
{
    static $done = false;
    if ($done) return;
    try { db()->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(80) NULL AFTER discount"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE payments MODIFY provider ENUM('','COD','VNPAY','MOMO','MOCK') NOT NULL"); } catch (Throwable $e) {}
    try { db()->exec("UPDATE payments p JOIN orders o ON o.id=p.order_id SET p.provider=o.payment_method WHERE p.provider='' AND o.payment_method IN ('COD','VNPAY','MOMO','MOCK')"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE payments MODIFY provider ENUM('COD','VNPAY','MOMO','MOCK') NOT NULL"); } catch (Throwable $e) {}
    $done = true;
}

function coupon_lookup(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $stmt = db()->prepare('SELECT * FROM coupons WHERE code=? LIMIT 1');
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

function coupon_validate(array $coupon, float $subtotal, array $productIds = []): array
{
    if (!(int)$coupon['active']) return ['valid' => false, 'message' => 'Mã giảm giá không còn hiệu lực và đã được gỡ khỏi giỏ hàng.'];
    if (($coupon['starts_at'] && strtotime($coupon['starts_at']) > time()) || ($coupon['ends_at'] && strtotime($coupon['ends_at']) < time())) {
        return ['valid' => false, 'message' => 'Mã giảm giá không còn hiệu lực và đã được gỡ khỏi giỏ hàng.'];
    }
    if ($coupon['usage_limit'] !== null) {
        $usedStmt = db()->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id=?');
        $usedStmt->execute([$coupon['id']]);
        if ((int)$usedStmt->fetchColumn() >= (int)$coupon['usage_limit']) return ['valid' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng.'];
    }
    if ($subtotal < (float)$coupon['min_order']) return ['valid' => false, 'message' => 'Đơn hàng chưa đạt giá trị tối thiểu ' . money($coupon['min_order']) . '.'];
    $scopeProducts = db()->prepare('SELECT product_id FROM coupon_products WHERE coupon_id=?');
    $scopeProducts->execute([$coupon['id']]);
    $allowedProducts = array_map('intval', array_column($scopeProducts->fetchAll(), 'product_id'));
    $scopeCategories = db()->prepare('SELECT category_id FROM coupon_categories WHERE coupon_id=?');
    $scopeCategories->execute([$coupon['id']]);
    $allowedCategories = array_map('intval', array_column($scopeCategories->fetchAll(), 'category_id'));
    if ($productIds && ($allowedProducts || $allowedCategories)) {
        $ph = implode(',', array_fill(0, count($productIds), '?'));
        $pstmt = db()->prepare("SELECT id,category_id FROM products WHERE id IN ($ph)");
        $pstmt->execute($productIds);
        $matched = false;
        foreach ($pstmt as $p) {
            if (in_array((int)$p['id'], $allowedProducts, true) || in_array((int)$p['category_id'], $allowedCategories, true)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) return ['valid' => false, 'message' => 'Coupon không áp dụng cho sản phẩm đã chọn.'];
    }
    return ['valid' => true, 'message' => 'Đã áp dụng coupon ' . $coupon['code'] . '.'];
}

function calculate_coupon_totals(float $subtotal, ?string $couponCode, array $productIds = [], float $shipping = 30000): array
{
    $subtotal = max(0, $subtotal);
    $discount = 0.0;
    $coupon = null;
    $couponCode = strtoupper(trim((string)$couponCode));
    $couponMessage = '';
    if ($couponCode !== '') {
        $coupon = coupon_lookup($couponCode);
        if ($coupon) {
            $check = coupon_validate($coupon, $subtotal, $productIds);
            if ($check['valid']) {
                if ($coupon['type'] === 'percent') $discount = $subtotal * ((float)$coupon['value'] / 100);
                elseif ($coupon['type'] === 'fixed') $discount = (float)$coupon['value'];
                elseif ($coupon['type'] === 'free_shipping') $shipping = 0;
                $couponMessage = $check['message'];
            } else {
                $coupon = null;
                $couponCode = '';
                $couponMessage = $check['message'];
            }
        } else {
            $couponCode = '';
            $couponMessage = 'Mã giảm giá không còn hiệu lực và đã được gỡ khỏi giỏ hàng.';
        }
    }
    $discount = min($subtotal, max(0, $discount));
    $afterDiscount = max(0, $subtotal - $discount);
    $vatRate = defined('VAT_RATE') ? (float)VAT_RATE : 0.05;
    $vat = $afterDiscount * $vatRate;
    $total = $afterDiscount + $shipping + $vat;
    return [
        'subtotal' => $subtotal,
        'coupon' => $coupon,
        'coupon_code' => $couponCode,
        'coupon_message' => $couponMessage,
        'discount' => $discount,
        'shipping' => $shipping,
        'after_discount' => $afterDiscount,
        'vat' => $vat,
        'total' => $total,
    ];
}

function remember_cart_coupon(?string $code): void
{
    $code = strtoupper(trim((string)$code));
    if ($code === '') unset($_SESSION['cart_coupon_code']);
    else $_SESSION['cart_coupon_code'] = $code;
}

function notification_detail_link(string $type, int $entityId, bool $admin = false): string
{
    $path = match ($type) {
        'order' => $admin ? 'admin/orders/index.php?order_id=' . $entityId : 'user/order-detail.php?order_id=' . $entityId,
        'ticket' => $admin ? 'admin/support/show.php?id=' . $entityId : 'user/tickets/show.php?id=' . $entityId,
        'return' => $admin ? 'admin/returns/index.php?id=' . $entityId : 'user/orders.php?return_id=' . $entityId,
        'payment' => $admin ? 'admin/payments/index.php?payment_id=' . $entityId : 'user/orders.php?payment_id=' . $entityId,
        'review' => 'product.php?review_id=' . $entityId,
        'policy' => 'policies.php',
        default => $admin ? 'admin/notifications.php' : 'user/notifications.php',
    };
    return app_url($path);
}

function normalize_notification_link(?string $link, bool $admin = false): string
{
    $fallback = $admin ? 'admin/notifications.php' : 'user/notifications.php';
    $link = trim((string)$link);
    if ($link === '' || $link === '#') {
        return app_url($fallback);
    }

    if (preg_match('#^https?://#i', $link)) {
        $parts = parse_url($link);
        $path = (string)($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $configuredPath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';

        if ($configuredPath !== '' && str_starts_with($path, rtrim($configuredPath, '/') . '/')) {
            $path = substr($path, strlen(rtrim($configuredPath, '/')));
        }
        $path = preg_replace('#^/shoestore/#', '', $path);
        $path = ltrim($path, '/');
        return app_url(($path !== '' ? $path : $fallback) . $query);
    }

    return app_url($link);
}

function notification_resolve_link(array $n, bool $admin = false): string
{
    $link = trim((string)($n['link'] ?? ''));
    if ($link !== '' && $link !== '#') return normalize_notification_link($link, $admin);
    $type = (string)($n['type'] ?? 'system');
    $id = (int)($n['entity_id'] ?? $n['id'] ?? 0);
    return notification_detail_link($type, $id, $admin);
}

function normalize_review_media_path(?string $path): ?string
{
    $path = trim(str_replace('\\', '/', (string)$path));
    if ($path === '') return null;
    $needle = 'uploads/reviews/';
    $pos = stripos($path, $needle);
    if ($pos !== false) $path = substr($path, $pos);
    $path = ltrim($path, '/');
    return str_starts_with($path, 'uploads/reviews/') ? $path : null;
}

function review_media_url(?string $path): string
{
    $path = normalize_review_media_path($path);
    if (!$path) return app_url('assets/img/review-placeholder.svg');
    $full = __DIR__ . '/../' . $path;
    return is_file($full) ? app_url($path) : app_url('assets/img/review-placeholder.svg');
}
