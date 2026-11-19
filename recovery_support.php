<?php
require_once __DIR__.'/registration_mail.php';
function recoveryAllowed(PDO $pdo, string $key): bool {
    $bucket=hash('sha256',$key);
    $q=$pdo->prepare('INSERT INTO password_reset_limits(bucket,attempts,window_started) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_started<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),window_started=IF(window_started<DATE_SUB(NOW(),INTERVAL 15 MINUTE),NOW(),window_started)');
    $q->execute([$bucket]);
    $q=$pdo->prepare('SELECT attempts FROM password_reset_limits WHERE bucket=?');$q->execute([$bucket]);
    return (int)$q->fetchColumn()<=5;
}
function recoveryBaseUrl(): string {
    // Configure APP_BASE_URL for a deployed installation; never trust the Host header.
    return appMailBaseUrl();
}
function recoveryPasswordValid(string $password): bool {
    return mb_strlen($password)>=8 && strlen($password)<=72 && preg_match('/[a-zA-Z]/',$password) && preg_match('/[0-9]/',$password);
}
function completeResidentReset(PDO $pdo, string $token, string $password): bool {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)||!recoveryPasswordValid($password))return false;
    // Compute the hash before locking the account.
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare("SELECT u.* FROM password_reset_tokens r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.expires_at>NOW() AND u.role='resident' AND u.is_active=1 AND u.email_verified=1 FOR UPDATE");
        $q->execute([hash('sha256',$token)]);$user=$q->fetch(PDO::FETCH_ASSOC);
        if(!$user){$pdo->rollBack();return false;}
        $q=$pdo->prepare('UPDATE users SET password=?,must_change_password=0,session_version=session_version+1 WHERE id=?');$q->execute([$hash,$user['id']]);
        $q=$pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=?');$q->execute([$user['id']]);
        $pdo->commit();return true;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
