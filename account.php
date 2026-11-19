<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/report_activity.php';
require_once __DIR__.'/account_security.php';
if(!isset($_SESSION['user_id'])){header('Location: index.php');exit;}
$_SESSION['account_csrf']??=bin2hex(random_bytes(32));$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['account_csrf'],$_POST['csrf_token'])){http_response_code(403);throw new InvalidArgumentException('Reload this page before changing your password.');}
  $current=is_string($_POST['current_password']??null)?$_POST['current_password']:'';$password=is_string($_POST['password']??null)?$_POST['password']:'';
  if(strlen($password)<8||strlen($password)>72||$password!==($_POST['password_confirmation']??'')||$password===$current)throw new InvalidArgumentException('Choose a different password of 8–72 bytes and enter matching confirmation.');
  $pdo->beginTransaction();$q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$_SESSION['user_id']]);$user=$q->fetch(PDO::FETCH_ASSOC);
  if(!$user||!password_verify($current,$user['password']))throw new InvalidArgumentException('Your current password is incorrect.');
  $q=$pdo->prepare('UPDATE users SET password=?,must_change_password=0,session_version=session_version+1 WHERE id=?');$q->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]);
  $q=$pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=?');$q->execute([$user['id']]);accountActivity($pdo,$user,'Account password changed.');$pdo->commit();
  session_regenerate_id(true);$_SESSION['session_version']=(int)$user['session_version']+1;$_SESSION['must_change_password']=0;header('Location: account.php?saved=1');exit;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if(http_response_code()!==403)http_response_code(422);$error=$e instanceof InvalidArgumentException?$e->getMessage():'Unable to change password.';}
}
$home=in_array($_SESSION['role'],['admin','staff'],true)?'admin/dashboard.php':'resident_dashboard.php';
?>
<?php
require_once __DIR__.'/portal_navigation.php';
renderPortalPageStart('Account security','Manage your password and protect access to your account.');
?>
<section class="card settings-card"><div class="card-header"><h2>Change your password</h2></div><div class="card-body">
<p>Signed in as <?= reportActivityEscape($_SESSION['full_name']??$_SESSION['username']) ?>.</p>
<?php if($_SESSION['must_change_password']??false): ?><div class="alert alert-warning" role="status">Replace your temporary password before accessing reports.</div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= reportActivityEscape($error) ?></div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Password changed. Other signed-in sessions have been revoked.</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= reportActivityEscape($_SESSION['account_csrf']) ?>">
<?php foreach(['current_password'=>'Current password','password'=>'New password','password_confirmation'=>'Confirm new password'] as $key=>$label): ?>
<div class="form-group"><label for="<?= $key ?>"><?= $label ?></label><input class="form-control" type="password" name="<?= $key ?>" id="<?= $key ?>" autocomplete="<?= $key==='current_password'?'current-password':'new-password' ?>" required maxlength="72"></div>
<?php endforeach; ?><p class="field-help mb-3">Choose a different password of 8–72 bytes and enter matching confirmation.</p><button class="btn btn-primary" type="submit">Change password</button></form>
<div class="settings-links"><a href="<?= reportActivityEscape($home) ?>">Back to dashboard</a><?php if($_SESSION['role']==='resident'): ?><a href="profile.php">My profile</a><?php endif; ?></div>
</div></section><?php renderPortalPageEnd(); ?>