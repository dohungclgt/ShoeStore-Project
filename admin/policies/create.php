<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin','Admin','Staff']);
header('Location: index.php');
exit;
