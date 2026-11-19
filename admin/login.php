<?php
require_once '../config.php';
require_once __DIR__.'/../account_security.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . (in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true) ? ($_SESSION['role']==='staff'?'staff_dashboard.php':'dashboard.php') : '../resident_dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $user=authenticateAccount($pdo,(string)$username,(string)$password,true);
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['session_version']=(int)$user['session_version'];
        $_SESSION['must_change_password']=(int)$user['must_change_password'];
        $_SESSION['role'] = $user['role'];
        header('Location: '.($user['must_change_password']?'../account.php':($user['role']==='staff'?'staff_dashboard.php':'dashboard.php')));
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #ecf0f1; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; margin-bottom: 30px; color: #2c3e50; }
        .form-group { margin: 20px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { width: 100%; padding: 12px; background: #3498db; color: white; border: none; cursor: pointer; border-radius: 4px; font-size: 16px; }
        .btn:hover { background: #2980b9; }
        .error { background: #e74c3c; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #3498db; text-decoration: none; }
    </style>
<?php include __DIR__ . '/../ui_theme.php'; ?>
</head>
<body class="auth-page">
    <div class="login-container">
        <span class="portal-kicker">Barangay Mudiang / Staff portal</span><h2>Community operations</h2><p class="staff-subtitle">Sign in to manage reports and coordinate local action.</p>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <div class="back-link">
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>
