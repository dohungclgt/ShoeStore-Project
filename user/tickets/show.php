<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
header('Location: ../tickets.php' . ($id > 0 ? '?ticket_id=' . $id : ''));
exit;
