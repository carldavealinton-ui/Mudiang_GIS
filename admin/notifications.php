<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/../access_control.php';
header('Content-Type: application/json');header('Cache-Control: no-store');$userId=(int)$_SESSION['user_id'];
[$scope,$scopeParams]=complaintAccessScope();$where='n.user_id=? AND '.$scope;$params=array_merge([$userId],$scopeParams);
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['admin_notification_csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')){http_response_code(403);echo json_encode(['error'=>'Invalid request token.']);exit;}
 $q=$pdo->prepare('UPDATE notifications n JOIN complaints c ON c.id=n.complaint_id SET n.is_read=1 WHERE '.$where.' AND n.id<=? AND n.is_read=0');$q->execute(array_merge($params,[max(0,(int)($_POST['through']??0))]));
}elseif($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET, POST');exit;}
$join=' FROM notifications n JOIN complaints c ON c.id=n.complaint_id WHERE '.$where;
$q=$pdo->prepare('SELECT COUNT(*)'.$join.' AND n.is_read=0');$q->execute($params);$count=(int)$q->fetchColumn();
$select='SELECT n.id,n.complaint_id,n.message,n.is_read,n.created_at,n.event_type';$q=$pdo->prepare($select.$join.' ORDER BY n.id DESC LIMIT 20');$q->execute($params);$items=$q->fetchAll(PDO::FETCH_ASSOC);$cursor=$items?(int)$items[0]['id']:0;$incoming=[];
if(isset($_GET['after'])){$after=max(0,(int)$_GET['after']);$q=$pdo->prepare($select.$join.' AND n.id>? ORDER BY n.id ASC LIMIT 100');$q->execute(array_merge($params,[$after]));$incoming=$q->fetchAll(PDO::FETCH_ASSOC);$cursor=$incoming?(int)end($incoming)['id']:$after;}
echo json_encode(['count'=>$count,'items'=>$items,'incoming'=>$incoming,'cursor'=>$cursor]);