ALTER DATABASE shoestore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=0;
ALTER TABLE roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE product_images CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE inventory CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE inventory_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE orders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE order_items CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE payments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE payment_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE reviews CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tickets CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ticket_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE chat_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE coupons CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE coupon_products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE coupon_categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE coupon_usage CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE returns CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE audit_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notifications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE knowledge_base CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE faq CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE policies CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE popups CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE popup_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE password_resets CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE email_verifications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS=1;

ALTER TABLE orders
  MODIFY status ENUM('pending_payment','waiting_confirm','waiting_pickup','packing','shipping','delivered','completed','cancelled','returned','cho_thanh_toan','da_thanh_toan','cho_xac_nhan','dang_dong_goi','dang_van_chuyen','da_giao','hoan_thanh','da_huy','hoan_tra') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting_confirm',
  ADD COLUMN IF NOT EXISTS cancel_reason TEXT NULL AFTER note,
  ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER cancel_reason,
  ADD COLUMN IF NOT EXISTS stock_restored_at DATETIME NULL AFTER cancelled_at;

ALTER TABLE returns
  MODIFY status ENUM('pending','approved','rejected','received','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS type ENUM('refund','exchange','return') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'refund' AFTER user_id,
  ADD COLUMN IF NOT EXISTS detail TEXT NULL AFTER reason,
  ADD COLUMN IF NOT EXISTS evidence_image VARCHAR(255) NULL AFTER detail,
  ADD COLUMN IF NOT EXISTS admin_note TEXT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS decided_at DATETIME NULL AFTER admin_note;

CREATE TABLE IF NOT EXISTS return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  order_item_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE,
  FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS return_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  user_id INT NULL,
  action VARCHAR(80) NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE categories SET name='Giày chạy bộ' WHERE slug='running' OR id=1;
UPDATE categories SET name='Giày lifestyle' WHERE slug='lifestyle' OR id=2;
UPDATE categories SET name='Giày bóng rổ' WHERE slug='basketball' OR id=3;
UPDATE categories SET name='Giày nam' WHERE slug='giay-nam' OR id=4;
UPDATE categories SET name='Giày nữ' WHERE slug='giay-nu' OR id=5;
UPDATE categories SET name='Sneaker' WHERE slug='sneaker' OR id=6;
UPDATE categories SET name='Sandal/Dép' WHERE slug IN ('sandal-dep','sandal') OR id=7;

UPDATE products SET name='AeroRun Lite', description='Giày chạy bộ nhẹ, đệm êm, phù hợp tập luyện hằng ngày.' WHERE id=1;
UPDATE products SET name='RoadFlex Pro', description='Giày road running ổn định, thoáng khí, bám đường tốt.' WHERE id=2;
UPDATE products SET name='Urban Suede', description='Giày lifestyle da lộn mềm, dễ phối đồ hằng ngày.' WHERE id=3;
UPDATE products SET name='Court Jump X', description='Giày bóng rổ cổ cao, hỗ trợ cổ chân và độ bám sân.' WHERE id=4;
UPDATE products SET name='Nike Air Motion Nam', description='Giày nam màu đen, đệm êm, phù hợp đi hằng ngày.' WHERE id=5;
UPDATE products SET name='Adidas Cloudfoam Nữ', description='Giày nữ màu trắng, nhẹ, thoáng khí.' WHERE id=6;
UPDATE products SET name='Nike Zoom Runner', description='Giày chạy bộ xanh navy, hoàn trả năng lượng tốt.' WHERE id=7;
UPDATE products SET name='Puma Velocity Run', description='Giày chạy bộ đỏ, độ bám đường, trọng lượng nhẹ.' WHERE id=8;
UPDATE products SET name='Converse Chuck 70 Classic', description='Sneaker cổ cao màu đen, phong cách cổ điển.' WHERE id=9;
UPDATE products SET name='Vans Old Skool Black', description='Sneaker trượt ván màu đen trắng, dễ phối đồ.' WHERE id=10;
UPDATE products SET name='Nike Court Force', description='Giày bóng rổ cổ cao màu trắng đỏ, hỗ trợ cổ chân.' WHERE id=11;
UPDATE products SET name='Adidas Hoops Elite', description='Giày bóng rổ màu xám, độ bám sân tốt.' WHERE id=12;
UPDATE products SET name='New Balance 574 Grey', description='Giày lifestyle màu xám, form thoải mái.' WHERE id=13;
UPDATE products SET name='Puma Suede Green', description='Giày da lộn màu xanh lá, phong cách retro.' WHERE id=14;
UPDATE products SET name='Adidas Street Run Nam', description='Giày nam màu xanh đen, phù hợp đi làm và đi chơi.' WHERE id=15;
UPDATE products SET name='Nike Bella Soft', description='Giày nữ màu hồng pastel, đế mềm, nhẹ chân.' WHERE id=16;
UPDATE products SET name='Puma Slide Comfort', description='Dép quai ngang màu đen, êm chân, chống trượt.' WHERE id=17;
UPDATE products SET name='Adidas Adilette Aqua', description='Dép thể thao màu xanh, nhanh khô, nhẹ.' WHERE id=18;
UPDATE products SET name='New Balance Fresh Pace', description='Giày chạy bộ màu trắng xanh, đệm dày, ổn định.' WHERE id=19;
UPDATE products SET name='Converse Run Star', description='Sneaker đế cao màu kem, cá tính, nổi bật.' WHERE id=20;
UPDATE products SET name='Vans Era Navy', description='Giày nam canvas xanh navy, bền và gọn.' WHERE id=21;
UPDATE products SET name='New Balance Rose Walk', description='Giày nữ màu hồng nhạt, phù hợp đi bộ nhẹ.' WHERE id=22;
UPDATE products SET name='Puma Rebound Layup', description='Giày bóng rổ màu đen đỏ, đệm chắc, bám sân.' WHERE id=23;
UPDATE products SET name='Nike Dunk Street', description='Sneaker màu trắng đen, dáng streetwear hiện đại.' WHERE id=24;

UPDATE order_items oi JOIN products p ON p.id=oi.product_id SET oi.product_name=p.name WHERE oi.product_name LIKE '%?%';
