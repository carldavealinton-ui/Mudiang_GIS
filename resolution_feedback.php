<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/workflow_support.php';
if(($_SESSION['role']??'')!=='resident'){http_response_code(403);exit('Resident access required.');}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
if(!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['workflow_csrf']??'',$_POST['csrf_token'])){http_response_code(403);exit('Invalid request token.');}
$id=(int)($_POST['complaint_id']??0);
try {
 $pdo->beginTransaction();$q=$pdo->prepare('SELECT * FROM complaints WHERE id=? AND user_id=? FOR UPDATE');$q->execute([$id,$_SESSION['user_id']]);$c=$q->fetch(PDO::FETCH_ASSOC);
 if(!$c){http_response_code(404);throw new InvalidArgumentException('Report not found.');}
 if($c['status']!=='resolved'||$c['resident_confirmed_at']||!hash_equals(reportVersion($c),(string)($_POST['version']??'')))throw new InvalidArgumentException('This report changed. Reload its timeline before responding.');
 $action=$_POST['action']??'';
 if($action==='confirm'){$q=$pdo->prepare('UPDATE complaints SET resident_confirmed_at=NOW() WHERE id=?');$q->execute([$id]);recordReportActivity($pdo,$id,'confirmation','Resident confirmed the resolution.');}
 elseif($action==='reopen'){
  $reason=is_string($_POST['reason']??null)?trim($_POST['reason']):'';if($reason===''||mb_strlen($reason)>2000)throw new InvalidArgumentException('Please explain why the concern remains unresolved (up to 2,000 characters).');
  $q=$pdo->prepare("UPDATE complaints SET status='processing',resolved_at=NULL,resident_confirmed_at=NULL,reopen_count=reopen_count+1 WHERE id=?");$q->execute([$id]);recordReportActivity($pdo,$id,'reopen','Resident requested reopening: '.$reason);
 }else throw new InvalidArgumentException('Choose a valid resolution response.');
 $recipients=$pdo->prepare("SELECT id FROM users WHERE (role='admin' OR (role='staff' AND id=?)) AND is_active=1");$recipients->execute([$c['assigned_staff_id']]);
 foreach($recipients->fetchAll(PDO::FETCH_COLUMN) as $staff)workflowNotice($pdo,(int)$staff,$id,'Resident '.($action==='confirm'?'confirmed resolution':'requested reopening').' of report #'.$id.'.');
 $pdo->commit();header('Location: complaint_conversation.php?id='.$id.'&feedback=1');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if(http_response_code()!==404)http_response_code(422);exit($e instanceof InvalidArgumentException?htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'):'Unable to save feedback.');}
