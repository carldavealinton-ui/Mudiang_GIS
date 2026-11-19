<?php
require_once 'config.php';
require_once __DIR__.'/account_security.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff' ? ($_SESSION['role']==='staff'?'admin/staff_dashboard.php':'admin/dashboard.php') : 'resident_dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = authenticateAccount($pdo,is_string($_POST['username']??null)?$_POST['username']:'',is_string($_POST['password']??null)?$_POST['password']:'');
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['session_version']=(int)$user['session_version'];
        $_SESSION['must_change_password']=(int)$user['must_change_password'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        header('Location: ' . ($user['must_change_password'] ? 'account.php' : ($user['role'] === 'admin' || $user['role'] === 'staff' ? ($_SESSION['role']==='staff'?'admin/staff_dashboard.php':'admin/dashboard.php') : 'resident_dashboard.php')));
        exit;
    } else {
        $error = 'Sign-in unsuccessful. Check your credentials or try again after 15 minutes if you have made repeated attempts. Use Forgot password if you need account recovery.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Barangay Mudiang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <style>
        :root {
            --primary: #2563eb; --primary-dark: #1d4ed8;
            --text: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0; --bg: #f1f5f9;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif; background: var(--bg);
            min-height: 100vh; display: flex;
        }
        .split-left {
            width: 45%; background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 100%);
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            padding: 60px 50px; color: white;
        }
        .split-left .brand-icon {
            width: 64px; height: 64px; background: var(--primary); border-radius: 16px;
            display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 28px;
        }
        .split-left h1 { font-size: 26px; font-weight: 700; margin-bottom: 10px; text-align: center; }
        .split-left p { color: #94a3b8; font-size: 14px; text-align: center; line-height: 1.7; max-width: 300px; }
        .features { margin-top: 40px; display: flex; flex-direction: column; gap: 16px; width: 100%; max-width: 300px; }
        .feature-item { display: flex; align-items: center; gap: 12px; }
        .feature-item .fi { width: 36px; height: 36px; background: rgba(37,99,235,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 15px; flex-shrink: 0; }
        .feature-item span { font-size: 13px; color: #cbd5e1; }

        .split-right {
            flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px;
        }
        .login-box { width: 100%; max-width: 400px; }
        .login-box h2 { font-size: 24px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .login-box .sub { font-size: 14px; color: var(--text-muted); margin-bottom: 32px; }

        .alert { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 15px; }
        .input-wrap input {
            width: 100%; padding: 11px 14px 11px 40px; border: 1px solid var(--border);
            border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; color: var(--text);
            background: white; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-wrap input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        .btn-login {
            width: 100%; padding: 12px; background: var(--primary); color: white;
            border: none; border-radius: 8px; font-family: 'Inter', sans-serif;
            font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover { background: var(--primary-dark); }

        .divider { display: flex; align-items: center; gap: 12px; margin: 24px 0; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .divider span { font-size: 12px; color: var(--text-muted); }

        .links { display: flex; flex-direction: column; gap: 10px; }
        .links a {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; font-weight: 500; color: var(--text); text-decoration: none; transition: all 0.2s;
        }
        .links a:hover { background: var(--bg); border-color: var(--primary); color: var(--primary); }

        @media (max-width: 768px) {
            .split-left { display: none; }
            .split-right { padding: 24px; }
        }
    </style>
<?php include __DIR__ . '/ui_theme.php'; ?>
</head>
<body class="auth-page">
    <div class="split-left"><div class="auth-brand">Mudiang / Community Services Portal</div>
        <div class="brand-icon"><i class="fas fa-map-marked-alt"></i></div>
        <h1>A connected community.<br>A better Mudiang.</h1>
        <p>A shared space to report local concerns, map what matters, and follow community action.</p>
        <div class="civic-map" aria-hidden="true"><svg viewBox="0 0 400 150"><g fill="none" stroke="#b7cdb5" stroke-opacity=".25"><path d="M0 90L80 50L150 100L260 35L400 60M30 150L100 90L170 30L260 80L360 0M0 30L120 65L220 130L400 100M75 0L80 50L100 90L90 150M260 0L260 35L260 80L280 150"/></g><g fill="#d9bb75"><circle class="map-node" cx="80" cy="50" r="7"/><circle class="map-node" cx="260" cy="80" r="7"/><circle class="map-node" cx="220" cy="130" r="5"/></g><g fill="none" stroke="#d9bb75" opacity=".3"><circle cx="80" cy="50" r="18"/><circle cx="260" cy="80" r="18"/></g></svg><span class="map-caption">Local concerns. Coordinated action.</span></div><div class="features">
            <div class="feature-item">
                <div class="fi"><i class="fas fa-map-pin"></i></div>
                <span>Pin exact complaint locations on the map</span>
            </div>
            <div class="feature-item">
                <div class="fi"><i class="fas fa-chart-line"></i></div>
                <span>Track resolution status in real-time</span>
            </div>
            <div class="feature-item">
                <div class="fi"><i class="fas fa-shield-alt"></i></div>
                <span>Secure resident and admin portals</span>
            </div>
        </div>
    </div>

    <div class="split-right">
        <div class="login-box">
            <span class="portal-kicker">Resident &amp; staff access</span><h2>Welcome back.</h2>
            <p class="sub">Sign in to report, track, and manage community concerns.</p>

            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrap">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>
                <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Sign In</button>
            </form>

            <div class="divider"><span>OR</span></div>

            <div class="links">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Create a new account</a>
                <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot password</a>
                <a href="admin/login.php"><i class="fas fa-shield-alt"></i> Staff &amp; administrator access</a>
            </div>
        </div>
    </div>

    <?php include 'chatbot_widget.php'; ?>
</body>
</html>
