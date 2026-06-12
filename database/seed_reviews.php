<?php
require_once __DIR__ . '/../includes/bootstrap.php';
ensure_review_schema();
ensure_payment_schema();
ensure_size_schema();

$userId = (int)(db()->query("SELECT id FROM users WHERE email='customer@shoestore.local' LIMIT 1")->fetchColumn() ?: db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn());
$products = db()->query("SELECT id,name,price,sale_price FROM products WHERE status='active' ORDER BY best_seller DESC, featured DESC, id ASC LIMIT 8")->fetchAll();
$comments = [
    [5, 'Form ôm chân, đi chạy bộ khá êm.'],
    [4, 'Giao hàng nhanh, đóng gói chắc chắn.'],
    [5, 'Size đúng, màu ngoài đẹp hơn ảnh.'],
    [4, 'Đế bám tốt, phù hợp đi hằng ngày.'],
    [3, 'Giá ổn so với chất lượng, phần dây cần chắc hơn một chút.'],
];

foreach ($products as $index => $product) {
    $pid = (int)$product['id'];
    $orderStmt = db()->prepare('SELECT oi.order_id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=? AND o.user_id=? ORDER BY oi.order_id DESC LIMIT 1');
    $orderStmt->execute([$pid, $userId]);
    $orderId = (int)$orderStmt->fetchColumn();
    if (!$orderId) {
        $code = 'RV' . date('ymdHis') . random_int(100, 999) . $pid;
        $price = (float)($product['sale_price'] ?: $product['price']);
        db()->beginTransaction();
        db()->prepare("INSERT INTO orders(user_id,code,subtotal,discount,shipping_fee,vat,total,payment_method,status,shipping_name,shipping_phone,shipping_address,note) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$userId,$code,$price,0,0,0,$price,'COD','completed','Demo Customer','0900000000','Seed review order','Đơn hàng mẫu để gắn đánh giá']);
        $orderId = (int)db()->lastInsertId();
        db()->prepare('INSERT INTO order_items(order_id,product_id,product_name,size,price,quantity) VALUES(?,?,?,?,?,?)')->execute([$orderId,$pid,$product['name'],'40',$price,1]);
        db()->prepare("INSERT INTO payments(order_id,provider,amount,status,payment_attempts) VALUES(?,?,?,?,0)")->execute([$orderId,'COD',$price,'unpaid']);
        db()->commit();
    }
    for ($i = 0; $i < 5; $i++) {
        $comment = $comments[($i + $index) % count($comments)];
        $text = $comment[1] . ' #' . $pid . '-' . ($i + 1);
        $exists = db()->prepare('SELECT id FROM reviews WHERE product_id=? AND order_id=? AND comment=? LIMIT 1');
        $exists->execute([$pid, $orderId, $text]);
        if ($exists->fetchColumn()) continue;
        $image = ($i === 1 || $i === 3) ? 'assets/img/review-placeholder.svg' : null;
        db()->prepare('INSERT INTO reviews(user_id,product_id,order_id,rating,comment,image,approved,created_at) VALUES(?,?,?,?,?,?,1,DATE_SUB(NOW(), INTERVAL ? DAY))')->execute([$userId,$pid,$orderId,$comment[0],$text,$image,($index * 3) + $i]);
        $reviewId = (int)db()->lastInsertId();
        if ($image) {
            db()->prepare("INSERT INTO review_media(review_id,file_path,file_type,mime_type) VALUES(?,?,?,?)")->execute([$reviewId,$image,'image','image/svg+xml']);
        }
    }
}

echo "Seeded realistic reviews for " . count($products) . " products.\n";
