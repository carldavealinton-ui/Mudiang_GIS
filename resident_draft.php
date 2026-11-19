<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/resident_features.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
requireResident();
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        residentCsrf();
        if (($_POST['action'] ?? '')==='delete') {
            $q=$pdo->prepare('DELETE FROM resident_drafts WHERE user_id=?');$q->execute([$_SESSION['user_id']]);
        } else {
            $payload=[];
            foreach (['complaint_type'=>50,'description'=>60000,'address'=>255,'latitude'=>30,'longitude'=>30,'priority'=>10] as $key=>$max) {
                $value=$_POST[$key]??'';
                if(!is_string($value)||mb_strlen($value)>$max)throw new InvalidArgumentException('Draft contains an invalid field.');
                $payload[$key]=$value;
            }
            $q=$pdo->prepare('INSERT INTO resident_drafts(user_id,payload) VALUES(?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)');
            $q->execute([$_SESSION['user_id'],json_encode($payload,JSON_THROW_ON_ERROR)]);
        }
        echo json_encode(['ok'=>true]);
    } elseif ($_SERVER['REQUEST_METHOD']==='GET') {
        $q=$pdo->prepare('SELECT payload,updated_at FROM resident_drafts WHERE user_id=?');$q->execute([$_SESSION['user_id']]);$draft=$q->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['draft'=>$draft?json_decode($draft['payload'],true):null,'updated_at'=>$draft['updated_at']??null]);
    } else {http_response_code(405);echo json_encode(['error'=>'Method not allowed.']);}
} catch(Throwable $e) {
    if(http_response_code()!==403)http_response_code(422);
    echo json_encode(['error'=>$e instanceof InvalidArgumentException?$e->getMessage():'Unable to save draft. Please try again.']);
}
