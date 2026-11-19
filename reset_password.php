<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/resident_features.php';
require_once __DIR__.'/recovery_support.php';
header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
$_SESSION['recovery_csrf']??=bin2hex(random_bytes(32));$error='';$done=false;
$token=is_string($_GET['token']??null)?$_GET['token']:'';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['recovery_csrf'],$_POST['csrf_token'])){http_response_code(403);throw new InvalidArgumentException('Refresh the page and try again.');}
        if(!recoveryAllowed($pdo,'reset-ip:'.($_SERVER['REMOTE_ADDR']??'')))throw new InvalidArgumentException('Too many attempts. Try again in 15 minutes.');
        $password=is_string($_POST['password']??null)?$_POST['password']:'';
        if(!recoveryPasswordValid($password)||$password!==($_POST['confirm_password']??null))throw new InvalidArgumentException('Use matching passwords with at least 8 characters, a letter and a number, and no more than 72 bytes.');
        if(!completeResidentReset($pdo,$token,$password))throw new InvalidArgumentException('This reset link is invalid or expired. Request a new link.');
        $done=true;
    }catch(Throwable $e){if(http_response_code()!==403)http_response_code(422);$error=$e instanceof InvalidArgumentException?$e->getMessage():'Your password could not be reset. Try again.';}
}
residentPageStart('Reset password');
?>
<?php if($done): ?><div class="alert alert-success" role="status">Password reset. Sign in with your new password. Previous sessions have been signed out.</div>
<?php else: ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= residentEscape($error) ?></div><?php endif; ?>
<p>Use at least 8 characters with a letter and a number, up to 72 bytes.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= residentEscape($_SESSION['recovery_csrf']) ?>">
<?php foreach(['password'=>'New password','confirm_password'=>'Confirm password'] as $key=>$label): ?><div class="form-group"><label for="<?= $key ?>"><?= $label ?></label><input class="form-control" type="password" name="<?= $key ?>" id="<?= $key ?>" minlength="8" autocomplete="new-password" required></div><?php endforeach; ?><button class="btn btn-primary">Reset password</button></form><?php endif; ?>
<p class="mt-3"><a href="login.php">Back to sign in</a> · <a href="forgot_password.php">Request a new reset link</a></p></main></body></html>
