<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
session_save_path(sys_get_temp_dir());require __DIR__.'/../config.php';session_write_close();require __DIR__.'/../recovery_support.php';
function verify($condition,$label){if(!$condition)throw new RuntimeException($label);echo "PASS: $label\n";}
function httpCall($path,$data=null,$cookie='') {
 $options=['http'=>['method'=>$data===null?'GET':'POST','header'=>"Cookie: $cookie",'ignore_errors'=>true,'follow_location'=>0]];
 if($data!==null){$options['http']['header'].="\r\nContent-Type: application/x-www-form-urlencoded";$options['http']['content']=http_build_query($data);}
 $body=file_get_contents('http://localhost/System/'.$path,false,stream_context_create($options));return [$body,$http_response_header];
}
function signIn($name,$password){[, $headers]=httpCall('index.php',['username'=>$name,'password'=>$password]);$cookie='';foreach($headers as $header)if(preg_match('/Set-Cookie: (PHPSESSID=[^;]+)/i',$header,$match))$cookie=$match[1];if($cookie&&str_contains($headers[0],'302'))return $cookie;throw new RuntimeException('Sign-in failed.');}
function csrfFrom($page,$name='csrf_token'){preg_match('/name="'.preg_quote($name,'/').'" value="([a-f0-9]+)"/',$page,$match);if(empty($match[1]))throw new RuntimeException('No CSRF token.');return $match[1];}
$users=[];$reports=[];$announcement=0;$suffix=bin2hex(random_bytes(6));$password='TestPassword123';$bucket='fixture:'.$suffix;
try {
 foreach(['resident','other','admin','staff'] as $role){$name='resident_feature_'.$role.'_'.$suffix;$email=$name.'@gmail.com';$q=$pdo->prepare('INSERT INTO users(username,password,full_name,email,email_verified,contact,address,role) VALUES(?,?,?,?,1,?,?,?)');$q->execute([$name,password_hash($password,PASSWORD_DEFAULT),'Feature test '.$role,$email,'09123456789','Test address',$role==='other'?'resident':$role]);$users[$role]=(int)$pdo->lastInsertId();$cookies[$role]=signIn($name,$password);}
 for($i=0;$i<12;$i++){$q=$pdo->prepare('INSERT INTO complaints(user_id,complaint_type,description,latitude,longitude,address,complainant_name,complainant_contact) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$users['resident'],$i===0?'Street Light':'Road Damage','Private fixture description '.$suffix,7.182708,125.622193,'Private fixture address','Private reporter','09123456789']);$reports[]=(int)$pdo->lastInsertId();}
 $q=$pdo->prepare('INSERT INTO complaints(user_id,complaint_type,description,latitude,longitude,address,complainant_name,complainant_contact) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$users['other'],'Road Damage','Other resident private text '.$suffix,7.182708,125.622193,'Hidden address','Hidden reporter','09123456789']);$otherReport=(int)$pdo->lastInsertId();$reports[]=$otherReport;
 [$page]=httpCall('submit_complaint_form.php',null,$cookies['resident']);preg_match('/data-draft-token="([a-f0-9]+)"/',$page,$m);$token=$m[1];
 verify(str_contains($page,'save-draft')&&str_contains($page,'current-location')&&str_contains($page,'resident-language.js'),'Report form exposes draft, location and language features');
 [, $headers]=httpCall('resident_draft.php');verify(str_contains($headers[0],'403'),'Anonymous drafts blocked');
 [$body,$headers]=httpCall('resident_draft.php',['description'=>'bad','csrf_token'=>'wrong'],$cookies['resident']);verify(str_contains($headers[0],'403'),'Draft writes require CSRF');
 [$body]=httpCall('resident_draft.php',['csrf_token'=>$token,'description'=>'Saved fixture '.$suffix,'complaint_type'=>'Road Damage','address'=>'Fixture','latitude'=>'7.182708','longitude'=>'125.622193','priority'=>'medium'],$cookies['resident']);verify(json_decode($body,true)['ok']??false,'Draft saved to account');
 [$body]=httpCall('resident_draft.php',null,$cookies['resident']);verify(json_decode($body,true)['draft']['description']==='Saved fixture '.$suffix,'Account draft restored');
 [$body]=httpCall('resident_draft.php',null,$cookies['other']);verify(json_decode($body,true)['draft']===null,'Other resident cannot read draft');
 [$page]=httpCall('submit_complaint_form.php?draft=1',null,$cookies['resident']);verify(str_contains($page,'Saved fixture '.$suffix),'Continue draft prefills report form');
 [$page]=httpCall('resident_dashboard.php',null,$cookies['resident']);verify(substr_count($page,'class="complaint-item"')===10&&str_contains($page,'Page 1 of 2')&&!str_contains($page,'Other resident private text'),'Dashboard paginates and scopes private reports');
 [$page]=httpCall('resident_dashboard.php?category=Street%20Light',null,$cookies['resident']);verify(substr_count($page,'class="complaint-item"')===1,'Category filter narrows reports');
 [$page]=httpCall('resident_dashboard.php?q=absent_'.$suffix,null,$cookies['resident']);verify(str_contains($page,'No reports found')&&!str_contains($page,'class="complaint-item"'),'Search empty state works');
 [$page]=httpCall('resident_dashboard.php?from=2099-01-01',null,$cookies['resident']);verify(!str_contains($page,'class="complaint-item"'),'Date filter works');
 [$page]=httpCall('resident_dashboard.php?status=resolved',null,$cookies['resident']);verify(!str_contains($page,'class="complaint-item"'),'Status filter works');
 $nearby='nearby_issues.php?latitude=7.182708&longitude=125.622193&category=Road%20Damage';
 [$body]=httpCall($nearby,null,$cookies['resident']);$nearbyData=json_decode($body,true);verify(count($nearbyData['items'])>=1,'Nearby matching issues found');
 verify(!str_contains($body,'description')&&!str_contains($body,'address')&&!str_contains($body,'Hidden reporter')&&!str_contains($body,'latitude'),'Nearby results omit private fields');
 $support=['complaint_id'=>$otherReport,'latitude'=>'7.182708','longitude'=>'125.622193','category'=>'Road Damage','csrf_token'=>$token];
 [$body]=httpCall('support_issue.php',$support,$cookies['resident']);verify(json_decode($body,true)['ok']??false,'Resident supports nearby issue');
 httpCall('support_issue.php',$support,$cookies['resident']);$q=$pdo->prepare('SELECT COUNT(*) FROM complaint_supporters WHERE complaint_id=? AND user_id=?');$q->execute([$otherReport,$users['resident']]);verify((int)$q->fetchColumn()===1,'Repeated support is idempotent');
 [$body,$headers]=httpCall('support_issue.php',array_replace($support,['latitude'=>'0']),$cookies['resident']);verify(str_contains($headers[0],'422'),'Distant issue support rejected');
 [$page]=httpCall('resident_dashboard.php',null,$cookies['resident']);verify(str_contains($page,'Issues I support'),'Supported issue appears on dashboard');
 [$page]=httpCall('profile.php',null,$cookies['resident']);$profileToken=csrfFrom($page);
 [, $headers]=httpCall('profile.php',['csrf_token'=>$profileToken,'full_name'=>'Updated resident','contact'=>'1234567890123','address'=>'Purok 1'],$cookies['resident']);verify(str_contains($headers[0],'422'),'Profile rejects overlong phone');
 [, $headers]=httpCall('profile.php',['csrf_token'=>$profileToken,'full_name'=>'Updated resident','contact'=>'09123456789','address'=>'Purok 1'],$cookies['resident']);verify(str_contains($headers[0],'302'),'Profile update accepted');
 $q=$pdo->prepare('SELECT full_name FROM users WHERE id=?');$q->execute([$users['other']]);verify($q->fetchColumn()==='Feature test other','Profile update cannot modify another resident');
 [, $headers]=httpCall('admin/announcements.php',null,$cookies['resident']);verify(str_contains($headers[0],'403'),'Resident cannot publish announcements');
 [, $headers]=httpCall('admin/announcements.php',null,$cookies['staff']);verify(str_contains($headers[0],'403'),'Staff cannot publish announcements');
 [$page]=httpCall('admin/announcements.php',null,$cookies['admin']);$announcementToken=csrfFrom($page);
 $advisory=['csrf_token'=>$announcementToken,'title'=>'Fixture advisory '.$suffix,'body'=>'Test announcement <script>unsafe</script>','category'=>'General','published_at'=>'2026-01-01T00:00','expires_at'=>'2099-01-01T00:00','is_published'=>'1'];
 [, $headers]=httpCall('admin/announcements.php',$advisory,$cookies['admin']);verify(str_contains($headers[0],'302'),'Admin publishes announcement');
 $q=$pdo->prepare('SELECT id FROM community_announcements WHERE title=?');$q->execute([$advisory['title']]);$announcement=(int)$q->fetchColumn();
 [$page]=httpCall('resident_dashboard.php',null,$cookies['resident']);verify(str_contains($page,$advisory['title'])&&str_contains($page,'&lt;script&gt;unsafe&lt;/script&gt;'),'Residents see safely escaped announcement');
 unset($advisory['is_published']);httpCall('admin/announcements.php?edit='.$announcement,$advisory,$cookies['admin']);
 [$page]=httpCall('resident_dashboard.php',null,$cookies['resident']);verify(!str_contains($page,$advisory['title']),'Unpublished announcement hidden');
 for($i=0;$i<5;$i++)verify(recoveryAllowed($pdo,$bucket),'Recovery request within limit '.$i);
 verify(!recoveryAllowed($pdo,$bucket),'Recovery rate limit blocks excess requests');
 $reset=bin2hex(random_bytes(32));$q=$pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');$q->execute([$users['resident'],hash('sha256',$reset)]);
 verify(!completeResidentReset($pdo,$reset,'short'),'Reset rejects weak password');
 verify(completeResidentReset($pdo,$reset,'NewPassword456'),'Valid one-time token resets password');
 verify(!completeResidentReset($pdo,$reset,'AnotherPassword789'),'Consumed reset token cannot be reused');
 [, $headers]=httpCall('resident_dashboard.php',null,$cookies['resident']);verify(str_contains($headers[0],'403'),'Reset revokes prior sessions');
 $reset=bin2hex(random_bytes(32));$q=$pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_SUB(NOW(),INTERVAL 1 MINUTE))');$q->execute([$users['resident'],hash('sha256',$reset)]);
 verify(!completeResidentReset($pdo,$reset,'NewPassword456'),'Expired reset token rejected');
 [$page]=httpCall('forgot_password.php');verify(str_contains($page,'Send reset link')&&!str_contains($page,'Fatal error'),'Forgot password page loads');
 [$page]=httpCall('reset_password.php?token=invalid');$resetCsrf=csrfFrom($page);[, $headers]=httpCall('reset_password.php?token=invalid',['csrf_token'=>'wrong','password'=>'NewPassword456','confirm_password'=>'NewPassword456']);verify(str_contains($headers[0],'403'),'Reset form requires CSRF');
 echo "All resident feature checks passed.\n";
}finally{
 if($announcement){$q=$pdo->prepare('DELETE FROM community_announcements WHERE id=?');$q->execute([$announcement]);}
 foreach($reports as $id){$q=$pdo->prepare('DELETE FROM complaints WHERE id=?');$q->execute([$id]);}
 foreach($users as $id){$q=$pdo->prepare('DELETE FROM users WHERE id=?');$q->execute([$id]);}
 $q=$pdo->prepare('DELETE FROM password_reset_limits WHERE bucket=?');$q->execute([hash('sha256',$bucket)]);
 $q=$pdo->prepare('DELETE FROM login_attempts WHERE attempt_key IN ('.implode(',',array_fill(0,4,'?')).')');$keys=[];foreach(['resident','other','admin','staff'] as $role)$keys[]=hash('sha256','resident_feature_'.$role.'_'.$suffix.'|::1');$q->execute($keys);
}
