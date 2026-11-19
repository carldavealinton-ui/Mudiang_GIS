<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
session_save_path(sys_get_temp_dir());
require __DIR__.'/../config.php';
$sql=file_get_contents(__DIR__.'/../migrations/20261006_resident_features.sql');
foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
$indexes=$pdo->query('SHOW INDEX FROM complaints')->fetchAll(PDO::FETCH_ASSOC);
if(!in_array('idx_resident_reports',array_column($indexes,'Key_name'),true))$pdo->exec('CREATE INDEX idx_resident_reports ON complaints(user_id,created_at,id)');
if(!in_array('idx_nearby_reports',array_column($indexes,'Key_name'),true))$pdo->exec('CREATE INDEX idx_nearby_reports ON complaints(complaint_type,status,latitude,longitude)');
echo "Resident features migration applied.\n";
