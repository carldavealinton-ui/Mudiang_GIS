<?php
function authenticateAccount(PDO $pdo, string $username, string $password, bool $staffOnly=false): ?array {
    $key=hash('sha256',mb_strtolower(trim($username)).'|'.($_SERVER['REMOTE_ADDR']??'local'));
    $q=$pdo->prepare('INSERT IGNORE INTO login_attempts(attempt_key) VALUES(?)');$q->execute([$key]);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT *,blocked_until>NOW() AS blocked FROM login_attempts WHERE attempt_key=? FOR UPDATE');$q->execute([$key]);$attempt=$q->fetch(PDO::FETCH_ASSOC);
        if($attempt['blocked']){$pdo->commit();return null;}
        $q=$pdo->prepare('SELECT * FROM users WHERE username=?');$q->execute([trim($username)]);$user=$q->fetch(PDO::FETCH_ASSOC);
        $hash=$user['password']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid=password_verify($password,$hash)&&$user&&$user['is_active']&&(!$staffOnly||in_array($user['role'],['admin','staff'],true));
        if($valid){$q=$pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?');$q->execute([$key]);}
        else{$count=($attempt['blocked_until']||strtotime($attempt['updated_at'])<time()-900)?1:(int)$attempt['failures']+1;$q=$pdo->prepare('UPDATE login_attempts SET failures=?,blocked_until=CASE WHEN ?>=5 THEN DATE_ADD(NOW(),INTERVAL 15 MINUTE) ELSE NULL END,updated_at=NOW() WHERE attempt_key=?');$q->execute([$count,$count,$key]);}
        $pdo->commit();return $valid?$user:null;
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function accountActivity(PDO $pdo, array $target, string $summary): void {
    $q=$pdo->prepare('SELECT full_name,username FROM users WHERE id=?');$q->execute([$_SESSION['user_id']]);$actor=$q->fetch(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('INSERT INTO account_activity(actor_id,target_id,actor_name,target_name,summary) VALUES(?,?,?,?,?)');$q->execute([$_SESSION['user_id'],$target['id'],$actor['full_name'].' (@'.$actor['username'].')',$target['full_name'].' (@'.$target['username'].')',$summary]);
}
