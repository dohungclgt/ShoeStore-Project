<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user=current_user();
if(!$user) json_response(['count'=>0,'items'=>[]]);
if(($_GET['action'] ?? '')==='notifications'){ $stmt=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0'); $stmt->execute([$user['id']]); json_response(['count'=>(int)$stmt->fetchColumn()]); }
$stmt=db()->prepare('SELECT code,total,status,created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 10'); $stmt->execute([$user['id']]); json_response(['items'=>$stmt->fetchAll()]);
