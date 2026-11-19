<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir()); require dirname(__DIR__) . '/config.php'; session_write_close(); require dirname(__DIR__).'/workflow_support.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function request($path,$data=null,$cookie='',$headers=[]){
 $options=['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",array_merge(["Cookie: $cookie"],$headers)),'ignore_errors'=>true,'follow_location'=>0]];
 if($data!==null){$options['http']['header'].="\r\nContent-Type: application/x-www-form-urlencoded";$options['http']['content']=http_build_query($data);}
 $body=file_get_contents('http://localhost/System/'.$path,false,stream_context_create($options));return[$body,$http_response_header];
}
function login($name,$password){[, $headers]=request('index.php',['username'=>$name,'password'=>$password]);$cookie=null;foreach($headers as $header)if(preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i',$header,$match))$cookie=$match[1];if($cookie)return$cookie;throw new RuntimeException('Login cookie unavailable');}
function api($id,$data,$cookie,$token){return request('complaint_messages.php?complaint_id='.$id,$data,$cookie,['X-CSRF-Token: '.$token]);}
$users=[];$complaints=[];$evidencePath='';
try{
 $suffix=bin2hex(random_bytes(6));$password=bin2hex(random_bytes(12));
 foreach(['resident','other','admin','staff','admin2'] as $role){$name='message_test_'.$role.'_'.$suffix;$q=$pdo->prepare('INSERT INTO users(username,password,full_name,contact,address,role) VALUES(?,?,?,?,?,?)');$q->execute([$name,password_hash($password,PASSWORD_DEFAULT),'Test '.$role,'09000000000','Test address',$role==='other'?'resident':($role==='admin2'?'admin':$role)]);$users[$role]=(int)$pdo->lastInsertId();$cookies[$role]=login($name,$password);}
 foreach(['resident','other'] as $role){$q=$pdo->prepare('INSERT INTO complaints(user_id,complaint_type,description,latitude,longitude,address,complainant_name,complainant_contact) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$users[$role],'Road Damage','Temporary conversation test',7.18,125.62,'Test landmark','Test '.$role,'09000000000']);$complaints[$role]=(int)$pdo->lastInsertId();}
 $id=$complaints['resident'];
 $staffName='Test staff (@message_test_staff_'.$suffix.')';$assign=$pdo->prepare('UPDATE complaints SET assigned_staff_id=?,assigned_to=? WHERE id=?');$assign->execute([$users['staff'],$staffName,$id]);
 $image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');$evidencePath=dirname(__DIR__).'/storage/evidence/scope_'.$suffix.'.bin';file_put_contents($evidencePath,$image);
 $q=$pdo->prepare('INSERT INTO complaint_evidence(complaint_id,path,original_name,mime_type,size) VALUES(?,?,?,?,?)');$q->execute([$id,'evidence/'.basename($evidencePath),'Scope fixture.png','image/png',strlen($image)]);$evidenceId=(int)$pdo->lastInsertId();
 [$body]=request('evidence.php?id='.$evidenceId,null,$cookies['staff']);check($body===$image,'Assigned staff can view report evidence');
 [$page]=request('admin/staff_dashboard.php',null,$cookies['staff']);check(str_contains($page,'Staff workspace')&&str_contains($page,'edit_complaint.php?id='.$id)&&!str_contains($page,'edit_complaint.php?id='.$complaints['other']),'Staff workspace shows only assigned reports');
 foreach(['residents.php','operations.php','annual_report.php','staff.php','account_access.php'] as $restricted){[, $headers]=request('admin/'.$restricted,null,$cookies['staff']);check(str_contains($headers[0],'403'),'Staff blocked from administrator page '.$restricted);}
 foreach(['admin/edit_complaint.php?id=','admin/messages.php?id=','complaint_messages.php?complaint_id='] as $path){[, $headers]=request($path.$complaints['other'],null,$cookies['staff']);check(str_contains($headers[0],'404'),'Staff cannot access unassigned report through '.$path);}
 [, $headers]=request('get_complaints.php');check(str_contains($headers[0],'403'),'Anonymous complaint-map data access blocked');
 [$body]=request('get_complaints.php',null,$cookies['resident']);$mapped=json_decode($body,true);check(count($mapped)===1&&(int)$mapped[0]['id']===$id,'Resident map API exposes only owned reports');
 foreach(['resident','admin','staff'] as $role){
  $path=$role==='resident'?'messages.php?id='.$id:'admin/messages.php?id='.$id;
  [$body]=request($path,null,$cookies[$role]);check(str_contains($body,'messages-workspace')&&str_contains($body,'conversation-card')&&!str_contains($body,'Fatal error'),'Dedicated Messages page renders selected report for '.$role);
 }
 [$body]=request('message_inbox.php',null,$cookies['resident']);$inbox=json_decode($body,true,512,JSON_THROW_ON_ERROR);
 check(count($inbox['items'])===1&&(int)$inbox['items'][0]['id']===$id,'Resident inbox lists only owned reports');
 [, $headers]=request('messages.php?id='.$complaints['other'],null,$cookies['resident']);check(str_contains($headers[0],'404'),'Messages page rejects another resident conversation');
 [, $headers]=request('admin/messages.php',null,$cookies['resident']);check(str_contains($headers[0],'403'),'Resident cannot access admin Messages page');
 [, $headers]=request('message_inbox.php');check(str_contains($headers[0],'403'),'Anonymous inbox access blocked');
 [$body]=request('message_inbox.php?q=missing_'.$suffix,null,$cookies['resident']);check(json_decode($body,true)['total']===0,'Inbox search handles no matches');
 [$body]=request('messages.php?filter=unread',null,$cookies['resident']);check(str_contains($body,'caught up'),'Unread inbox has a clear empty state');
 foreach(['resident','admin','staff'] as $role){$path=$role==='resident'?'complaint_conversation.php?id='.$id:'admin/edit_complaint.php?id='.$id;[$page]=request($path,null,$cookies[$role]);check(str_contains($page,'conversation-card')&&!str_contains($page,'Fatal error'),'Conversation panel loads for '.$role);preg_match('/data-token="([a-f0-9]+)"/',$page,$match);$tokens[$role]=$match[1];}
 [$body,$headers]=request('complaint_messages.php?complaint_id='.$id);check(str_contains($headers[0],'403'),'Anonymous conversation access blocked');
 [$body,$headers]=request('complaint_messages.php?complaint_id='.$id,null,$cookies['other']);check(str_contains($headers[0],'404'),'Unrelated resident cannot read conversation');
 [$body]=request('complaint_messages.php?complaint_id='.$id,null,$cookies['resident']);check(json_decode($body,true)['items']===[],'New conversation starts empty');
 [$review]=request('admin/edit_complaint.php?id='.$id,null,$cookies['admin']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$review,$csrf);
 [$review2]=request('admin/edit_complaint.php?id='.$id,null,$cookies['admin2']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$review2,$csrf2);
 $q=$pdo->prepare('SELECT * FROM complaints WHERE id=?');$q->execute([$id]);$before=$q->fetch(PDO::FETCH_ASSOC);
 check($before['status']==='filed'&&str_contains($review,'status-buttons'),'New reports start Filed and show lifecycle buttons');
 $version=reportVersion($before);
 $update=['csrf_token'=>$csrf[1],'version'=>$version,'status'=>'acknowledged','priority'=>'medium','assigned_staff_id'=>$users['staff'],'resolution_notes'=>'','remark'=>'Admin one inspected the concern.'];
 [, $headers]=request('admin/edit_complaint.php?id='.$id,$update,$cookies['admin']);check(str_contains($headers[0],'302'),'Admin one acknowledges report and adds remark');
 $stale=$update;$stale['status']='processing';$stale['csrf_token']=$csrf2[1];[, $headers]=request('admin/edit_complaint.php?id='.$id,$stale,$cookies['admin2']);check(str_contains($headers[0],'422'),'Concurrent stale update is rejected');
 foreach(['processing','resolved'] as $status){$q->execute([$id]);$current=$q->fetch(PDO::FETCH_ASSOC);$update['version']=reportVersion($current);$update['status']=$status;$update['resolution_notes']=$status==='resolved'?'Road repairs completed and inspected.':'';$update['csrf_token']=$csrf2[1];$update['remark']='Admin two update: '.$status;[, $headers]=request('admin/edit_complaint.php?id='.$id,$update,$cookies['admin2']);check(str_contains($headers[0],'302'),'Admin two moves report to '.$status);}
 $q=$pdo->prepare('SELECT * FROM complaint_activity WHERE complaint_id=? ORDER BY id');$q->execute([$id]);$activity=$q->fetchAll(PDO::FETCH_ASSOC);
 check(count($activity)===7&&$activity[0]['actor_id']==$users['admin']&&$activity[2]['actor_id']==$users['admin2'],'Status and remark actions retain distinct admin attribution');
 [$body]=request('admin/activity.php',null,$cookies['admin2']);check(str_contains($body,'Admin one inspected')&&str_contains($body,'Admin two update'),'Global activity page shows both admin actions');
 $auditFilters='actor='.$users['admin2'].'&type=remark&from='.date('Y-m-d',strtotime('-1 day')).'&to='.date('Y-m-d',strtotime('+1 day'));
 [$body]=request('admin/activity.php?'.$auditFilters,null,$cookies['admin']);
 check(str_contains($body,'Admin two update: resolved')&&str_contains($body,'Current status: Resolved')&&!str_contains($body,'<p>Admin one inspected'),'Activity filters separate admin actions and show resident report context');
 [$csv,$headers]=request('admin/activity.php?'.$auditFilters.'&export=csv',null,$cookies['admin']);
 $rows=array_values(array_filter(explode("\n",trim($csv))));
 check(count($rows)===3&&str_contains($csv,'Admin two update: resolved')&&str_contains($csv,'Test resident')&&!str_contains($csv,'Admin one inspected'),'CSV exports all matching accountability records');
 [, $headers]=request('admin/activity.php?from=2026-02-31',null,$cookies['admin']);check(str_contains($headers[0],'422'),'Invalid accountability date filters rejected');
 [, $headers]=request('admin/activity.php?export=csv',null,$cookies['resident']);check(str_contains($headers[0],'403'),'Residents cannot export staff activity logs');
 [, $headers]=request('admin/activity.php',null,$cookies['resident']);check(str_contains($headers[0],'403'),'Activity page restricted to staff');
 [$body]=request('complaint_conversation.php?id='.$id,null,$cookies['resident']);check(str_contains($body,'Staff remarks')&&str_contains($body,'Admin one inspected'),'Resident can read appended staff remarks');
 check(str_contains($body,'report-tracking-page')&&str_contains($body,'id="conversation"')&&str_contains($body,'data-complaint="'.$id.'"'),'Report tracking restores the conversation for the selected report');
 check(str_contains($body,'id="report-details"')&&str_contains($body,'id="report-progress"')&&str_contains($body,'href="#conversation"'),'Report tracking navigation links to details, progress, and conversation');
 $q=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');$q->execute([$users['resident']]);
 $send=['action'=>'send','body'=>'Please check this location. <img src=x onerror=alert(1)>','client_token'=>bin2hex(random_bytes(16))];
 [$body,$headers]=api($id,$send,$cookies['resident'],$tokens['resident']);$result=json_decode($body,true,512,JSON_THROW_ON_ERROR);check(($result['ok']??false)===true,'Resident can send a message');$message=(int)$result['id'];
 [$again]=api($id,$send,$cookies['resident'],$tokens['resident']);check((int)json_decode($again,true)['id']===$message,'Retry returns the original message');
 $q=$pdo->prepare('SELECT COUNT(*) FROM complaint_messages WHERE complaint_id=?');$q->execute([$id]);check((int)$q->fetchColumn()===1,'Retry does not create duplicate messages');
 foreach(['admin','staff'] as $role){[$body]=request('admin/notifications.php',null,$cookies[$role]);$alerts=json_decode($body,true,512,JSON_THROW_ON_ERROR);check($alerts['count']===1&&$alerts['items'][0]['event_type']==='message','New resident message notifies '.$role.' once');}
 [$body]=request('complaint_messages.php?complaint_id='.$id,null,$cookies['admin']);$items=json_decode($body,true)['items'];check($items[0]['body']===$send['body']&&$items[0]['sender_role']==='resident','Message text and sender identity delivered without HTML interpolation');
 [$body]=request('resident_dashboard.php',null,$cookies['resident']);check(str_contains($body,'Message staff'),'Resident dashboard links to report conversations');
 [$body]=request('admin/dashboard.php',null,$cookies['admin']);check(str_contains($body,'1 new'),'Admin complaint list shows unread conversation badge');
 [$inboxBody]=request('message_inbox.php?filter=unread&q='.$id,null,$cookies['admin']);$inbox=json_decode($inboxBody,true,512,JSON_THROW_ON_ERROR);
 $matching=array_values(array_filter($inbox['items'],fn($thread)=>(int)$thread['id']===$id));check(count($matching)===1&&(int)$matching[0]['unread']===1&&$matching[0]['last_body']===$send['body'],'Unread inbox shows latest preview and unread count');
 api($id,['action'=>'read','through'=>$message],$cookies['admin'],$tokens['admin']);
 [$body]=request('admin/notifications.php',null,$cookies['admin']);check(json_decode($body,true)['count']===0,'Reading conversation marks the viewing admin notification read');
 [$body]=request('admin/notifications.php',null,$cookies['staff']);check(json_decode($body,true)['count']===1,'Other staff read state stays independent');
 $reply=['action'=>'send','body'=>'We have received your details.','client_token'=>bin2hex(random_bytes(16))];[$body]=api($id,$reply,$cookies['admin'],$tokens['admin']);$replyId=json_decode($body,true)['id'];check(isset($replyId),'Administrator can reply');
 $reply['body']='I will coordinate with the assigned team.';$reply['client_token']=bin2hex(random_bytes(16));[$body]=api($id,$reply,$cookies['staff'],$tokens['staff']);check(json_decode($body,true)['ok']===true,'Staff can reply');
 $activityQuery=$pdo->prepare("SELECT actor_id,source_message_id FROM complaint_activity WHERE complaint_id=? AND event_type='message' ORDER BY id");$activityQuery->execute([$id]);$messageActivity=$activityQuery->fetchAll(PDO::FETCH_ASSOC);
 check(count($messageActivity)===2&&(int)$messageActivity[0]['actor_id']===$users['admin']&&(int)$messageActivity[1]['actor_id']===$users['staff'],'Staff message activity retains each sender identity');
 check(count(array_unique(array_column($messageActivity,'source_message_id')))===2,'Staff message activity links to the original messages');
 [$body]=request('get_notifications.php',null,$cookies['resident']);$alerts=json_decode($body,true);$messageAlerts=array_filter($alerts['items'],static fn($item)=>$item['event_type']==='message'&&!$item['is_read']);check(count($messageAlerts)===2,'Admin and staff replies notify the resident');
 [$body]=request('resident_message_notifications.php',null,$cookies['resident']);$messageAlerts=json_decode($body,true);check($messageAlerts['count']===2&&count($messageAlerts['items'])===2,'Resident message icon counts unread admin and staff replies');
 [$body]=request('resident_message_notifications.php?after='.$messageAlerts['cursor'],null,$cookies['resident']);check(json_decode($body,true)['items']===[],'Message popup cursor prevents repeated alerts');
 [$body]=request('resident_message_notifications.php',null,$cookies['other']);check(json_decode($body,true)['count']===0&&json_decode($body,true)['items']===[],'Message alerts remain private to the report owner');
 [, $headers]=request('resident_message_notifications.php',null,$cookies['admin']);check(str_contains($headers[0],'403'),'Resident message alerts reject administrator access');
 api($id,['action'=>'read','through'=>$replyId],$cookies['resident'],$tokens['resident']);[$body]=request('resident_message_notifications.php',null,$cookies['resident']);check(json_decode($body,true)['count']===1,'Read action preserves replies newer than displayed cursor');
 [$body,$headers]=api($id,$send,$cookies['resident'],'invalid');check(str_contains($headers[0],'403'),'Missing or invalid CSRF token rejected');
 $bad=$send;$bad['body']=' ';$bad['client_token']=bin2hex(random_bytes(16));[, $headers]=api($id,$bad,$cookies['resident'],$tokens['resident']);check(str_contains($headers[0],'422'),'Blank messages rejected');
 $bad['body']=str_repeat('x',2001);[, $headers]=api($id,$bad,$cookies['resident'],$tokens['resident']);check(str_contains($headers[0],'422'),'Messages over 2000 characters rejected');
 $otherId=$complaints['other'];[, $headers]=api($otherId,$send,$cookies['resident'],$tokens['resident']);check(str_contains($headers[0],'404'),'Resident cannot send to another resident report');
 $changed=$send;$changed['body']='Changed retry body';[, $headers]=api($id,$changed,$cookies['resident'],$tokens['resident']);check(str_contains($headers[0],'409'),'Reused request token with changed message rejected');
 $q=$pdo->prepare('INSERT INTO complaint_messages(complaint_id,user_id,body,client_token) VALUES(?,?,?,?)');for($i=0;$i<110;$i++)$q->execute([$id,$users['resident'],'Earlier history '.$i,bin2hex(random_bytes(16))]);
 [$body]=request('complaint_messages.php?complaint_id='.$id,null,$cookies['resident']);$history=json_decode($body,true);check(count($history['items'])===100&&$history['has_older'],'Latest history is bounded to 100 messages');
 [$body]=request('complaint_messages.php?complaint_id='.$id.'&before='.$history['items'][0]['id'],null,$cookies['resident']);$earlier=json_decode($body,true);check(count($earlier['items'])===13&&!$earlier['has_older'],'Earlier-message pagination preserves full history');
 [$body]=request('complaint_messages.php?complaint_id='.$id.'&after='.$message,null,$cookies['admin']);$incoming=json_decode($body,true);check(count($incoming['items'])===100&&$incoming['has_more'],'Incoming polling catches up safely in batches');
 foreach(['resident','staff'] as $role){[, $headers]=request('admin/staff.php',null,$cookies[$role]);check(str_contains($headers[0],'403'),'Staff registration blocked for '.$role);}
 [$page]=request('admin/staff.php',null,$cookies['admin']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$page,$staffCsrf);
 check(isset($staffCsrf[1])&&str_contains($page,'Team directory'),'Administrator can open staff registration and directory');
 $registration=['csrf_token'=>$staffCsrf[1],'full_name'=>'Registered Staff '.$suffix,'username'=>'registered_staff_'.$suffix,'password'=>$password,'password_confirmation'=>$password,'email'=>'','contact'=>'09000000000','role'=>'admin'];
 $invalid=$registration;$invalid['csrf_token']='invalid';[, $headers]=request('admin/staff.php',$invalid,$cookies['admin']);check(str_contains($headers[0],'403'),'Staff registration rejects invalid CSRF');
 $invalid=$registration;$invalid['password_confirmation']='mismatch';[, $headers]=request('admin/staff.php',$invalid,$cookies['admin']);check(str_contains($headers[0],'422'),'Staff registration validates password confirmation');
 [, $headers]=request('admin/staff.php',$registration,$cookies['admin']);
 $q=$pdo->prepare('SELECT * FROM users WHERE username=?');$q->execute([$registration['username']]);$registered=$q->fetch(PDO::FETCH_ASSOC);
 if($registered)$users['registered']=(int)$registered['id'];
 check(str_contains($headers[0],'302')&&$registered['role']==='staff'&&password_verify($password,$registered['password']),'Staff account stores hashed password and cannot escalate submitted role');
 [, $headers]=request('admin/staff.php',$registration,$cookies['admin']);check(str_contains($headers[0],'422'),'Duplicate staff usernames rejected');
 $registeredCookie=login($registration['username'],$password);
 [, $headers]=request('admin/dashboard.php',null,$registeredCookie);check(str_contains($headers[0],'302'),'New staff must replace temporary password before report access');
 [$page]=request('account.php',null,$registeredCookie);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$page,$passwordToken);
 $newPassword=bin2hex(random_bytes(14));
 [, $headers]=request('account.php',['csrf_token'=>$passwordToken[1],'current_password'=>$password,'password'=>$newPassword,'password_confirmation'=>$newPassword],$registeredCookie);check(str_contains($headers[0],'302'),'Staff can securely replace their temporary password');
 $registeredCookie=login($registration['username'],$newPassword);
 $assign->execute([$registered['id'],$registration['full_name'].' (@'.$registration['username'].')',$id]);
 [, $headers]=request('evidence.php?id='.$evidenceId,null,$cookies['staff']);check(str_contains($headers[0],'404'),'Reassignment revokes previous staff evidence access');
 [, $headers]=request('complaint_messages.php?complaint_id='.$id,null,$cookies['staff']);check(str_contains($headers[0],'404'),'Reassignment revokes previous staff conversation access');
 [$inbox]=request('message_inbox.php',null,$cookies['staff']);check(json_decode($inbox,true)['total']===0,'Reassigned report disappears from former staff inbox');
 [$alerts]=request('admin/notifications.php',null,$cookies['staff']);check(json_decode($alerts,true)['items']===[],'Notifications no longer expose reports reassigned to another staff member');
 [$page]=request('admin/edit_complaint.php?id='.$id,null,$registeredCookie);preg_match('/data-token="([a-f0-9]+)"/',$page,$registeredToken);
 [$body]=api($id,['action'=>'send','body'=>'Registered staff accountability check.','client_token'=>bin2hex(random_bytes(16))],$registeredCookie,$registeredToken[1]);check(json_decode($body,true)['ok']===true,'Registered staff can sign in and respond to resident reports');
 $q=$pdo->prepare('SELECT actor_name FROM complaint_activity WHERE complaint_id=? AND actor_id=?');$q->execute([$id,$registered['id']]);check($q->fetchColumn()===$registration['full_name'].' (@'.$registration['username'].')','Registered staff activity records full name and unique username');
 [$page]=request('admin/staff.php',null,$cookies['admin']);check(str_contains($page,$registration['full_name'])&&str_contains($page,'activity.php?actor='.$registered['id']),'Staff directory links to individual accountability history');
 [$page]=request('admin/edit_complaint.php?id='.$otherId,null,$cookies['admin']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$page,$workflowToken);
 $q=$pdo->prepare('SELECT * FROM complaints WHERE id=?');$q->execute([$otherId]);$other=$q->fetch(PDO::FETCH_ASSOC);
 $update=['csrf_token'=>$workflowToken[1],'version'=>reportVersion($other),'status'=>'acknowledged','priority'=>'high','assigned_staff_id'=>$registered['id'],'due_date'=>date('Y-m-d',strtotime('-1 day')),'resolution_notes'=>'','remark'=>''];
 [, $headers]=request('admin/edit_complaint.php?id='.$otherId,$update,$cookies['admin']);check(str_contains($headers[0],'302'),'Complaint can be assigned to a registered account with a deadline');
 $q->execute([$otherId]);$other=$q->fetch(PDO::FETCH_ASSOC);check((int)$other['assigned_staff_id']===(int)$registered['id']&&$other['acknowledged_at']!==null,'Assignment uses account ID and acknowledgement milestone is recorded');
 [$staffPage]=request('admin/edit_complaint.php?id='.$otherId,null,$registeredCookie);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$staffPage,$staffUpdateToken);
 $staffUpdate=['csrf_token'=>$staffUpdateToken[1],'version'=>reportVersion($other),'status'=>'processing','resolution_notes'=>'','remark'=>'Assigned staff began investigation.'];
 foreach(['assigned_staff_id'=>$users['admin'],'due_date'=>date('Y-m-d',strtotime('+3 days'))] as $key=>$value){$badUpdate=$staffUpdate;$badUpdate[$key]=$value;[, $headers]=request('admin/edit_complaint.php?id='.$otherId,$badUpdate,$registeredCookie);check(str_contains($headers[0],'422'),'Staff cannot change administrator-controlled '.$key);}
 $badUpdate=$staffUpdate;$badUpdate['status']='resolved';$badUpdate['resolution_notes']='Attempted to skip processing.';[, $headers]=request('admin/edit_complaint.php?id='.$otherId,$badUpdate,$registeredCookie);check(str_contains($headers[0],'422'),'Staff cannot skip complaint lifecycle stages');
 [, $headers]=request('admin/edit_complaint.php?id='.$otherId,$staffUpdate,$registeredCookie);check(str_contains($headers[0],'302'),'Assigned staff can process a complaint and add a remark');
 $q->execute([$otherId]);$other=$q->fetch(PDO::FETCH_ASSOC);
 [, $headers]=request('admin/activity.php?export=csv',null,$registeredCookie);check(str_contains($headers[0],'403'),'Staff cannot export system activity');
 [$staffLog]=request('admin/activity.php',null,$registeredCookie);check(str_contains($staffLog,'Assigned staff began investigation.')&&!str_contains($staffLog,'Admin one inspected the concern.'),'Staff activity page shows only own recorded actions');
 check(!str_contains($staffLog,'Export CSV')&&!str_contains($staffLog,'href="operations.php"')&&!str_contains($staffLog,'href="staff.php"'),'Staff UI hides administrator navigation and export controls');
 [$page]=request('admin/operations.php?owner='.$registered['id'].'&overdue=1',null,$cookies['admin']);check(str_contains($page,'#'.$otherId)&&str_contains($page,'Overdue')&&str_contains($page,'Staff performance'),'Operations filters show assigned overdue reports and performance');
 [, $headers]=request('admin/operations.php?from=2026-02-31',null,$cookies['admin']);check(str_contains($headers[0],'422'),'Operations rejects invalid date filters');
 [, $headers]=request('admin/operations.php',null,$cookies['resident']);check(str_contains($headers[0],'403'),'Operations reports restricted to staff');
 [$body]=request('admin/notifications.php',null,$registeredCookie);check(str_contains($body,'Overdue report #'.$otherId),'Assigned staff automatically receives an overdue alert');
 [$body2]=request('admin/notifications.php',null,$registeredCookie);check(json_decode($body,true)['count']===json_decode($body2,true)['count'],'Overdue polling does not duplicate alerts');
 $update['status']='resolved';$update['version']=reportVersion($other);
 [, $headers]=request('admin/edit_complaint.php?id='.$otherId,$update,$cookies['admin']);check(str_contains($headers[0],'422'),'Resolution requires closing details');
 $update['resolution_notes']='Drainage obstruction cleared and inspected.';
 [, $headers]=request('admin/edit_complaint.php?id='.$otherId,$update,$cookies['admin']);check(str_contains($headers[0],'302'),'Complaint resolves with closing details');
 [$page]=request('complaint_conversation.php?id='.$otherId,null,$cookies['other']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$page,$feedbackToken);
 check(str_contains($page,'Your complaint timeline')&&str_contains($page,'Drainage obstruction cleared'),'Resident timeline shows lifecycle and resolution details');
 $q->execute([$otherId]);$other=$q->fetch(PDO::FETCH_ASSOC);$feedback=['complaint_id'=>$otherId,'csrf_token'=>$feedbackToken[1],'version'=>reportVersion($other),'action'=>'reopen','reason'=>'Water still collects after rain.'];
 [$ownPage]=request('complaint_conversation.php?id='.$id,null,$cookies['resident']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$ownPage,$ownToken);$unauthorized=$feedback;$unauthorized['csrf_token']=$ownToken[1];
 [, $headers]=request('resolution_feedback.php',$unauthorized,$cookies['resident']);check(str_contains($headers[0],'404'),'Other residents cannot reopen a report');
 $invalidFeedback=$feedback;$invalidFeedback['reason']='';[, $headers]=request('resolution_feedback.php',$invalidFeedback,$cookies['other']);check(str_contains($headers[0],'422'),'Reopening requires an explanation');
 [, $headers]=request('resolution_feedback.php',$feedback,$cookies['other']);check(str_contains($headers[0],'302'),'Report owner can request reopening with a reason');
 $q->execute([$otherId]);$other=$q->fetch(PDO::FETCH_ASSOC);check($other['status']==='processing'&&(int)$other['reopen_count']===1&&$other['resolved_at']===null,'Reopening returns complaint to processing and records reopen count');
 $update['version']=reportVersion($other);[, $headers]=request('admin/edit_complaint.php?id='.$otherId,$update,$cookies['admin']);check(str_contains($headers[0],'302'),'Staff can resolve a reopened report');
 $q->execute([$otherId]);$other=$q->fetch(PDO::FETCH_ASSOC);$feedback['version']=reportVersion($other);$feedback['action']='confirm';[, $headers]=request('resolution_feedback.php',$feedback,$cookies['other']);check(str_contains($headers[0],'302'),'Resident can confirm completed resolution');
 [, $headers]=request('resolution_feedback.php',$feedback,$cookies['other']);check(str_contains($headers[0],'422'),'Repeated confirmation cannot change the closing record');
 $q->execute([$otherId]);$confirmed=$q->fetch(PDO::FETCH_ASSOC);$update['version']=reportVersion($confirmed);$update['resolution_notes']='Changed closing notes';[, $headers]=request('admin/edit_complaint.php?id='.$otherId,$update,$cookies['admin']);check(str_contains($headers[0],'422'),'Staff cannot overwrite a resident-confirmed resolution');
 $audit=$pdo->prepare("SELECT COUNT(*) FROM complaint_activity WHERE complaint_id=? AND actor_id=? AND event_type IN ('confirmation','reopen')");$audit->execute([$otherId,$users['other']]);check((int)$audit->fetchColumn()===2,'Confirmation and reopening retain resident authorship in activity logs');
 [$page]=request('admin/account_access.php?username='.$registration['username'],null,$cookies['admin']);preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$page,$accessToken);
 $access=['username'=>$registration['username'],'csrf_token'=>$accessToken[1],'action'=>'deactivate'];
 [, $headers]=request('admin/account_access.php',$access,$cookies['staff']);check(str_contains($headers[0],'403'),'Staff cannot manage another account access');
 [, $headers]=request('admin/account_access.php',$access,$cookies['admin']);check(str_contains($headers[0],'302'),'Administrator can deactivate staff');
 [, $headers]=request('admin/dashboard.php',null,$registeredCookie);check(str_contains($headers[0],'403'),'Deactivation revokes existing staff sessions');
 $access['action']='activate';request('admin/account_access.php',$access,$cookies['admin']);$registeredCookie=login($registration['username'],$newPassword);
 $access['action']='reset';$access['password']=$password;$access['password_confirmation']=$password;[, $headers]=request('admin/account_access.php',$access,$cookies['admin']);check(str_contains($headers[0],'302'),'Administrator can issue a temporary recovery password');
 [, $headers]=request('admin/dashboard.php',null,$registeredCookie);check(str_contains($headers[0],'403'),'Password recovery revokes existing sessions');
 $registeredCookie=login($registration['username'],$password);[, $headers]=request('admin/dashboard.php',null,$registeredCookie);check(str_contains($headers[0],'302'),'Recovered account requires a new password');
 for($i=0;$i<5;$i++)request('index.php',['username'=>'message_test_staff_'.$suffix,'password'=>'wrong']);
 [, $headers]=request('index.php',['username'=>'message_test_staff_'.$suffix,'password'=>$password]);check(str_contains($headers[0],'200'),'Repeated failed sign-in attempts temporarily block further login');
 echo "All messaging, staff, and workflow integration checks passed.\n";
}finally{
 if($evidencePath!==''&&is_file($evidencePath))unlink($evidencePath);
 foreach($complaints as $id){$q=$pdo->prepare('DELETE FROM complaints WHERE id=?');$q->execute([$id]);}
 foreach($users as $id){$q=$pdo->prepare('DELETE FROM account_activity WHERE actor_id=? OR target_id=?');$q->execute([$id,$id]);$q=$pdo->prepare('SELECT username FROM users WHERE id=?');$q->execute([$id]);$username=$q->fetchColumn();$q=$pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?');$q->execute([hash('sha256',mb_strtolower($username).'|127.0.0.1')]);$q->execute([hash('sha256',mb_strtolower($username).'|::1')]);$q=$pdo->prepare('DELETE FROM users WHERE id=?');$q->execute([$id]);}
 echo "Temporary test messages, complaints, notifications, and accounts removed.\n";
}
