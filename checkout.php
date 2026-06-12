<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/mailer.php';
$user = require_login();
ensure_payment_schema();
ensure_size_schema();
ensure_commerce_schema();
$selectedKeys = array_values(array_filter(array_map('strval', $_SESSION['checkout_items'] ?? [])));
if (empty($_SESSION['cart']) || !$selectedKeys) { flash('error','Vui lòng chọn ít nhất một sản phẩm để thanh toán.'); header('Location: cart.php'); exit; }

function checkout_cart_items(array $selectedKeys): array {
    $cart = $_SESSION['cart'] ?? [];
    $items = [];
    foreach ($selectedKeys as $key) {
        if (!isset($cart[$key]) || !is_array($cart[$key])) continue;
        $line = $cart[$key];
        $stmt = db()->prepare('SELECT p.*, ps.stock size_stock FROM products p JOIN product_sizes ps ON ps.product_id=p.id AND ps.size=? WHERE p.id=?');
        $stmt->execute([$line['size'], $line['product_id']]);
        $p = $stmt->fetch();
        if (!$p) continue;
        $p['cart_key'] = $key;
        $p['size'] = $line['size'];
        $p['quantity'] = max(1, (int)$line['quantity']);
        $p['unit_price'] = (float)($line['unit_price'] ?: ($p['sale_price'] ?: $p['price']));
        if ($p['quantity'] > (int)$p['size_stock']) throw new RuntimeException('Số lượng vượt quá tồn kho size ' . $p['size'] . ': ' . $p['name']);
        $items[] = $p;
    }
    return $items;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $items = checkout_cart_items($selectedKeys);
        if (!$items) throw new RuntimeException('Vui lòng chọn ít nhất một sản phẩm để thanh toán.');
        $subtotal = array_reduce($items, fn($sum,$p)=>$sum+$p['unit_price']*(int)$p['quantity'], 0);
        $productIdsForCoupon = array_map(fn($p)=>(int)$p['id'], $items);
        $couponCode = trim((string)($_POST['coupon'] ?? ($_SESSION['cart_coupon_code'] ?? '')));
        $totals = calculate_coupon_totals($subtotal, $couponCode, $productIdsForCoupon);
        if ($couponCode !== '' && !$totals['coupon']) {
            remember_cart_coupon(null);
            flash('error', $totals['coupon_message'] ?: 'Mã giảm giá không còn hiệu lực và đã được gỡ khỏi giỏ hàng.');
            header('Location: checkout.php'); exit;
        }
        if ($totals['coupon']) remember_cart_coupon($totals['coupon_code']);
        $discount = $totals['discount']; $shipping = $totals['shipping']; $vat = $totals['vat']; $total = $totals['total'];
        $code='SS'.date('ymdHis').random_int(10,99); $method=strtoupper((string)($_POST['payment_method'] ?? 'COD'));
        if (!in_array($method, ['COD','VNPAY','MOCK'], true)) throw new RuntimeException('Phương thức thanh toán không hợp lệ.');
        ensure_support_schema();
        db()->beginTransaction();
        $status=$method==='COD'?'waiting_pickup':($method==='MOCK'?'waiting_confirm':'pending_payment');
        db()->prepare('INSERT INTO orders(user_id,code,subtotal,discount,coupon_code,shipping_fee,vat,total,payment_method,status,shipping_name,shipping_phone,shipping_address,note) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$user['id'],$code,$subtotal,$discount,$totals['coupon_code'] ?: null,$shipping,$vat,$total,$method,$status,$_POST['shipping_name'],$_POST['shipping_phone'],$_POST['shipping_address'],$_POST['note'] ?? '']);
        $orderId=(int)db()->lastInsertId();
        $itemStmt=db()->prepare('INSERT INTO order_items(order_id,product_id,product_name,size,price,quantity) VALUES(?,?,?,?,?,?)');
        $paidKeys=[]; $paidProductIds=[];
        foreach($items as $p){
            decrement_product_size_stock((int)$p['id'], $p['size'], (int)$p['quantity']);
            $itemStmt->execute([$orderId,$p['id'],$p['name'],$p['size'],$p['unit_price'],$p['quantity']]);
            $paidKeys[]=$p['cart_key']; $paidProductIds[]=(int)$p['id'];
        }
        foreach (array_unique($paidProductIds) as $pid) sync_product_total_stock((int)$pid);
        $paymentStatus = $method==='COD' ? 'unpaid' : ($method==='MOCK' ? 'paid' : 'pending');
        $transactionId = $method==='MOCK' ? ('MOCK_' . date('YmdHis') . random_int(1000, 9999)) : null;
        db()->prepare('INSERT INTO payments(order_id,provider,transaction_id,amount,status,payment_attempts,raw_response) VALUES(?,?,?,?,?,?,?)')->execute([
            $orderId,
            $method,
            $transactionId,
            $total,
            $paymentStatus,
            0,
            $method==='MOCK' ? json_encode(['provider'=>'ShoeStore Pay','result'=>'success','transaction_id'=>$transactionId,'paid_at'=>date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
        $paymentId=(int)db()->lastInsertId();
        if ($totals['coupon']) db()->prepare('INSERT INTO coupon_usage(coupon_id,user_id,order_id) VALUES(?,?,?)')->execute([(int)$totals['coupon']['id'], (int)$user['id'], $orderId]);
        if ($method === 'MOCK') {
            db()->prepare('INSERT INTO payment_logs(payment_id,provider,action,payload,valid_signature) VALUES(?,?,?,?,?)')->execute([
                $paymentId,
                'MOCK',
                'mock_auto_success',
                json_encode(['order_id'=>$orderId,'code'=>$code,'amount'=>(float)$total,'transaction_id'=>$transactionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                1,
            ]);
            create_notification((int)$user['id'], 'Thanh toán mô phỏng thành công', 'Đơn hàng '.$code.' đã thanh toán thành công.', 'payment', notification_detail_link('order', $orderId, false));
            notify_admins('Thanh toán mô phỏng thành công', 'Đơn hàng '.$code.' đã được thanh toán qua ShoeStore Pay.', 'payment', notification_detail_link('payment', $paymentId, true));
            audit_log('mock_payment_success','orders',$orderId,['code'=>$code,'method'=>$method,'total'=>$total,'transaction_id'=>$transactionId,'cart_keys'=>$paidKeys]);
        } else {
            create_notification((int)$user['id'], 'Đặt hàng thành công', 'Đơn hàng '.$code.' đã được tạo.');
            notify_admins('Có đơn hàng mới', 'Đơn hàng '.$code.' vừa được tạo.');
            audit_log('create_order','orders',$orderId,['code'=>$code,'method'=>$method,'total'=>$total,'cart_keys'=>$paidKeys]);
        }
        db()->commit();
        $_SESSION['pending_order_items'][$orderId] = $paidKeys;
        if ($method === 'VNPAY') { header('Location: api/vnpay.php?action=create&order_id='.$orderId); exit; }
        foreach ($paidKeys as $key) unset($_SESSION['cart'][$key]);
        unset($_SESSION['checkout_items'], $_SESSION['pending_order_items'][$orderId], $_SESSION['cart_coupon_code']);
        $mailOrder = db()->prepare('SELECT * FROM orders WHERE id=?'); $mailOrder->execute([$orderId]); $orderForMail = $mailOrder->fetch();
        if ($method === 'MOCK') {
            if ($orderForMail) send_mail($user['email'], 'Thanh toán mô phỏng thành công '.$code, render_email_template('payment-success', order_email_data($orderForMail, 'Thanh toán thành công', 'Đơn hàng của bạn đã được thanh toán thành công bằng Thanh toán mô phỏng.')), 'Đơn hàng '.$code.' đã thanh toán thành công. Tổng tiền: '.money($total));
            flash('success','Thanh toán mô phỏng thành công. Đơn hàng đang chờ xác nhận.');
            header('Location: user/order-detail.php?order_id='.$orderId); exit;
        }
        if ($orderForMail) send_mail($user['email'], 'Đặt hàng thành công '.$code, render_email_template('order-created', order_email_data($orderForMail, 'Đặt hàng thành công', 'Đơn hàng của bạn đã được tạo và đang chờ xử lý.')), 'Đơn hàng '.$code.' đã được tạo. Tổng tiền: '.money($total));
        flash('success','Đặt hàng COD thành công.'); header('Location: user/orders.php'); exit;
    } catch (Throwable $e) { if(db()->inTransaction()) db()->rollBack(); flash('error',$e->getMessage()); }
}
$items = checkout_cart_items($selectedKeys);
$subtotal = array_reduce($items, fn($sum,$p)=>$sum+$p['unit_price']*(int)$p['quantity'], 0);
$productIds = array_map(fn($p)=>(int)$p['id'], $items);
$currentCoupon = (string)($_SESSION['cart_coupon_code'] ?? '');
$displayTotals = calculate_coupon_totals($subtotal, $currentCoupon, $productIds);
if ($currentCoupon !== '' && !$displayTotals['coupon']) {
    remember_cart_coupon(null);
    flash('error', $displayTotals['coupon_message'] ?: 'Mã giảm giá không còn hiệu lực và đã được gỡ khỏi giỏ hàng.');
    header('Location: cart.php'); exit;
}
render_header('Thanh toán');
?>
<main class="container py-5"><h1 class="section-title">Thanh toán</h1><div class="row g-4"><div class="col-lg-7"><form method="post" class="table-card row g-3" data-confirm-submit="Xác nhận tạo đơn hàng và chuyển sang bước thanh toán?"><?= csrf_field() ?><div class="col-md-6"><label>Họ tên</label><input name="shipping_name" class="form-control" required value="<?= e($user['name']) ?>"></div><div class="col-md-6"><label>Điện thoại</label><input name="shipping_phone" class="form-control" required value="<?= e($user['phone']) ?>"></div><div class="col-12"><label>Địa chỉ</label><textarea name="shipping_address" class="form-control" required><?= e($user['address']) ?></textarea></div><div class="col-md-6"><label>Coupon</label><input name="coupon" id="checkoutCoupon" class="form-control" placeholder="WELCOME10" value="<?= e($displayTotals['coupon_code']) ?>" data-subtotal="<?= e((string)$subtotal) ?>" data-products="<?= e(implode(',', $productIds)) ?>"><div id="checkoutCouponMessage" class="small mt-1"><?= e($displayTotals['coupon_message']) ?></div></div><div class="col-md-6"><label>Phương thức thanh toán</label><select name="payment_method" class="form-select"><option value="COD">COD</option><option value="VNPAY">VNPay Sandbox</option><option value="MOCK">Thanh toán mô phỏng</option></select><div class="small text-muted mt-1">Không thể kết nối VNPay Sandbox. Bạn có thể dùng Thanh toán mô phỏng để kiểm thử quy trình.</div></div><div class="col-12"><label>Ghi chú</label><textarea name="note" class="form-control"></textarea></div><div class="col-12"><button class="btn btn-dark">Đặt hàng</button></div></form></div><div class="col-lg-5"><div class="table-card"><h2 class="h5">Sản phẩm thanh toán</h2><?php foreach($items as $p): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e($p['name']) ?> · Size <?= e($p['size']) ?> x <?= (int)$p['quantity'] ?></span><strong><?= money($p['unit_price']*$p['quantity']) ?></strong></div><?php endforeach; ?><div class="d-flex justify-content-between mt-3"><span>Tạm tính</span><strong><?= money($subtotal) ?></strong></div><div class="d-flex justify-content-between text-danger"><span id="checkoutDiscountLabel">Mã giảm giá <?= e($displayTotals['coupon_code']) ?></span><strong id="checkoutDiscount">-<?= money($displayTotals['discount']) ?></strong></div><div class="d-flex justify-content-between"><span>Tổng sau giảm</span><strong id="checkoutAfterDiscount"><?= money($displayTotals['after_discount']) ?></strong></div><div class="d-flex justify-content-between"><span>Phí vận chuyển</span><strong id="checkoutShipping"><?= money($displayTotals['shipping']) ?></strong></div><div class="d-flex justify-content-between"><span>VAT</span><strong id="checkoutVat"><?= money($displayTotals['vat']) ?></strong></div><div class="d-flex justify-content-between fs-5 mt-2"><span>Tổng cộng</span><strong id="checkoutTotal"><?= money($displayTotals['total']) ?></strong></div></div></div></div></main>
<script>
document.addEventListener('DOMContentLoaded',()=>{const input=document.getElementById('checkoutCoupon'); if(!input)return; const msg=document.getElementById('checkoutCouponMessage'); const money=v=>Number(v).toLocaleString('vi-VN')+' VND'; const vatRate=Number(window.SHOESTORE?.vatRate ?? 0.05); let timer=null; function paint(discount,after,shipping,vat,total,text=''){document.getElementById('checkoutDiscount').textContent='-'+money(discount);document.getElementById('checkoutAfterDiscount').textContent=money(after);document.getElementById('checkoutShipping').textContent=money(shipping);document.getElementById('checkoutVat').textContent=money(vat);document.getElementById('checkoutTotal').textContent=money(total);msg.textContent=text;} function reset(text=''){const subtotal=Number(input.dataset.subtotal);paint(0,subtotal,30000,subtotal*vatRate,subtotal+30000+subtotal*vatRate,text);} input.addEventListener('input',()=>{clearTimeout(timer); const code=input.value.trim(); if(!code){msg.className='small mt-1 text-muted'; reset(''); return;} msg.className='small mt-1 text-muted'; msg.textContent='Đang kiểm tra coupon...'; timer=setTimeout(()=>{const body=new URLSearchParams(); body.set('code',code); body.set('subtotal',input.dataset.subtotal); input.dataset.products.split(',').filter(Boolean).forEach(id=>body.append('product_ids[]',id)); fetch('api/coupon.php',{method:'POST',body}).then(r=>r.json()).then(res=>{if(!res.valid){msg.className='small mt-1 text-danger'; reset(res.message); return;} msg.className='small mt-1 text-success'; msg.textContent=res.message; document.getElementById('checkoutDiscountLabel').textContent='Mã giảm giá '+res.code; paint(Number(res.discount||0),Number(res.after_discount||res.final||0),Number(res.shipping||30000),Number(res.vat||0),Number(res.total||0),res.message);}).catch(()=>{msg.className='small mt-1 text-danger'; reset('Không kiểm tra được coupon.');});},350);}); if(input.value.trim()) input.dispatchEvent(new Event('input')); setInterval(()=>{if(input.value.trim()) input.dispatchEvent(new Event('input'));},30000);});
</script>
<?php render_footer(); ?>
