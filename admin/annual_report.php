<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/reporting_support.php';
require_once __DIR__.'/../portal_navigation.php';
date_default_timezone_set('Asia/Manila');
$error='';
try { $range=reportingRange($_GET); }
catch(InvalidArgumentException $exception){http_response_code(422);$error=$exception->getMessage();$range=reportingRange([]);}
$reportCategories=['Road Damage','Drainage Issue','Street Light','Garbage Collection','Noise Complaint','Water Supply','Other'];
if(!$error){
    $page=isset($_GET['page'])&&is_string($_GET['page'])?max(1,(int)$_GET['page']):1;
    $reportData=reportingData($pdo,$range,$page);
}
if(isset($_GET['export'])){
    if($error||!is_string($_GET['export'])||!in_array($_GET['export'],['summary','records'],true)){http_response_code(422);header('Content-Type: text/plain; charset=UTF-8');exit($error?:'Choose a valid CSV export.');}
    exportReportingCsv($pdo,$range,$reportData,$_GET['export']);
}
require __DIR__.'/reporting_view.php';
