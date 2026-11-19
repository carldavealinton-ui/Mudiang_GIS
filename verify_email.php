<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';
$status = 'invalid';

if ($token) {
    $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['email_verified']) {
            $status = 'already';
        } else {
            $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?")
                ->execute([$user['id']]);
            $status = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification — Barangay Mudiang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <style>
        :root { --primary: #2563eb; --border: #e2e8f0; --bg: #f1f5f9; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card {
            background: white; border-radius: 16px; border: 1px solid var(--border);
            box-shadow: 0 4px 24px rgba(0,0,0,0.08); width: 100%; max-width: 420px;
            text-align: center; padding: 48px 36px;
        }
        .icon { font-size: 52px; margin-bottom: 20px; }
        h2 { font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 10px; }
        p { font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 28px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 24px; background: var(--primary); color: white;
            border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: background 0.2s;
        }
        .btn:hover { background: #1d4ed8; }
    </style>
<?php include __DIR__ . '/ui_theme.php'; ?>
</head>
<body class="auth-page">
<div class="card">
    <?php if ($status === 'success'): ?>
        <div class="icon" style="color:#10b981">&#10003;</div>
        <h2>Email Verified!</h2>
        <p>Your Gmail address has been verified successfully. You can now sign in to your account.</p>
        <a href="login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Sign In</a>
    <?php elseif ($status === 'already'): ?>
        <div class="icon" style="color:#2563eb"><i class="fas fa-check-circle"></i></div>
        <h2>Already Verified</h2>
        <p>Your email address is already verified. Go ahead and sign in.</p>
        <a href="login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Sign In</a>
    <?php else: ?>
        <div class="icon" style="color:#ef4444"><i class="fas fa-times-circle"></i></div>
        <h2>Invalid Link</h2>
        <p>This verification link is invalid or has already been used. Please register again or contact support.</p>
        <a href="register.php" class="btn" style="background:#64748b"><i class="fas fa-user-plus"></i> Register</a>
    <?php endif; ?>
</div>
</body>
</html>
