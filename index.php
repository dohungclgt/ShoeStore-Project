<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
ensure_news_schema();
$products = db()->query('SELECT p.*, c.name category_name, COALESCE(i.stock,0) stock FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id WHERE p.status="active" ORDER BY p.featured DESC, p.created_at DESC LIMIT 8')->fetchAll();
$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$news = db()->query("SELECT * FROM news WHERE status='published' ORDER BY created_at DESC LIMIT 6")->fetchAll();
$popup = db()->query('SELECT * FROM popups WHERE active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY id DESC LIMIT 1')->fetch();
render_header('Trang chủ');
?>
<section class="hero">
  <div class="container">
    <div class="col-lg-7">
      <span class="hero-kicker"><i class="fa-solid fa-bolt"></i> Bộ sưu tập mới 2026</span>
      <h1 class="fw-black">ShoeStore</h1>
      <p class="lead">Giày chạy bộ, lifestyle và bóng rổ chính hãng với tồn kho minh bạch, thanh toán an toàn và ưu đãi theo thời gian thực.</p>
      <div class="d-flex gap-2 flex-wrap"><a class="btn btn-light btn-lg" href="products.php">Mua sắm ngay</a><a class="btn btn-outline-light btn-lg" href="#flash-sale">Flash Sale</a></div>
    </div>
  </div>
</section>
<main class="container py-5">
  <section class="home-search-panel table-card mb-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
      <div><h2 class="section-title mb-1">Tìm kiếm nhanh</h2><p class="section-subtitle mb-0">Gõ để tìm sản phẩm tự động, không cần tải lại trang.</p></div>
      <div class="d-flex gap-2"><button class="btn btn-outline-dark" type="button" id="saveHomeFilters">Lưu bộ lọc</button><button class="btn btn-dark" type="button" id="resetHomeFilters">Đặt lại bộ lọc</button></div>
    </div>
    <form id="homeSearchForm" class="row g-2">
      <div class="col-lg-3"><input name="q" class="form-control" placeholder="Tên sản phẩm, thương hiệu, mô tả..."></div>
      <div class="col-lg-2"><select name="category" class="form-select"><option value="">Danh mục</option><?php foreach ($cats as $cat): ?><option value="<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-lg-2"><input name="brand" class="form-control" placeholder="Thương hiệu"></div>
      <div class="col-lg-1"><input name="size" class="form-control" placeholder="Size"></div>
      <div class="col-lg-1"><input name="color" class="form-control" placeholder="Màu"></div>
      <div class="col-lg-1"><input name="max_price" class="form-control" placeholder="Giá tối đa"></div>
      <div class="col-lg-2 d-flex gap-3 align-items-center"><label class="small mb-0"><input type="checkbox" name="in_stock" value="1"> Còn hàng</label><label class="small mb-0"><input type="checkbox" name="sale" value="1"> Giảm giá</label></div>
    </form>
    <div id="homeSearchLoading" class="skeleton mt-3 d-none"></div>
    <div id="homeSearchResults" class="row g-4 mt-2"></div>
  </section>
  <div class="feature-strip mb-5">
    <div><i class="fa-solid fa-truck-fast text-danger me-2"></i><strong>Giao hàng nhanh</strong><p class="mb-0 text-muted">Theo dõi trạng thái rõ ràng.</p></div>
    <div><i class="fa-solid fa-rotate-left text-danger me-2"></i><strong>Đổi size 7 ngày</strong><p class="mb-0 text-muted">Hỗ trợ sau mua minh bạch.</p></div>
    <div><i class="fa-solid fa-shield-halved text-danger me-2"></i><strong>Thanh toán an toàn</strong><p class="mb-0 text-muted">COD và VNPay Sandbox.</p></div>
    <div><i class="fa-solid fa-ticket text-danger me-2"></i><strong>Coupon realtime</strong><p class="mb-0 text-muted">Ưu đãi cập nhật liên tục.</p></div>
  </div>
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="section-title">Danh mục</h2><a href="products.php">Xem tất cả</a></div>
  <div class="row g-3 mb-5">
    <?php foreach ($cats as $cat): ?>
    <div class="col-md-4"><a class="category-tile" href="products.php?category=<?= e($cat['slug']) ?>"><img class="img-fluid rounded" src="<?= e($cat['image']) ?>" alt="<?= e($cat['name']) ?>"><h3 class="h5 mt-2"><?= e($cat['name']) ?></h3></a></div>
    <?php endforeach; ?>
  </div>
  <section id="flash-sale" class="commerce-band p-4 rounded mb-5"><div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><h2 class="section-title mb-1">Flash Sale</h2><p class="section-subtitle mb-0">Các mẫu sneaker đang giảm giá mạnh trong hôm nay.</p></div><a class="btn btn-dark" href="products.php?q=run">Săn ưu đãi</a></div></section>
  <div class="skeleton mb-4"></div>
  <h2 class="section-title mb-3">Sản phẩm nổi bật</h2>
  <div class="row g-4">
    <?php foreach ($products as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
  </div>
  <section class="my-5 table-card">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
      <div><h2 class="section-title mb-1">Đánh giá khách hàng</h2><p class="section-subtitle mb-0">Trung bình <strong>4.8/5</strong> từ khách mua thực tế.</p></div>
      <span class="badge text-bg-danger align-self-start">Đánh giá nổi bật</span>
    </div>
    <div id="reviewCarousel" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">
        <div class="carousel-item active"><div class="row g-3"><div class="col-md-4"><div class="review-card table-card"><div class="d-flex gap-3 align-items-center mb-3"><img class="review-avatar" src="https://ui-avatars.com/api/?name=Minh+Anh" alt="Minh Anh"><div><strong>Minh Anh</strong><div class="review-stars">★★★★★</div><small class="text-muted">02/06/2026</small></div></div><p>Giao nhanh, size đúng, giày chạy rất êm và đúng tồn kho hiển thị.</p></div></div><div class="col-md-4"><div class="review-card table-card"><div class="d-flex gap-3 align-items-center mb-3"><img class="review-avatar" src="https://ui-avatars.com/api/?name=Quoc+Bao" alt="Quốc Bảo"><div><strong>Quốc Bảo</strong><div class="review-stars">★★★★★</div><small class="text-muted">01/06/2026</small></div></div><p>Đơn hàng cập nhật rõ ràng, thanh toán VNPay chuyển hướng mượt.</p></div></div><div class="col-md-4"><div class="review-card table-card"><div class="d-flex gap-3 align-items-center mb-3"><img class="review-avatar" src="https://ui-avatars.com/api/?name=Thanh+Truc" alt="Thanh Trúc"><div><strong>Thanh Trúc</strong><div class="review-stars">★★★★☆</div><small class="text-muted">30/05/2026</small></div></div><p>Hỗ trợ đổi size nhanh, nhân viên phản hồi ticket rất rõ ràng.</p></div></div></div></div>
        <div class="carousel-item"><div class="row g-3"><div class="col-md-4"><div class="review-card table-card"><div class="d-flex gap-3 align-items-center mb-3"><img class="review-avatar" src="https://ui-avatars.com/api/?name=Hoang+Nam" alt="Hoàng Nam"><div><strong>Hoàng Nam</strong><div class="review-stars">★★★★★</div><small class="text-muted">29/05/2026</small></div></div><p>Giày lifestyle đẹp, đóng gói chắc chắn, coupon áp dụng dễ hiểu.</p></div></div><div class="col-md-4"><div class="review-card table-card"><div class="d-flex gap-3 align-items-center mb-3"><img class="review-avatar" src="https://ui-avatars.com/api/?name=Lan+Huong" alt="Lan Hương"><div><strong>Lan Hương</strong><div class="review-stars">★★★★★</div><small class="text-muted">28/05/2026</small></div></div><p>Giao diện dễ dùng trên điện thoại, tìm kiếm sản phẩm rất nhanh.</p></div></div><div class="col-md-4"><div class="review-card table-card"><div class="d-flex gap-3 align-items-center mb-3"><img class="review-avatar" src="https://ui-avatars.com/api/?name=Gia+Huy" alt="Gia Huy"><div><strong>Gia Huy</strong><div class="review-stars">★★★★☆</div><small class="text-muted">27/05/2026</small></div></div><p>Mẫu chạy bộ nhẹ, đúng mô tả, chatbot tư vấn đúng sản phẩm đang có.</p></div></div></div></div>
      </div>
      <button class="carousel-control-prev" type="button" data-bs-target="#reviewCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
      <button class="carousel-control-next" type="button" data-bs-target="#reviewCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
    </div>
  </section>
  <section class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="section-title">Tin tức Sneaker</h2><a href="news.php">Xem tất cả</a></div>
    <div class="row g-4">
      <?php foreach($news as $n): ?>
        <div class="col-md-6 col-lg-4">
          <article class="card product-card h-100">
            <img src="<?= e($n['thumbnail']) ?>" class="card-img-top" alt="<?= e($n['title']) ?>">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between small text-muted mb-2"><span><?= e($n['author']) ?></span><span><?= e(date('d/m/Y', strtotime($n['created_at']))) ?></span></div>
              <h3 class="h5"><?= e($n['title']) ?></h3>
              <p class="text-muted"><?= e($n['excerpt']) ?></p>
              <span class="badge text-bg-light align-self-start mb-3"><?= e($n['tags'] ?? '') ?></span>
              <a class="btn btn-outline-dark mt-auto" href="<?= e(app_url('news-detail.php?slug=' . urlencode($n['slug']))) ?>">Đọc thêm</a>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<?php if ($popup): ?>
<div class="modal fade" id="promoModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <img src="<?= e($popup['image']) ?>" class="img-fluid" alt="<?= e($popup['title']) ?>">
    <div class="modal-body"><h5><?= e($popup['title']) ?></h5><p><?= e($popup['content']) ?></p><a class="btn btn-dark popup-click" data-id="<?= (int)$popup['id'] ?>" href="<?= e($popup['cta_link']) ?>"><?= e($popup['cta_text']) ?></a><button class="btn btn-link" id="hideToday" type="button">Không hiển thị hôm nay</button></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const key='popup_hide_until';
  const modalEl=document.getElementById('promoModal');
  const modal=new bootstrap.Modal(modalEl);
  const hideUntil=Number(localStorage.getItem(key) || 0);
  if(!hideUntil || hideUntil < Date.now()){
    modal.show();
    fetch('api/popup.php?action=impression&id=<?= (int)$popup['id'] ?>').catch(()=>{});
  }
  document.getElementById('hideToday').addEventListener('click',()=>{
    localStorage.setItem(key, String(Date.now()+86400000));
    modal.hide();
  });
});
</script>
<?php endif; ?>
<?php render_footer(); ?>
