<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin','Admin','Staff']);
$id = (int)($_GET['id'] ?? 0);
header('Location: index.php' . ($id > 0 ? '?edit=' . $id : ''));
exit;
