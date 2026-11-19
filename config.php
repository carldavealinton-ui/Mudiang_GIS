<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'barangay_gis';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();
if (isset($_SESSION['user_id'])) {
    $sessionAccount=$pdo->prepare('SELECT role,is_active,must_change_password,session_version FROM users WHERE id=?');
    $sessionAccount->execute([$_SESSION['user_id']]);$sessionAccount=$sessionAccount->fetch(PDO::FETCH_ASSOC);
    if(!$sessionAccount||!$sessionAccount['is_active']||(int)($_SESSION['session_version']??0)!==(int)$sessionAccount['session_version']) {
        $_SESSION=[];session_destroy();http_response_code(403);exit('This account session is no longer active. Please sign in again.');
    }
    $_SESSION['role']=$sessionAccount['role'];$_SESSION['must_change_password']=(int)$sessionAccount['must_change_password'];
    $sessionPage=basename($_SERVER['SCRIPT_NAME']??'');
    if(PHP_SAPI!=='cli'&&$sessionAccount['must_change_password']&&!in_array($sessionPage,['account.php','logout.php'],true)){
        $sessionBase=str_contains($_SERVER['SCRIPT_NAME']??'','/admin/')?'../':'';header('Location: '.$sessionBase.'account.php');exit;
    }
}
?>
