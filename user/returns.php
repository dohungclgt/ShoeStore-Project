<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
header('Location: orders.php' . ($id > 0 ? '?return_id=' . $id : ''));
exit;
