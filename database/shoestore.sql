CREATE DATABASE IF NOT EXISTS shoestore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shoestore;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS email_verifications,password_resets,popup_logs,popups,policies,faq,knowledge_base,news,notifications,audit_logs,return_logs,return_items,returns,coupon_usage,coupon_categories,coupon_products,coupons,chat_history,ticket_messages,tickets,review_media,reviews,payment_logs,payments,order_items,orders,inventory_logs,inventory,product_sizes,product_images,products,categories,users,roles;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE roles (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE);
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(30), address TEXT, avatar VARCHAR(255),
  email_verified_at DATETIME NULL,
  status ENUM('active','locked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);
CREATE TABLE categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL UNIQUE, parent_id INT NULL, image VARCHAR(255), FOREIGN KEY(parent_id) REFERENCES categories(id) ON DELETE SET NULL);
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  description TEXT,
  price DECIMAL(12,2) NOT NULL,
  sale_price DECIMAL(12,2) NULL,
  brand VARCHAR(120),
  gender ENUM('men','women','unisex') DEFAULT 'unisex',
  size_range VARCHAR(80),
  image VARCHAR(255),
  featured TINYINT(1) NOT NULL DEFAULT 0,
  best_seller TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX(category_id), FULLTEXT KEY product_search (name, description, brand),
  FOREIGN KEY(category_id) REFERENCES categories(id)
);
CREATE TABLE product_images (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, image VARCHAR(255) NOT NULL, sort_order INT NOT NULL DEFAULT 0, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE);
CREATE TABLE product_sizes (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, size VARCHAR(20) NOT NULL, stock INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY product_size_unique(product_id,size), FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE);
CREATE TABLE inventory (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL UNIQUE, stock INT NOT NULL DEFAULT 0, low_stock_threshold INT NOT NULL DEFAULT 5, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE);
CREATE TABLE inventory_logs (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NULL, type ENUM('import','export','adjust') NOT NULL, quantity INT NOT NULL, note VARCHAR(255), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(product_id) REFERENCES products(id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code VARCHAR(30) NOT NULL UNIQUE,
  subtotal DECIMAL(12,2) NOT NULL,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  vat DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  payment_method ENUM('COD','MOMO','VNPAY') NOT NULL,
  status ENUM('pending_payment','waiting_confirm','waiting_pickup','packing','shipping','delivered','completed','cancelled','returned','cho_thanh_toan','da_thanh_toan','cho_xac_nhan','dang_dong_goi','dang_van_chuyen','da_giao','hoan_thanh','da_huy','hoan_tra') NOT NULL DEFAULT 'waiting_confirm',
  shipping_name VARCHAR(120) NOT NULL, shipping_phone VARCHAR(30) NOT NULL, shipping_address TEXT NOT NULL, note TEXT,
  cancel_reason TEXT NULL, cancelled_at DATETIME NULL, stock_restored_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX(user_id), FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE order_items (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT NOT NULL, product_name VARCHAR(180) NOT NULL, size VARCHAR(20) NULL, price DECIMAL(12,2) NOT NULL, quantity INT NOT NULL, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id));
CREATE TABLE payments (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, provider ENUM('COD','MOMO','VNPAY') NOT NULL, transaction_id VARCHAR(160), amount DECIMAL(12,2) NOT NULL, status ENUM('pending','paid','success','failed','unpaid','refunded') NOT NULL DEFAULT 'pending', payment_attempts INT NOT NULL DEFAULT 0, raw_response JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE);
CREATE TABLE payment_logs (id INT AUTO_INCREMENT PRIMARY KEY, payment_id INT NULL, provider VARCHAR(30) NOT NULL, action VARCHAR(80) NOT NULL, payload JSON NULL, valid_signature TINYINT(1) DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE SET NULL);
CREATE TABLE reviews (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, order_id INT NULL, rating TINYINT NOT NULL, comment TEXT, image VARCHAR(255) NULL, approved TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(product_id) REFERENCES products(id), CHECK(rating BETWEEN 1 AND 5));
CREATE TABLE review_media (id INT AUTO_INCREMENT PRIMARY KEY, review_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, file_type ENUM('image','video') NOT NULL, mime_type VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(review_id) REFERENCES reviews(id) ON DELETE CASCADE);
CREATE TABLE tickets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, subject VARCHAR(180) NOT NULL, status ENUM('open','answered','closed') NOT NULL DEFAULT 'open', attachment VARCHAR(255), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE ticket_messages (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, user_id INT NOT NULL, message TEXT NOT NULL, attachment VARCHAR(255), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE chat_history (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, question TEXT NOT NULL, database_context JSON NULL, ai_response TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE coupons (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, type ENUM('percent','fixed','free_shipping') NOT NULL, value DECIMAL(12,2) NOT NULL DEFAULT 0, min_order DECIMAL(12,2) NOT NULL DEFAULT 0, starts_at DATETIME NULL, ends_at DATETIME NULL, usage_limit INT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE coupon_products (coupon_id INT NOT NULL, product_id INT NOT NULL, PRIMARY KEY(coupon_id, product_id), FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE);
CREATE TABLE coupon_categories (coupon_id INT NOT NULL, category_id INT NOT NULL, PRIMARY KEY(coupon_id, category_id), FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE);
CREATE TABLE coupon_usage (id INT AUTO_INCREMENT PRIMARY KEY, coupon_id INT NOT NULL, user_id INT NOT NULL, order_id INT NOT NULL, used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(coupon_id) REFERENCES coupons(id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(order_id) REFERENCES orders(id));
CREATE TABLE returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('refund','exchange','return') NOT NULL DEFAULT 'refund',
  reason TEXT NOT NULL,
  detail TEXT NULL,
  evidence_image VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected','received','refunded') NOT NULL DEFAULT 'pending',
  admin_note TEXT NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(id),
  FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE return_items (id INT AUTO_INCREMENT PRIMARY KEY, return_id INT NOT NULL, order_item_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE, FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id));
CREATE TABLE return_logs (id INT AUTO_INCREMENT PRIMARY KEY, return_id INT NOT NULL, user_id INT NULL, action VARCHAR(80) NOT NULL, note TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(return_id) REFERENCES returns(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(80) NOT NULL, entity VARCHAR(80) NOT NULL, entity_id INT NULL, data JSON NULL, ip_address VARCHAR(64), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(entity, entity_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(180) NOT NULL, body TEXT, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE knowledge_base (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, content TEXT NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, FULLTEXT KEY kb_search(title, content));
CREATE TABLE news (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL UNIQUE,
  thumbnail VARCHAR(255) NOT NULL,
  excerpt TEXT NOT NULL,
  content MEDIUMTEXT NOT NULL,
  author VARCHAR(120) NOT NULL DEFAULT 'ShoeStore Team',
  tags VARCHAR(255),
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT KEY news_search(title, excerpt, content, tags)
);
CREATE TABLE faq (id INT AUTO_INCREMENT PRIMARY KEY, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, FULLTEXT KEY faq_search(question, answer));
CREATE TABLE policies (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, content TEXT NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, FULLTEXT KEY policy_search(title, content));
CREATE TABLE popups (id INT AUTO_INCREMENT PRIMARY KEY, type ENUM('flash_sale','banner','promotion','announcement') NOT NULL, title VARCHAR(180) NOT NULL, content TEXT, image VARCHAR(255), cta_text VARCHAR(80), cta_link VARCHAR(255), active TINYINT(1) NOT NULL DEFAULT 1, starts_at DATETIME NULL, ends_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE popup_logs (id INT AUTO_INCREMENT PRIMARY KEY, popup_id INT NOT NULL, user_id INT NULL, event ENUM('impression','click') NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(popup_id) REFERENCES popups(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE email_verifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, verified_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);

INSERT INTO roles(name) VALUES ('Super Admin'),('Admin'),('Staff'),('Customer');
INSERT INTO users(role_id,name,email,password,email_verified_at) VALUES
((SELECT id FROM roles WHERE name='Super Admin'),'Super Admin','admin@shoestore.local','$2y$10$ZZ.bmF65aCNaokaILr7u/.G6uMnDQ3jEwtkgJsgYyJub3AST5yl06',NOW()),
((SELECT id FROM roles WHERE name='Customer'),'Demo Customer','customer@shoestore.local','$2y$10$ZZ.bmF65aCNaokaILr7u/.G6uMnDQ3jEwtkgJsgYyJub3AST5yl06',NOW());
INSERT INTO categories(name,slug,image) VALUES
('Running','running','https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=900&q=80'),
('Lifestyle','lifestyle','https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=900&q=80'),
('Basketball','basketball','https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=900&q=80');
INSERT INTO products(category_id,name,slug,description,price,sale_price,brand,gender,size_range,image,featured,best_seller) VALUES
((SELECT id FROM categories WHERE slug='running'),'AeroRun Lite','aerorun-lite','Giay chay bo nhe, dem em, phu hop tap luyen hang ngay.',1850000,1690000,'ShoeStore','unisex','39-44','https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80',1,1),
((SELECT id FROM categories WHERE slug='running'),'RoadFlex Pro','roadflex-pro','Giay road running on dinh, thoang khi, bam duong tot.',2450000,NULL,'ShoeStore','unisex','38-45','https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=900&q=80',1,0),
((SELECT id FROM categories WHERE slug='lifestyle'),'Urban Suede','urban-suede','Giay lifestyle da lon mem, de phoi do hang ngay.',1590000,1290000,'ShoeStore','men','39-43','https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=900&q=80',0,1),
((SELECT id FROM categories WHERE slug='basketball'),'Court Jump X','court-jump-x','Giay bong ro co cao, ho tro co chan va do bam san.',2750000,NULL,'ShoeStore','unisex','40-46','https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=900&q=80',1,1);
INSERT INTO inventory(product_id,stock,low_stock_threshold) SELECT id, 25, 5 FROM products;
INSERT INTO product_sizes(product_id,size,stock)
SELECT p.id, s.size, 4
FROM products p
JOIN (SELECT '38' size UNION SELECT '39' UNION SELECT '40' UNION SELECT '41' UNION SELECT '42' UNION SELECT '43') s;
INSERT INTO coupons(code,type,value,min_order,starts_at,ends_at,usage_limit) VALUES ('WELCOME10','percent',10,500000,NOW(),DATE_ADD(NOW(), INTERVAL 90 DAY),500),('FREESHIP','free_shipping',0,1000000,NOW(),DATE_ADD(NOW(), INTERVAL 90 DAY),500);
INSERT INTO faq(question,answer) VALUES ('Thời gian giao hàng bao lâu?','Đơn hàng nội thành thường giao trong 1-3 ngày làm việc, ngoại tỉnh 3-5 ngày.'),('Có đổi size không?','ShoeStore hỗ trợ đổi size trong 7 ngày nếu sản phẩm còn mới và còn tem mác.');
INSERT INTO policies(title,content) VALUES ('Chính sách hoàn trả','Khách hàng có thể tạo yêu cầu hoàn trả trong 7 ngày sau khi nhận hàng nếu sản phẩm lỗi hoặc giao sai.'),('Bảo mật thanh toán','ShoeStore chỉ lưu trạng thái giao dịch và mã tham chiếu, không lưu thông tin thẻ.');
INSERT INTO knowledge_base(title,content) VALUES ('Tu van giay chay bo','Giay chay bo nen uu tien trong luong nhe, dem em, size vua chan va ton kho con hang.'),('Cham soc giay','Ve sinh bang ban chai mem, tranh phoi nang truc tiep.');
INSERT INTO popups(type,title,content,image,cta_text,cta_link,active,starts_at,ends_at) VALUES ('flash_sale','Flash Sale Running','Giam gia giay chay bo trong tuan nay','https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=900&q=80','Mua ngay','products.php',1,NOW(),DATE_ADD(NOW(), INTERVAL 7 DAY));


DELIMITER //
CREATE TRIGGER trg_inventory_log_after_order
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
  UPDATE inventory SET stock = stock - NEW.quantity WHERE product_id = NEW.product_id;
  INSERT INTO inventory_logs(product_id,type,quantity,note) VALUES(NEW.product_id,'export',NEW.quantity,CONCAT('Order item ', NEW.id));
END//
DELIMITER ;
