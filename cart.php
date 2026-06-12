<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
ensure_size_schema();
ensure_commerce_schema();

function normalize_cart(): void {
    foreach ($_SESSION['cart'] ?? [] as $key => $value) {
        if (is_array($value)) continue;
        $pid = (int)$key;
        unset($_SESSION['cart'][$key]);
        $stmt = db()->prepare('SELECT size,stock FROM product_sizes WHERE product_id=? AND stock>0 ORDER BY CAST(size AS UNSIGNED),size LIMIT 1');
        $stmt->execute([$pid]);
        $size = $stmt->fetch();
        if ($size) {
            $_SESSION['cart'][$pid . ':' . $size['size']] = ['product_id'=>$pid,'size'=>$size['size'],'quantity'=>(int)$value,'unit_price'=>0];
        }
    }
}

function load_cart_items(): array {
    normalize_cart();
    if (empty($_SESSION['cart'])) return [];
    $items = [];
    foreach ($_SESSION['cart'] as $key => $line) {
        $stmt = db()->prepare('SELECT p.*, ps.stock size_stock FROM products p JOIN product_sizes ps ON ps.product_id=p.id AND ps.size=? WHERE p.id=?');
        $stmt->execute([$line['size'], $line['product_id']]);
        $p = $stmt->fetch();
        if (!$p) { unset($_SESSION['cart'][$key]); continue; }
        $p['cart_key'] = $key;
        $p['size'] = $line['size'];
        $p['quantity'] = min(max(1, (int)$line['quantity']), (int)$p['size_stock']);
        $p['unit_price'] = (float)($line['unit_price'] ?: ($p['sale_price'] ?: $p['price']));
        $p['line_total'] = $p['unit_price'] * $p['quantity'];
        $items[$key] = $p;
    }
    return $items;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'update';
    $items = load_cart_items();
    if ($action === 'update') {
        foreach ($_POST['qty'] ?? [] as $key => $qty) {
            if (!isset($items[$key])) continue;
            $qty = max(1, (int)$qty);
            if ($qty > (int)$items[$key]['size_stock']) {
                flash('error', 'Số lượng vượt quá tồn kho size đã chọn.');
                header('Location: cart.php'); exit;
            }
            $_SESSION['cart'][$key]['quantity'] = $qty;
        }
        flash('success', 'Đã cập nhật giỏ hàng.');
        header('Location: cart.php'); exit;
    }
    $selectedInput = $_POST['selected'] ?? [];
    if (!is_array($selectedInput)) $selectedInput = [$selectedInput];
    $selected = array_values(array_filter($selectedInput, fn($key) => isset($items[$key])));
    if (!$selected) {
        flash('error', 'Vui lòng chọn ít nhất một sản phẩm để thanh toán.');
        header('Location: cart.php'); exit;
    }
    if ($action === 'delete_selected') {
        foreach ($selected as $key) unset($_SESSION['cart'][$key]);
        flash('success', 'Đã xóa các sản phẩm đã chọn khỏi giỏ hàng.');
        header('Location: cart.php'); exit;
    }
    if ($action === 'checkout_selected') {
        $_SESSION['checkout_items'] = $selected;
        if (isset($_POST['coupon'])) remember_cart_coupon((string)$_POST['coupon']);
        header('Location: checkout.php'); exit;
    }
}

$items = load_cart_items();
$cartCouponCode = (string)($_SESSION['cart_coupon_code'] ?? '');
render_header('Giỏ hàng');
?>
<main class="container py-5">
  <h1 class="section-title">Giỏ hàng</h1>
  <form method="post" id="cartForm" class="table-card">
    <?= csrf_field() ?><input type="hidden" name="action" id="cartAction" value="update">
    <table class="table align-middle"><thead><tr><th><input type="checkbox" id="selectAllCart" checked></th><th>Sản phẩm</th><th>Size</th><th>Giá</th><th>Số lượng</th><th>Tạm tính</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?>
      <tr data-name="<?= e($i['name']) ?> - Size <?= e($i['size']) ?>" data-price="<?= e((string)$i['unit_price']) ?>" data-product="<?= (int)$i['id'] ?>">
        <td><input type="checkbox" class="cart-select" name="selected[]" value="<?= e($i['cart_key']) ?>" checked></td>
        <td><div class="d-flex align-items-center gap-3"><img src="<?= e($i['image']) ?>" style="width:72px;height:72px;object-fit:cover;border-radius:6px" alt=""><strong><?= e($i['name']) ?></strong></div></td>
        <td><span class="badge text-bg-dark"><?= e($i['size']) ?></span><br><small class="text-muted">Còn <?= (int)$i['size_stock'] ?></small></td>
        <td><?= money($i['unit_price']) ?></td>
        <td><input class="form-control cart-qty" type="number" name="qty[<?= e($i['cart_key']) ?>]" value="<?= (int)$i['quantity'] ?>" min="1" max="<?= (int)$i['size_stock'] ?>" style="max-width:110px"></td>
        <td class="line-total"><?= money($i['line_total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <div class="row justify-content-end"><div class="col-md-4"><div class="p-3 border rounded">
      <label class="form-label">Coupon</label><input id="cartCoupon" name="coupon" class="form-control mb-1" placeholder="WELCOME10" value="<?= e($cartCouponCode) ?>"><div id="cartCouponMessage" class="small mb-2"></div>
      <div class="d-flex justify-content-between"><span>Tạm tính</span><strong id="cartSubtotal">0 VND</strong></div>
      <div class="d-flex justify-content-between text-danger"><span id="cartDiscountLabel">Mã giảm giá</span><strong id="cartDiscount">0 VND</strong></div>
      <div class="d-flex justify-content-between"><span>Tổng sau giảm</span><strong id="cartAfterDiscount">0 VND</strong></div>
      <div class="d-flex justify-content-between"><span>VAT dự kiến</span><strong id="cartVat">0 VND</strong></div>
      <div class="d-flex justify-content-between fs-5 mt-2"><span>Tổng cộng</span><strong id="cartTotal">0 VND</strong></div>
    </div></div></div>
    <div class="d-flex flex-wrap gap-2 justify-content-between mt-3"><button class="btn btn-outline-dark" type="submit" data-cart-action="update">Cập nhật</button><div class="d-flex gap-2"><button class="btn btn-outline-danger" type="submit" data-cart-action="delete_selected">Xóa đã chọn</button><button class="btn btn-dark" type="submit" data-cart-action="checkout_selected">Thanh toán đã chọn</button></div></div>
  </form>
</main>
<script>
document.addEventListener('DOMContentLoaded',()=> {
  const money = v => Number(v).toLocaleString('vi-VN') + ' VND';
  const vatRate = Number(window.SHOESTORE?.vatRate ?? 0.05);
  const form = document.getElementById('cartForm'), action = document.getElementById('cartAction'), couponInput = document.getElementById('cartCoupon'), couponMessage = document.getElementById('cartCouponMessage');
  let couponDiscount=0,couponFinal=0,couponTimer=null;
  const selectedProductIds=()=>[...document.querySelectorAll('.cart-select:checked')].map(c=>c.closest('tr').dataset.product);
  function validateCoupon(subtotal){ const code=couponInput.value.trim(); couponDiscount=0; couponFinal=subtotal; if(!code){couponMessage.textContent=''; return Promise.resolve();} couponMessage.className='small mb-2 text-muted'; couponMessage.textContent='Đang kiểm tra coupon...'; const body=new URLSearchParams(); body.set('code',code); body.set('subtotal',subtotal); selectedProductIds().forEach(id=>body.append('product_ids[]',id)); return fetch('api/coupon.php',{method:'POST',body}).then(r=>r.json()).then(res=>{ if(!res.valid){couponMessage.className='small mb-2 text-danger'; couponMessage.textContent=res.message; return;} couponMessage.className='small mb-2 text-success'; couponMessage.textContent=res.message; couponDiscount=Number(res.discount||0); couponFinal=Number(res.final||subtotal); document.getElementById('cartDiscountLabel').textContent='Mã giảm giá '+res.code; }).catch(()=>{couponMessage.className='small mb-2 text-danger'; couponMessage.textContent='Không kiểm tra được coupon.';}); }
  function recalc(){ let subtotal=0; document.querySelectorAll('#cartForm tbody tr').forEach(row=>{ const qtyInput=row.querySelector('.cart-qty'); const qty=Math.max(1,Number(qtyInput.value||1)); const max=Number(qtyInput.max||qty); if(qty>max){qtyInput.value=max; Swal.fire('Lỗi','Số lượng vượt quá tồn kho size đã chọn.','error');} const line=Number(row.dataset.price)*Number(qtyInput.value); row.querySelector('.line-total').textContent=money(line); if(row.querySelector('.cart-select').checked) subtotal+=line; }); clearTimeout(couponTimer); couponTimer=setTimeout(()=>validateCoupon(subtotal).finally(()=>{ const after=couponInput.value.trim()?couponFinal:subtotal; document.getElementById('cartSubtotal').textContent=money(subtotal); document.getElementById('cartDiscount').textContent='-'+money(couponInput.value.trim()?couponDiscount:0); document.getElementById('cartAfterDiscount').textContent=money(after); document.getElementById('cartVat').textContent=money(after*vatRate); document.getElementById('cartTotal').textContent=money(after*(1+vatRate)); }),250); }
  document.getElementById('selectAllCart')?.addEventListener('change',e=>{document.querySelectorAll('.cart-select').forEach(c=>c.checked=e.target.checked); recalc();});
  document.querySelectorAll('.cart-select,.cart-qty').forEach(el=>el.addEventListener('input',recalc)); couponInput.addEventListener('input',recalc);
  document.querySelectorAll('[data-cart-action]').forEach(btn=>btn.addEventListener('click',()=>action.value=btn.dataset.cartAction));
  form?.addEventListener('submit',e=>{ const selected=[...document.querySelectorAll('.cart-select:checked')]; if(action.value!=='update'&&selected.length===0){e.preventDefault(); Swal.fire('Thiếu sản phẩm','Vui lòng chọn ít nhất một sản phẩm để thanh toán.','warning'); return;} if(action.value==='delete_selected'){e.preventDefault(); Swal.fire({title:'Xác nhận xóa',text:'Bạn có chắc chắn muốn xóa các sản phẩm đã chọn khỏi giỏ hàng không?',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa',cancelButtonText:'Hủy'}).then(r=>{if(r.isConfirmed) form.submit();});} if(action.value==='checkout_selected'){e.preventDefault(); const list=selected.map(c=>{const r=c.closest('tr'); return `<li>${r.dataset.name} - SL ${r.querySelector('.cart-qty').value} - ${r.querySelector('.line-total').textContent}</li>`}).join(''); Swal.fire({title:'Xác nhận thanh toán',html:`Bạn sẽ tiến hành thanh toán cho:<ul class="text-start">${list}</ul><p class="text-start">Tạm tính: ${document.getElementById('cartSubtotal').textContent}<br>Mã giảm giá: ${document.getElementById('cartDiscount').textContent}<br>Tổng sau giảm: ${document.getElementById('cartAfterDiscount').textContent}</p>Bạn có muốn tiếp tục không?`,showCancelButton:true,confirmButtonText:'Tiếp tục',cancelButtonText:'Hủy'}).then(r=>{if(r.isConfirmed) form.submit();});} });
  setInterval(()=>{ if(couponInput.value.trim()) recalc(); },30000); recalc();
});
</script>
<?php render_footer(); ?>
