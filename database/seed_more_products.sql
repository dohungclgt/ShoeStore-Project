USE shoestore;

INSERT IGNORE INTO categories(name,slug,image) VALUES
('Giày nam','giay-nam','https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=900&q=80'),
('Giày nữ','giay-nu','https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=900&q=80'),
('Sneaker','sneaker','https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=900&q=80'),
('Sandal/Dép','sandal-dep','https://images.unsplash.com/photo-1603487742131-4160ec999306?auto=format&fit=crop&w=900&q=80');

INSERT IGNORE INTO products(category_id,name,slug,description,price,sale_price,brand,gender,size_range,image,featured,best_seller,status) VALUES
((SELECT id FROM categories WHERE slug='giay-nam'),'Nike Air Motion Nam','nike-air-motion-nam','Giày nam màu đen, đệm êm, phù hợp đi hằng ngày.',2290000,1990000,'Nike','men','39-44','https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80',1,1,'active'),
((SELECT id FROM categories WHERE slug='giay-nu'),'Adidas Cloudfoam Nữ','adidas-cloudfoam-nu','Giày nữ màu trắng, nhẹ, thoáng khí.',1890000,1690000,'Adidas','women','36-40','https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=900&q=80',1,0,'active'),
((SELECT id FROM categories WHERE slug='running'),'Nike Zoom Runner','nike-zoom-runner','Giày chạy bộ xanh navy, hoàn trả năng lượng tốt.',2590000,2390000,'Nike','unisex','38-45','https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=900&q=80',1,1,'active'),
((SELECT id FROM categories WHERE slug='running'),'Puma Velocity Run','puma-velocity-run','Giày chạy bộ đỏ, đế bám đường, trọng lượng nhẹ.',1790000,NULL,'Puma','unisex','38-44','https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=900&q=80',0,1,'active'),
((SELECT id FROM categories WHERE slug='sneaker'),'Converse Chuck 70 Classic','converse-chuck-70-classic','Sneaker cổ cao màu đen, phong cách cổ điển.',1650000,NULL,'Converse','unisex','36-44','https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=900&q=80',1,1,'active'),
((SELECT id FROM categories WHERE slug='sneaker'),'Vans Old Skool Black','vans-old-skool-black','Sneaker trượt ván màu đen trắng, dễ phối đồ.',1590000,1450000,'Vans','unisex','36-44','https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=900&q=80',0,1,'active'),
((SELECT id FROM categories WHERE slug='basketball'),'Nike Court Force','nike-court-force','Giày bóng rổ cổ cao màu trắng đỏ, hỗ trợ cổ chân.',2990000,2690000,'Nike','unisex','40-46','https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=900&q=80',1,1,'active'),
((SELECT id FROM categories WHERE slug='basketball'),'Adidas Hoops Elite','adidas-hoops-elite','Giày bóng rổ màu xám, độ bám sân tốt.',2490000,NULL,'Adidas','men','40-45','https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=900&q=80',0,0,'active'),
((SELECT id FROM categories WHERE slug='lifestyle'),'New Balance 574 Grey','new-balance-574-grey','Giày lifestyle màu xám, form thoải mái.',2190000,1990000,'New Balance','unisex','37-44','https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=900&q=80',1,0,'active'),
((SELECT id FROM categories WHERE slug='lifestyle'),'Puma Suede Green','puma-suede-green','Giày da lộn màu xanh lá, phong cách retro.',1750000,NULL,'Puma','unisex','37-43','https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=900&q=80',0,0,'active'),
((SELECT id FROM categories WHERE slug='giay-nam'),'Adidas Street Run Nam','adidas-street-run-nam','Giày nam màu xanh đen, phù hợp đi làm và đi chơi.',1990000,1790000,'Adidas','men','39-44','https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=900&q=80',0,1,'active'),
((SELECT id FROM categories WHERE slug='giay-nu'),'Nike Bella Soft','nike-bella-soft','Giày nữ màu hồng pastel, đế mềm, nhẹ chân.',2050000,1850000,'Nike','women','35-40','https://images.unsplash.com/photo-1579338559194-a162d19bf842?auto=format&fit=crop&w=900&q=80',1,0,'active'),
((SELECT id FROM categories WHERE slug='sandal-dep'),'Puma Slide Comfort','puma-slide-comfort','Dép quai ngang màu đen, êm chân, chống trượt.',690000,590000,'Puma','unisex','36-44','https://images.unsplash.com/photo-1603487742131-4160ec999306?auto=format&fit=crop&w=900&q=80',0,1,'active'),
((SELECT id FROM categories WHERE slug='sandal-dep'),'Adidas Adilette Aqua','adidas-adilette-aqua','Dép thể thao màu xanh, nhanh khô, nhẹ.',790000,NULL,'Adidas','unisex','36-45','https://images.unsplash.com/photo-1595341888016-a392ef81b7de?auto=format&fit=crop&w=900&q=80',0,0,'active'),
((SELECT id FROM categories WHERE slug='running'),'New Balance Fresh Pace','new-balance-fresh-pace','Giày chạy bộ màu trắng xanh, đệm dày, ổn định.',2350000,2150000,'New Balance','unisex','38-45','https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=900&q=80',1,0,'active'),
((SELECT id FROM categories WHERE slug='sneaker'),'Converse Run Star','converse-run-star','Sneaker đế cao màu kem, cá tính, nổi bật.',2250000,NULL,'Converse','women','36-41','https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=900&q=80',1,0,'active'),
((SELECT id FROM categories WHERE slug='giay-nam'),'Vans Era Navy','vans-era-navy','Giày nam canvas xanh navy, bền và gọn.',1390000,1250000,'Vans','men','39-44','https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=900&q=80',0,0,'active'),
((SELECT id FROM categories WHERE slug='giay-nu'),'New Balance Rose Walk','new-balance-rose-walk','Giày nữ màu hồng nhạt, phù hợp đi bộ nhẹ.',1890000,NULL,'New Balance','women','35-40','https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=900&q=80',0,0,'active'),
((SELECT id FROM categories WHERE slug='basketball'),'Puma Rebound Layup','puma-rebound-layup','Giày bóng rổ màu đen đỏ, đệm chắc, bám sân.',2090000,1890000,'Puma','men','40-45','https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=900&q=80',0,1,'active'),
((SELECT id FROM categories WHERE slug='sneaker'),'Nike Dunk Street','nike-dunk-street','Sneaker màu trắng đen, dáng streetwear hiện đại.',2890000,2590000,'Nike','unisex','37-45','https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=900&q=80',1,1,'active');

INSERT INTO inventory(product_id,stock,low_stock_threshold)
SELECT p.id, 30, 5 FROM products p
LEFT JOIN inventory i ON i.product_id=p.id
WHERE i.id IS NULL;
