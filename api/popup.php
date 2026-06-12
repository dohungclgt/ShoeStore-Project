<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id=(int)($_GET['id'] ?? 0); $event=$_GET['action'] ?? '';
if($id && in_array($event,['impression','click'],true)){ db()->prepare('INSERT INTO popup_logs(popup_id,user_id,event) VALUES(?,?,?)')->execute([$id,$_SESSION['user_id'] ?? null,$event]); }
json_response(['ok'=>true]);
