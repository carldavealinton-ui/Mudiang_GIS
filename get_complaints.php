<?php
require_once 'config.php';
require_once __DIR__.'/access_control.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])||!in_array($_SESSION['role']??'', ['admin','staff','resident'],true)){http_response_code(403);echo json_encode(['error'=>'Sign in to view reports.']);exit;}
$sql='SELECT id, complaint_type, description, latitude, longitude, status, created_at FROM complaints c';
[$scope,$params]=complaintAccessScope();$stmt=$pdo->prepare($sql.' WHERE '.$scope.' ORDER BY created_at DESC');$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($complaints);
?>
