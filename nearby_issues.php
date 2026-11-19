<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/resident_features.php';
header('Content-Type: application/json');requireResident();
try {
    $lat=filter_var($_GET['latitude']??null,FILTER_VALIDATE_FLOAT);
    $lng=filter_var($_GET['longitude']??null,FILTER_VALIDATE_FLOAT);
    $type=is_string($_GET['category']??null)?$_GET['category']:'';
    $types=['Road Damage','Drainage Issue','Street Light','Garbage Collection','Noise Complaint','Water Supply','Other'];
    if($lat===false||$lng===false||abs($lat)>90||abs($lng)>180||!in_array($type,$types,true))throw new InvalidArgumentException('Choose a category and pin a valid location.');
    // Only category, status, approximate distance and supporter count are shared.
    // Descriptions, addresses, attachments and resident identities remain private.
    $sql="SELECT c.id,c.complaint_type,c.status,c.created_at,
      6371000*ACOS(LEAST(1,GREATEST(-1,SIN(RADIANS(?))*SIN(RADIANS(c.latitude))+COS(RADIANS(?))*COS(RADIANS(c.latitude))*COS(RADIANS(c.longitude)-RADIANS(?))))) AS distance,
      (SELECT COUNT(*) FROM complaint_supporters s WHERE s.complaint_id=c.id) AS supporters,
      EXISTS(SELECT 1 FROM complaint_supporters s WHERE s.complaint_id=c.id AND s.user_id=?) AS supported
      FROM complaints c WHERE c.status<>'resolved' AND c.complaint_type=? AND c.user_id<>?
      AND c.latitude BETWEEN ? AND ? AND c.longitude BETWEEN ? AND ? HAVING distance<=300 ORDER BY distance,c.id DESC LIMIT 5";
    $lngRange=min(180,.003/ max(.00001,cos(deg2rad($lat))));
    $q=$pdo->prepare($sql);$q->execute([$lat,$lat,$lng,$_SESSION['user_id'],$type,$_SESSION['user_id'],$lat-.003,$lat+.003,$lng-$lngRange,$lng+$lngRange]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($items as &$item){$item['distance']=max(50,(int)(ceil($item['distance']/50)*50));$item['supported']=(bool)$item['supported'];}unset($item);
    echo json_encode(['items'=>$items]);
}catch(Throwable $e){http_response_code(422);echo json_encode(['error'=>$e instanceof InvalidArgumentException?$e->getMessage():'Nearby reports could not be loaded.']);}
