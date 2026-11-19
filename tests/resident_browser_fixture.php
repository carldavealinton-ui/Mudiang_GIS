<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
session_save_path(sys_get_temp_dir());require __DIR__.'/../config.php';session_write_close();
require __DIR__.'/../evidence_support.php';
if(($argv[1]??'')==='cleanup') {
    foreach(array_slice($argv,2) as $id) {
        $q=$pdo->prepare("SELECT username FROM users WHERE id=? AND username LIKE 'browser_fixture_%'");$q->execute([(int)$id]);
        if(!$q->fetchColumn())throw new RuntimeException('Fixture cleanup target rejected.');
        $q=$pdo->prepare('SELECT e.path FROM complaint_evidence e JOIN complaints c ON c.id=e.complaint_id WHERE c.user_id=?');$q->execute([(int)$id]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $storedPath){
            $file=realpath(EVIDENCE_DIRECTORY.'/'.basename($storedPath));
            if($file&&dirname($file)===realpath(EVIDENCE_DIRECTORY))unlink($file);
        }
        $q=$pdo->prepare('DELETE FROM complaints WHERE user_id=?');$q->execute([(int)$id]);
        $q=$pdo->prepare('DELETE FROM users WHERE id=?');$q->execute([(int)$id]);
    }
    exit;
}
$suffix=bin2hex(random_bytes(6));$password=bin2hex(random_bytes(12));$users=[];$reports=[];$accounts=[];
foreach(['resident','other','admin','staff'] as $role) {
    $username='browser_fixture_'.$role.'_'.$suffix;
    $q=$pdo->prepare("INSERT INTO users(username,password,full_name,email,email_verified,contact,address,role) VALUES(?,?,?,?,1,?,?,?)");$q->execute([$username,password_hash($password,PASSWORD_DEFAULT),'Browser fixture '.$role,$username.'@gmail.com','09123456789','Fixture address',$role==='other'?'resident':$role]);$users[$role]=(int)$pdo->lastInsertId();$accounts[$role]=$username;
    if(in_array($role,['resident','other'],true)){$q=$pdo->prepare('INSERT INTO complaints(user_id,complaint_type,description,latitude,longitude,address,complainant_name,complainant_contact) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$users[$role],'Road Damage','Browser fixture report',7.182708,125.622193,'Fixture address','Browser fixture','09123456789']);$reports[$role]=(int)$pdo->lastInsertId();}
}
$q=$pdo->prepare('UPDATE complaints SET assigned_staff_id=?,assigned_to=? WHERE id=?');$q->execute([$users['staff'],'Browser fixture staff',$reports['resident']]);
echo json_encode(['username'=>'browser_fixture_resident_'.$suffix,'password'=>$password,'users'=>array_values($users),'accounts'=>$accounts,'reports'=>$reports]);
