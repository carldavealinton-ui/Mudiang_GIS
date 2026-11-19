<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/resident_features.php';
require_once __DIR__.'/recovery_support.php';
header('Cache-Control: no-store');
$_SESSION['recovery_csrf']??=bin2hex(random_bytes(32));$notice='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    $started=microtime(true);
    try {
        if(!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['recovery_csrf'],$_POST['csrf_token'])){http_response_code(403);throw new InvalidArgumentException('Refresh the page and try again.');}
        $email=is_string($_POST['email']??null)?strtolower(trim($_POST['email'])):'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100)throw new InvalidArgumentException('Enter a valid email address.');
        $ipAllowed=recoveryAllowed($pdo,'request-ip:'.($_SERVER['REMOTE_ADDR']??''));
        $emailAllowed=recoveryAllowed($pdo,'request-email:'.$email);
        if($ipAllowed&&$emailAllowed) {
            $q=$pdo->prepare("SELECT id,email,full_name FROM users WHERE email=? AND role='resident' AND email_verified=1 AND is_active=1");$q->execute([$email]);$user=$q->fetch(PDO::FETCH_ASSOC);
            if($user) {
                $token=bin2hex(random_bytes(32));
                $q=$pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=CURRENT_TIMESTAMP');
                $q->execute([$user['id'],hash('sha256',$token)]);
                $url=recoveryBaseUrl().'/reset_password.php?token='.$token;
                sendRegistrationVerification($user['email'],'Reset your Barangay Mudiang password',"Hello {$user['full_name']},\r\n\r\nReset your password using this link:\r\n{$url}\r\n\r\nThis link expires in 30 minutes and can be used once. If you did not request this, ignore this email.",'From: noreply@barangay-mudiang.local');
            }
        }
        $notice='If an active, verified resident account matches that email, a reset link will be sent. Check your inbox and spam folder.';
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Password recovery is temporarily unavailable.';}
    // Keep a small response-time floor for known and unknown addresses.
    $remaining=750000-(int)((microtime(true)-$started)*1000000);if($remaining>0)usleep($remaining);
}
residentPageStart('Forgot password');
?>
<p>Enter the verified Gmail address associated with your resident account.</p>
<?php if($notice): ?><div class="alert alert-success" role="status"><?= residentEscape($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= residentEscape($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= residentEscape($_SESSION['recovery_csrf']) ?>"><div class="form-group"><label for="email">Email address</label><input class="form-control" type="email" name="email" id="email" maxlength="100" autocomplete="email" required></div><button class="btn btn-primary">Send reset link</button></form><p class="mt-3"><a href="login.php">Back to sign in</a></p></main></body></html>
