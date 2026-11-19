<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/resident_features.php';
requireResident();
$q=$pdo->prepare('SELECT full_name,email,contact,address FROM users WHERE id=?');$q->execute([$_SESSION['user_id']]);$values=$q->fetch(PDO::FETCH_ASSOC);$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    foreach(['full_name','contact','address'] as $key)$values[$key]=is_string($_POST[$key]??null)?trim($_POST[$key]):'';
    try {
        residentCsrf();
        if($values['full_name']===''||mb_strlen($values['full_name'])>100)throw new InvalidArgumentException('Enter your full name, up to 100 characters.');
        if(!preg_match('/^[0-9]{1,12}$/D',$values['contact']))throw new InvalidArgumentException('Contact number must contain only digits, up to 12 digits.');
        if($values['address']===''||mb_strlen($values['address'])>255)throw new InvalidArgumentException('Enter your complete address, up to 255 characters.');
        $q=$pdo->prepare('UPDATE users SET full_name=?,contact=?,address=? WHERE id=?');$q->execute([$values['full_name'],$values['contact'],$values['address'],$_SESSION['user_id']]);
        $_SESSION['full_name']=$values['full_name'];header('Location: profile.php?saved=1');exit;
    }catch(Throwable $e){if(http_response_code()!==403)http_response_code(422);$error=$e instanceof InvalidArgumentException?$e->getMessage():'Your profile could not be saved.';}
}
require_once __DIR__.'/portal_navigation.php';
renderPortalPageStart('My profile','Keep your contact information up to date.');
echo '<section class="card settings-card"><div class="card-header"><h2>Personal information</h2></div><div class="card-body">';
?>
<p>Keep your details up to date so barangay staff can contact you.</p>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= residentEscape($error) ?></div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Profile updated.</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= residentEscape($_SESSION['resident_csrf']) ?>">
<?php foreach(['full_name'=>['Full name',100,'name'],'contact'=>['Contact number',12,'tel'],'address'=>['Home address',255,'street-address']] as $key=>$details): ?>
<div class="form-group"><label for="<?= $key ?>"><?= $details[0] ?></label><input class="form-control" id="<?= $key ?>" name="<?= $key ?>" maxlength="<?= $details[1] ?>" autocomplete="<?= $details[2] ?>" value="<?= residentEscape($values[$key]) ?>" <?= $key==='contact'?'type="tel" inputmode="numeric" pattern="[0-9]{1,12}"':'type="text"' ?> required></div>
<?php endforeach; ?>
<p class="mb-3">Email address: <?= residentEscape($values['email']) ?>. Contact barangay support if this needs changing.</p>
<button class="btn btn-primary">Save profile</button></form><div class="settings-links"><a href="account.php">Account security</a><a href="resident_dashboard.php">Back to dashboard</a></div></div></section><?php renderPortalPageEnd(); ?>
