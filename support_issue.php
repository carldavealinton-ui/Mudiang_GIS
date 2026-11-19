<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/resident_features.php';
header('Content-Type: application/json');requireResident();
try {
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit(json_encode(['error'=>'Method not allowed.']));}
    residentCsrf();$id=(int)($_POST['complaint_id']??0);
    $q=$pdo->prepare("SELECT id,user_id,status FROM complaints WHERE id=? AND status<>'resolved'");$q->execute([$id]);$report=$q->fetch(PDO::FETCH_ASSOC);
    if(!$report||$report['user_id']==$_SESSION['user_id'])throw new InvalidArgumentException('This report is unavailable for community support.');
    // Validate the selected location and category on the server as well.
    $lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);$lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);
    $type=is_string($_POST['category']??null)?$_POST['category']:'';
    if($lat===false||$lng===false||abs($lat)>90||abs($lng)>180)throw new InvalidArgumentException('Pin your location before supporting an issue.');
    $q=$pdo->prepare('SELECT 6371000*ACOS(LEAST(1,GREATEST(-1,SIN(RADIANS(?))*SIN(RADIANS(latitude))+COS(RADIANS(?))*COS(RADIANS(latitude))*COS(RADIANS(longitude)-RADIANS(?))))) FROM complaints WHERE id=? AND complaint_type=?');$q->execute([$lat,$lat,$lng,$id,$type]);$distance=$q->fetchColumn();
    if($distance===false||(float)$distance>300)throw new InvalidArgumentException('Choose a matching issue within 300 metres of your pin.');
    $q=$pdo->prepare('INSERT IGNORE INTO complaint_supporters(complaint_id,user_id) VALUES(?,?)');$q->execute([$id,$_SESSION['user_id']]);
    echo json_encode(['ok'=>true]);
}catch(Throwable $e){if(http_response_code()!==403)http_response_code(422);echo json_encode(['error'=>$e instanceof InvalidArgumentException?$e->getMessage():'Unable to support this issue.']);}
