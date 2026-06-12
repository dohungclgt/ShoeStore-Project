<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['Super Admin','Admin','Staff']);
$rows=db()->query('SELECT p.id,p.name,i.stock,i.low_stock_threshold FROM products p JOIN inventory i ON i.product_id=p.id ORDER BY p.name')->fetchAll();
json_response(['items'=>$rows]);
