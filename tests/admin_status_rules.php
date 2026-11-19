<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
session_save_path(sys_get_temp_dir());require __DIR__.'/../config.php';session_write_close();require __DIR__.'/../workflow_support.php';
function statusCheck($condition,$label){if(!$condition)throw new RuntimeException($label);echo "PASS: $label\n";}
function statusRequest($path,$data=null,$cookie='') {
 $options=['http'=>['method'=>$data===null?'GET':'POST','header'=>"Cookie: $cookie",'ignore_errors'=>true,'follow_location'=>0]];
 if($data!==null){$options['http']['header'].="\r\nContent-Type: application/x-www-form-urlencoded";$options['http']['content']=http_build_query($data);}
 $body=file_get_contents('http://localhost/System/'.$path,false,stream_context_create($options));return [$body,$http_response_header];
}
$suffix=bin2hex(random_bytes(6));$password=bin2hex(random_bytes(12));$users=[];$report=0;$names=[];
try {
 foreach(['admin','staff','resident'] as $role){$name='status_rule_test_'.$role.'_'.$suffix;$names[]=$name;$q=$pdo->prepare('INSERT INTO users(username,password,full_name,contact,address,role) VALUES(?,?,?,?,?,?)');$q->execute([$name,password_hash($password,PASSWORD_DEFAULT),'Status fixture '.$role,'09123456789','Fixture address',$role]);$users[$role]=(int)$pdo->lastInsertId();[, $headers]=statusRequest('index.php',['username'=>$name,'password'=>$password]);foreach($headers as $header)if(preg_match('/Set-Cookie: (PHPSESSID=[^;]+)/i',$header,$match))$cookies[$role]=$match[1];}
 $q=$pdo->prepare('INSERT INTO complaints(user_id,complaint_type,description,latitude,longitude,address,complainant_name,complainant_contact,assigned_staff_id,assigned_to) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$users['resident'],'Road Damage','Status rule fixture',7.18,125.62,'Fixture','Status fixture resident','09123456789',$users['staff'],'Status fixture staff']);$report=(int)$pdo->lastInsertId();$path='admin/edit_complaint.php?id='.$report;
 $load=static function()use($pdo,$report){$q=$pdo->prepare('SELECT * FROM complaints WHERE id=?');$q->execute([$report]);return $q->fetch(PDO::FETCH_ASSOC);};
 [$page]=statusRequest($path,null,$cookies['admin']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$page,$token);
 $submit=static function($status,$priority,$role='admin')use($load,$token,$users,$cookies,$path){$current=$load();return statusRequest($path,['csrf_token'=>$token[1],'version'=>reportVersion($current),'status'=>$status,'priority'=>$priority,'assigned_staff_id'=>$users['staff'],'due_date'=>'','resolution_notes'=>$status==='resolved'?'Fixture repairs completed.':'','remark'=>''],$cookies[$role]);};
 statusCheck(!preg_match('/name="status" value="filed"[^>]*disabled/',$page),'New report allows Filed');
 [, $headers]=$submit('acknowledged','medium');statusCheck(str_contains($headers[0],'302'),'Administrator acknowledges report');$acknowledgedAt=$load()['acknowledged_at'];
 [$page]=statusRequest($path,null,$cookies['admin']);statusCheck((bool)preg_match('/name="status" value="filed"[^>]*disabled/',$page),'Filed is disabled after acknowledgment');
 preg_match('/<select name="priority"[^>]*>/',$page,$priorityControl);statusCheck(!str_contains($priorityControl[0],'disabled'),'Administrator importance control remains enabled');
 [, $headers]=$submit('filed','low');statusCheck(str_contains($headers[0],'422'),'Server rejects return to Filed');$current=$load();statusCheck($current['status']==='acknowledged'&&$current['priority']==='medium','Rejected change leaves status and importance intact');
 [, $headers]=$submit('acknowledged','high');statusCheck(str_contains($headers[0],'302')&&$load()['priority']==='high','Administrator changes importance after acknowledgment');
 foreach(['processing','resolved'] as $status){[, $headers]=$submit($status,'high');statusCheck(str_contains($headers[0],'302'),'Administrator advances to '.$status);[, $headers]=$submit('filed','high');statusCheck(str_contains($headers[0],'422')&&$load()['status']===$status,'Return to Filed blocked from '.$status);[, $headers]=$submit($status,'low');statusCheck(str_contains($headers[0],'302')&&$load()['priority']==='low','Importance remains editable at '.$status);}
 statusCheck($load()['acknowledged_at']===$acknowledgedAt,'Acknowledgment timestamp preserved');
 [$page]=statusRequest($path,null,$cookies['staff']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$page,$staffToken);$current=$load();
 preg_match('/<select name="priority"[^>]*>/',$page,$staffPriorityControl);statusCheck(!str_contains($staffPriorityControl[0],'disabled'),'Assigned staff importance control is enabled');
 $staffUpdate=['csrf_token'=>$staffToken[1],'version'=>reportVersion($current),'status'=>'resolved','priority'=>'high','assigned_staff_id'=>$users['staff'],'resolution_notes'=>$current['resolution_notes']];
 [, $headers]=statusRequest($path,$staffUpdate,$cookies['staff']);statusCheck(str_contains($headers[0],'302')&&$load()['priority']==='high','Assigned staff can update importance');
 $current=$load();$staffUpdate['version']=reportVersion($current);$staffUpdate['status']='filed';
 [, $headers]=statusRequest($path,$staffUpdate,$cookies['staff']);statusCheck(str_contains($headers[0],'422')&&$load()['status']==='resolved','Assigned staff cannot return report to Filed');
 $q=$pdo->prepare("SELECT COUNT(*) FROM complaint_activity WHERE complaint_id=? AND actor_id=? AND event_type='importance'");$q->execute([$report,$users['staff']]);statusCheck((int)$q->fetchColumn()===1,'Staff importance update records actor in history');
 echo "Admin status and importance checks passed.\n";
} finally {
 if($report){$q=$pdo->prepare('DELETE FROM complaints WHERE id=?');$q->execute([$report]);}
 foreach($users as $id){$q=$pdo->prepare('DELETE FROM users WHERE id=?');$q->execute([$id]);}
 foreach($names as $name){$q=$pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?');foreach(['127.0.0.1','::1'] as $ip)$q->execute([hash('sha256',$name.'|'.$ip)]);}
}
