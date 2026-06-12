<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method not allowed'],405);
verify_csrf();
$user = current_user();
if (!$user) json_response(['error'=>'Vui lòng đăng nhập để dùng chatbot.'],401);
rate_limit('chatbot_'.$user['id'], 20, 300);

$question = trim((string)($_POST['message'] ?? ''));
if ($question === '') json_response(['error'=>'Câu hỏi đang trống.'],422);
ensure_support_schema();

function chatbot_norm(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $map = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
    ];
    $text = strtr($text, $map);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $converted ?: $text)));
}

function chatbot_clean_reply(string $reply): string {
    $forbidden = [
        'database','cơ sở dữ liệu','co so du lieu','knowledge base','dữ liệu nội bộ',
        'du lieu noi bo','hệ thống lưu trữ','he thong luu tru','dữ liệu của chúng tôi',
        'du lieu cua chung toi','database_context'
    ];
    foreach ($forbidden as $word) {
        $reply = preg_replace('/'.preg_quote($word, '/').'/iu', 'thông tin hiện có', $reply);
    }
    return trim($reply);
}

function chatbot_product_intent(string $norm): bool {
    foreach (['giay','sneaker','nike','adidas','new balance','puma','converse','vans','chay bo','nam','nu','duoi','trieu','mau den','den','basketball','bong ro','lifestyle'] as $kw) {
        if (str_contains($norm, $kw)) return true;
    }
    return false;
}

function chatbot_support_intent(string $norm): bool {
    foreach (['chinh sach','bao hanh','hoan tien','doi tra','tra hang','giao hang','nhan duoc hang','nhan hang','van chuyen','kich thuoc','bang size','size giay','chon size'] as $kw) {
        if (str_contains($norm, $kw)) return true;
    }
    return false;
}

function chatbot_support_fallback(string $norm): string {
    if (str_contains($norm, 'kich thuoc') || str_contains($norm, 'bang size') || str_contains($norm, 'size giay') || str_contains($norm, 'chon size')) {
        return 'Tôi rất sẵn lòng hỗ trợ bạn. Khi chọn size online, bạn nên đo chiều dài bàn chân, so với bảng size trên trang sản phẩm và ưu tiên chọn size đang còn hàng. Nếu bạn đang phân vân giữa hai size, bạn có thể gửi chiều dài bàn chân để tôi gợi ý kỹ hơn.';
    }
    if (str_contains($norm, 'giao hang') || str_contains($norm, 'nhan duoc hang') || str_contains($norm, 'nhan hang') || str_contains($norm, 'van chuyen')) {
        return 'Thời gian giao hàng thường phụ thuộc khu vực nhận hàng và đơn vị vận chuyển. Bạn có thể theo dõi trạng thái trong trang Đơn hàng; nếu cần kiểm tra chi tiết, hãy tạo ticket hỗ trợ để bộ phận chăm sóc khách hàng hỗ trợ bạn.';
    }
    return 'Xin lỗi, hiện tại tôi chưa có thông tin chính xác về nội dung này. Bạn có thể tạo ticket hỗ trợ hoặc liên hệ bộ phận chăm sóc khách hàng để được hỗ trợ chi tiết hơn.';
}

function chatbot_public_asset_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return app_url('assets/img/placeholder.svg');
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, '/')) return $path;
    return app_url(ltrim($path, '/'));
}

function chatbot_find_products(string $question): array {
    $norm = chatbot_norm($question);
    preg_match('/(\d+(?:[.,]\d+)?)\s*(trieu|tr|k|nghin|ngan)?/iu', $question, $m);
    $max = null;
    if ($m) {
        $max = (float)str_replace(',', '.', $m[1]);
        $unit = chatbot_norm($m[2] ?? '');
        $max *= (str_contains($unit, 'tr')) ? 1000000 : ((str_contains($unit, 'k') || str_contains($unit, 'ng')) ? 1000 : 1);
    }
    $params = [];
    $where = ['p.status="active"', 'COALESCE(i.stock,0)>0'];
    if ($max) { $where[] = 'COALESCE(p.sale_price,p.price)<=?'; $params[] = $max; }
    $categoryMap = [
        'chay bo' => 'running',
        'running' => 'running',
        'bong ro' => 'basketball',
        'basketball' => 'basketball',
        'sneaker' => 'sneaker',
        'lifestyle' => 'lifestyle',
        'sandal' => 'sandal-dep',
        'dep' => 'sandal-dep',
    ];
    foreach ($categoryMap as $kw => $slug) {
        if (str_contains($norm, $kw)) { $where[] = 'c.slug=?'; $params[] = $slug; break; }
    }
    if (str_contains($norm, 'giay nam') || preg_match('/\bnam\b/', $norm)) { $where[] = "p.gender IN ('men','unisex')"; }
    if (str_contains($norm, 'giay nu') || preg_match('/\bnu\b/', $norm)) { $where[] = "p.gender IN ('women','unisex')"; }
    foreach (['nike','adidas','new balance','puma','converse','vans'] as $brand) {
        if (str_contains($norm, $brand)) { $where[] = 'p.brand LIKE ?'; $params[] = '%'.$brand.'%'; break; }
    }
    if (str_contains($norm, 'den') || str_contains($norm, 'black')) {
        $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
        $params[] = '%đen%'; $params[] = '%đen%';
    }
    $words = array_values(array_filter(explode(' ', $norm), fn($w) => strlen($w) > 2 && !in_array($w, ['toi','muon','xem','mua','giay','cho','duoi','trieu'], true)));
    if (!$max && !$params && $words) {
        $like = '%' . implode('%', array_slice($words, 0, 4)) . '%';
        $where[] = '(LOWER(p.name) LIKE ? OR LOWER(p.brand) LIKE ? OR LOWER(c.name) LIKE ? OR LOWER(p.description) LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $sql = 'SELECT p.id,p.name,p.slug,p.brand,p.image,p.description,COALESCE(p.sale_price,p.price) price,COALESCE(i.stock,0) stock,c.name category_name
            FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id
            WHERE '.implode(' AND ', $where).'
            ORDER BY p.featured DESC,p.best_seller DESC,price ASC LIMIT 5';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $products = [];
    foreach ($stmt as $p) {
        $products[] = [
            'name' => $p['name'],
            'brand' => $p['brand'] ?: 'ShoeStore',
            'price' => (float)$p['price'],
            'price_label' => money($p['price']),
            'stock' => (int)$p['stock'],
            'rating' => '4.8',
            'image' => chatbot_public_asset_url($p['image']),
            'url' => app_url('product.php?slug=' . urlencode($p['slug'])),
            'description' => mb_strimwidth((string)$p['description'], 0, 90, '...', 'UTF-8'),
        ];
    }
    return $products;
}

$norm = chatbot_norm($question);
$context = ['type' => 'general'];
$products = [];
$reply = null;
$supportIntent = chatbot_support_intent($norm);

if (!$supportIntent && chatbot_product_intent($norm)) {
    $products = chatbot_find_products($question);
    $context = ['type' => 'products', 'products' => $products];
    if ($products) {
        $reply = 'Tôi rất sẵn lòng hỗ trợ bạn. Đây là một số lựa chọn phù hợp, bạn có thể bấm “Xem chi tiết” để xem size, tồn kho và đặt hàng.';
    } else {
        $reply = 'Rất tiếc, hiện tại tôi chưa tìm thấy sản phẩm phù hợp với yêu cầu của bạn. Bạn có thể thử thay đổi từ khóa hoặc tham khảo các danh mục sản phẩm khác.';
    }
}

if ($reply === null && (str_contains($norm, 'don hang') || str_contains($norm, 'order'))) {
    $stmt = db()->prepare('SELECT code,total,payment_method,status,created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5');
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();
    $context = ['type' => 'orders', 'orders' => $orders];
    if ($orders) {
        $lines = ['Tôi rất sẵn lòng hỗ trợ bạn. Đây là các đơn hàng gần đây của bạn:'];
        foreach ($orders as $o) $lines[] = '- Đơn '.$o['code'].': '.money($o['total']).', trạng thái '.order_status_label($o['status']).'.';
        $reply = implode("\n", $lines);
    } else {
        $reply = 'Hiện tại tôi chưa thấy đơn hàng nào gần đây của bạn. Bạn có thể tiếp tục mua sắm hoặc tạo ticket hỗ trợ nếu cần kiểm tra thêm.';
    }
}

if ($reply === null && $supportIntent) {
    $knowledge = find_relevant_policies($question, 4);
    if (!$knowledge) {
        $like = '%' . preg_replace('/\s+/', '%', $question) . '%';
        $stmt = db()->prepare('(SELECT question title,answer content FROM faq WHERE active=1 AND (question LIKE ? OR answer LIKE ?) LIMIT 3) UNION ALL (SELECT title,content FROM knowledge_base WHERE active=1 AND (title LIKE ? OR content LIKE ?) LIMIT 3)');
        $stmt->execute([$like,$like,$like,$like]);
        $knowledge = $stmt->fetchAll();
    }
    $context = ['type' => 'support', 'items' => $knowledge];
    if ($knowledge) {
        $fallback = function() use ($knowledge) {
            $first = $knowledge[0];
            return 'Tôi rất sẵn lòng hỗ trợ bạn. ' . trim($first['content']) . "\n\nNếu bạn cần hỗ trợ chi tiết hơn, bạn có thể tạo ticket để bộ phận chăm sóc khách hàng kiểm tra giúp bạn.";
        };
        $ai = openrouter_chat([
            ['role'=>'system','content'=>'Bạn là nhân viên tư vấn bán hàng ShoeStore. Trả lời tự nhiên, lịch sự, ngắn gọn. Chỉ dùng nội dung chính sách được cung cấp. Không nhắc nguồn dữ liệu, không nhắc database, cơ sở dữ liệu, knowledge base, dữ liệu nội bộ hay cách hoạt động bên trong. Không tự bịa thông tin.'],
            ['role'=>'user','content'=>json_encode(['policy_information'=>$knowledge,'customer_question'=>$question], JSON_UNESCAPED_UNICODE)]
        ], ['temperature' => 0.25, 'max_tokens' => 550]);
        if ($ai['ok']) {
            $reply = (string)$ai['content'];
        } else {
            $reply = $fallback();
        }
    } else {
        $reply = chatbot_support_fallback($norm);
    }
}

if ($reply === null) {
    $ai = openrouter_chat([
        ['role'=>'system','content'=>'Bạn là nhân viên tư vấn bán hàng ShoeStore. Trả lời thân thiện, tự nhiên, ngắn gọn. Không nhắc database, cơ sở dữ liệu, knowledge base, dữ liệu nội bộ hoặc cách hoạt động bên trong. Nếu khách hỏi về sản phẩm, khuyến mãi, tồn kho, giá hoặc chính sách cụ thể mà không có thông tin được cung cấp, hãy hỏi thêm nhu cầu hoặc hướng dẫn tạo ticket; không tự bịa sản phẩm, giá, tồn kho, khuyến mãi.'],
        ['role'=>'user','content'=>$question]
    ], ['temperature' => 0.45, 'max_tokens' => 400]);
    $reply = $ai['ok']
        ? (string)$ai['content']
        : 'Xin lỗi, trợ lý AI đang tạm thời gặp sự cố. Vui lòng thử lại sau.';
}

$reply = chatbot_clean_reply($reply);
db()->prepare('INSERT INTO chat_history(user_id,question,database_context,ai_response) VALUES(?,?,?,?)')->execute([$user['id'],$question,json_encode($context,JSON_UNESCAPED_UNICODE),$reply]);
json_response(['reply'=>$reply,'products'=>$products]);
