<div class="col-sm-6 col-lg-3">
  <div class="card product-card">
    <?php if (!empty($p['sale_price']) && (float)$p['sale_price'] < (float)$p['price']): ?><span class="badge-sale">-<?= (int)round((1 - $p['sale_price'] / $p['price']) * 100) ?>%</span><?php endif; ?>
    <img src="<?= e($p['image']) ?>" class="card-img-top" alt="<?= e($p['name']) ?>">
    <div class="card-body d-flex flex-column">
      <span class="text-muted small"><?= e($p['category_name'] ?? '') ?> · Còn <?= (int)($p['stock'] ?? 0) ?> sản phẩm</span>
      <h3 class="h6 mt-1"><?= e($p['name']) ?></h3>
      <div class="small text-warning mb-1">★★★★★ <span class="text-muted">(4.8)</span></div>
      <?php
        $couponBadge = db()->prepare("SELECT COUNT(*) FROM coupons c LEFT JOIN coupon_products cp ON cp.coupon_id=c.id LEFT JOIN coupon_categories cc ON cc.coupon_id=c.id WHERE c.active=1 AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW()) AND (cp.product_id IS NULL AND cc.category_id IS NULL OR cp.product_id=? OR cc.category_id=?)");
        $couponBadge->execute([(int)$p['id'], (int)$p['category_id']]);
      ?>
      <?php if ((int)$couponBadge->fetchColumn() > 0): ?><span class="badge text-bg-danger align-self-start mb-2">Có mã giảm giá</span><?php endif; ?>
      <p class="price mb-3"><?= money($p['sale_price'] ?: $p['price']) ?></p>
      <a class="btn btn-dark mt-auto" href="<?= e(app_url('product.php?slug=' . urlencode($p['slug']))) ?>">Chi tiết</a>
    </div>
  </div>
</div>
