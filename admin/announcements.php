<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/../resident_features.php';require_once __DIR__.'/../report_activity.php';
require_once __DIR__.'/../announcement_notifications.php';
date_default_timezone_set('Asia/Manila');
if($_SESSION['role']!=='admin'){http_response_code(403);exit('Administrator access required.');}
$_SESSION['announcements_csrf']??=bin2hex(random_bytes(32));$error='';
$categories=['General','Water interruption','Waste collection','Road advisory','Community event'];
$edit=(int)($_GET['edit']??0);$values=['id'=>0,'title'=>'','body'=>'','category'=>'General','published_at'=>date('Y-m-d\TH:i'),'expires_at'=>'','is_published'=>0];
if($edit){$q=$pdo->prepare('SELECT * FROM community_announcements WHERE id=?');$q->execute([$edit]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row){http_response_code(404);exit('Announcement not found.');}$values=$row;}
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['announcements_csrf'],$_POST['csrf_token'])){http_response_code(403);throw new InvalidArgumentException('Refresh the page before saving.');}
        foreach(['title','body','category','published_at','expires_at'] as $key)$values[$key]=is_string($_POST[$key]??null)?trim($_POST[$key]):'';
        $values['is_published']=isset($_POST['is_published'])?1:0;
        if($values['title']===''||mb_strlen($values['title'])>150||$values['body']===''||mb_strlen($values['body'])>10000||!in_array($values['category'],$categories,true))throw new InvalidArgumentException('Enter a title, message, and valid announcement category.');
        $parse=static function(string $raw): ?string {
            if($raw==='')return null;
            $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',str_replace(' ','T',substr($raw,0,16)),new DateTimeZone('Asia/Manila'));
            $errors=DateTimeImmutable::getLastErrors();
            if(!$date||($errors&&($errors['warning_count']||$errors['error_count'])))throw new InvalidArgumentException('Choose valid announcement dates.');
            return $date->format('Y-m-d H:i:s');
        };
        $published=$parse($values['published_at']);$expires=$parse($values['expires_at']);
        if(!$published||($expires&&$expires<=$published))throw new InvalidArgumentException('Expiry must be later than the publication date.');
        $pdo->beginTransaction();
        if($edit){$q=$pdo->prepare('UPDATE community_announcements SET title=?,body=?,category=?,published_at=?,expires_at=?,is_published=? WHERE id=?');$q->execute([$values['title'],$values['body'],$values['category'],$published,$expires,$values['is_published'],$edit]);}
        else{$q=$pdo->prepare('INSERT INTO community_announcements(title,body,category,published_at,expires_at,is_published,created_by) VALUES(?,?,?,?,?,?,?)');$q->execute([$values['title'],$values['body'],$values['category'],$published,$expires,$values['is_published'],$_SESSION['user_id']]);}
        notifyResidentAnnouncements($pdo);
        $pdo->commit();
        header('Location: announcements.php?saved=1');exit;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if(http_response_code()!==403)http_response_code(422);$error=$e instanceof InvalidArgumentException?$e->getMessage():'Unable to save announcement.';}
}
$items=$pdo->query('SELECT id,title,is_published,published_at,expires_at FROM community_announcements ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Barangay announcements</title><link rel="stylesheet" href="../assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><?php include __DIR__.'/../ui_theme.php'; ?></head><body class="portal-page ">
<?php include __DIR__.'/operations_nav.php'; ?>
<main class="main"><header class="topbar"><div class="topbar-left"><h1>Barangay announcements</h1><p>Publish community advisories for residents. Dates use Philippine time.</p></div></header><div class="content">
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= residentEscape($error) ?></div><?php endif; ?><?php if(isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Announcement saved.</div><?php endif; ?>
<section class="card"><div class="card-header"><h2><?= $edit?'Edit announcement':'New announcement' ?></h2></div><div class="card-body"><form method="post"><input type="hidden" name="csrf_token" value="<?= residentEscape($_SESSION['announcements_csrf']) ?>"><div class="form-group"><label for="title">Title</label><input id="title" name="title" maxlength="150" value="<?= residentEscape($values['title']) ?>" required></div><div class="form-group"><label for="body">Message</label><textarea id="body" name="body" maxlength="10000" rows="5" required><?= residentEscape($values['body']) ?></textarea></div><div class="form-group"><label for="category">Category</label><select id="category" name="category"><?php foreach($categories as $category): ?><option <?= $values['category']===$category?'selected':'' ?>><?= residentEscape($category) ?></option><?php endforeach; ?></select></div>
<?php foreach(['published_at'=>'Publish on','expires_at'=>'Expires on (optional)'] as $key=>$label): ?><div class="form-group"><label for="<?= $key ?>"><?= $label ?></label><input type="datetime-local" id="<?= $key ?>" name="<?= $key ?>" value="<?= residentEscape(str_replace(' ','T',substr($values[$key]??'',0,16))) ?>" <?= $key==='published_at'?'required':'' ?>></div><?php endforeach; ?>
<label class="d-flex gap-2 mb-3"><input type="checkbox" name="is_published" value="1" <?= $values['is_published']?'checked':'' ?>> Published (uncheck to hide from residents)</label><button class="btn btn-primary">Save announcement</button> <a href="announcements.php" class="btn btn-outline">New announcement</a></form></div></section>
<section class="card"><div class="card-header"><h2>Recent announcements</h2></div><div class="card-body"><?php if(!$items): ?><p>No announcements yet.</p><?php endif; ?><?php foreach($items as $item): ?><article class="community-advisory"><h3><?= residentEscape($item['title']) ?></h3><p><?= $item['is_published']?'Published / scheduled':'Draft' ?> · <?= residentEscape($item['published_at']) ?></p><a href="?edit=<?= (int)$item['id'] ?>">Edit or unpublish</a></article><?php endforeach; ?></div></section></div></main></body></html>
