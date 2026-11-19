<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../account_security.php';
if ($_SESSION['role'] !== 'admin') { http_response_code(403); exit('Only administrators can register staff accounts.'); }
function staffEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$_SESSION['staff_account_csrf'] ??= bin2hex(random_bytes(32));
$errors = [];
$values = array_fill_keys(['full_name', 'username', 'email', 'contact'], '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['staff_account_csrf'], $_POST['csrf_token'])) { http_response_code(403); exit('Invalid registration token. Reload this page.'); }
    foreach ($values as $field => $value) $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    if ($values['full_name'] === '' || mb_strlen($values['full_name']) > 100) $errors[] = 'Enter a full name of up to 100 characters.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D', $values['username'])) $errors[] = 'Use 3–50 letters, numbers, periods, underscores or hyphens for the username.';
    if ($values['email'] !== '' && (strlen($values['email']) > 100 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL))) $errors[] = 'Enter a valid email address.';
    if (mb_strlen($values['contact']) > 50) $errors[] = 'Contact number must be 50 characters or fewer.';
    if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'Password must be between 8 and 72 bytes.';
    if ($password !== $confirmation) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        $query = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $query->execute([$values['username']]);
        if ($query->fetchColumn()) $errors[] = 'That username is already registered. Choose another.';
        else {
            try {
                $pdo->beginTransaction();
                $query = $pdo->prepare("INSERT INTO users (full_name, username, password, email, contact, role, must_change_password) VALUES (?, ?, ?, ?, ?, 'staff', 1)");
                $query->execute([$values['full_name'], $values['username'], password_hash($password, PASSWORD_DEFAULT), $values['email'] ?: null, $values['contact'] ?: null]);
                accountActivity($pdo,['id'=>(int)$pdo->lastInsertId()]+$values,'Staff account registered with an initial temporary password.');
                $pdo->commit();
                header('Location: staff.php?created=1'); exit;
            } catch (PDOException $exception) {
                if($pdo->inTransaction())$pdo->rollBack();
                if ($exception->getCode() !== '23000') throw $exception;
                $errors[] = 'That username is already registered. Choose another.';
            }
        }
    }
    http_response_code(422);
}
$staff = $pdo->query("SELECT u.id, u.full_name, u.username, u.email, u.contact, u.role, u.created_at, u.is_active, COUNT(a.id) AS action_count, MAX(a.created_at) AS last_activity FROM users u LEFT JOIN complaint_activity a ON a.actor_id = u.id WHERE u.role IN ('admin', 'staff') GROUP BY u.id, u.full_name, u.username, u.email, u.contact, u.role, u.created_at, u.is_active ORDER BY u.created_at DESC, u.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Staff Accounts — Barangay Mudiang</title><link rel="stylesheet" href="../assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><?php include __DIR__ . '/../ui_theme.php'; ?></head>
<body class="portal-page resident-directory">
<?php require_once __DIR__.'/../portal_navigation.php'; renderPortalNavigation(); ?>
<main class="main"><header class="topbar"><div class="topbar-left"><h1>Staff accounts</h1><p>Individual access. Clear accountability.</p></div><a class="btn btn-outline" href="activity.php">View activity logs</a><a class="nav-item" href="operations.php"><i class="fas fa-chart-line" aria-hidden="true"></i> Operations</a></header><div class="content">
<section class="dashboard-intro"><div><span class="portal-kicker">Operations / Your team</span><h2>Every action has a name.</h2><p>Register each staff member with their own login. Report updates, remarks and messages will show their full name and username in Activity Logs.</p></div><span class="intro-tag"><?= count($staff) ?> team accounts</span></section>
<?php if (isset($_GET['created'])): ?><div class="alert alert-success" role="status">Staff account created. The staff member can now sign in using their own username and password.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please check the registration details.</strong><ul><?php foreach ($errors as $error): ?><li><?= staffEscape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="card mb-4"><div class="card-header"><div><h2><i class="fas fa-user-plus" aria-hidden="true"></i> Register staff</h2><p>Create a separate account for each person handling resident reports.</p></div></div><form method="post" class="p-4"><input type="hidden" name="csrf_token" value="<?= staffEscape($_SESSION['staff_account_csrf']) ?>"><div class="row g-3">
<?php foreach (['full_name' => 'Full name', 'username' => 'Username', 'email' => 'Email (optional)', 'contact' => 'Contact number (optional)'] as $field => $label): ?><div class="col-md-6"><label class="form-label" for="staff-<?= $field ?>"><?= $label ?></label><input class="form-control" id="staff-<?= $field ?>" name="<?= $field ?>" type="<?= $field === 'email' ? 'email' : 'text' ?>" value="<?= staffEscape($values[$field]) ?>" maxlength="<?= $field === 'username' || $field === 'contact' ? 50 : 100 ?>" <?= in_array($field, ['full_name', 'username'], true) ? 'required' : '' ?>></div><?php endforeach; ?>
<div class="col-md-6"><label class="form-label" for="staff-password">Password</label><input class="form-control" id="staff-password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required><small class="text-muted">At least 8 characters. Share credentials privately with this staff member.</small></div><div class="col-md-6"><label class="form-label" for="staff-confirm">Confirm password</label><input class="form-control" id="staff-confirm" name="password_confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div><div class="col-12"><button class="btn btn-primary" type="submit"><i class="fas fa-user-plus" aria-hidden="true"></i> Create staff account</button></div></div></form></section>
<section class="card"><div class="card-header"><div><h2>Team directory</h2><p>Administrators and staff responsible for resident reports.</p></div></div><div class="table-wrap"><table><caption class="visually-hidden">Staff identity and recorded report activity</caption><thead><tr><th scope="col">Team member</th><th scope="col">Role</th><th scope="col">Contact</th><th scope="col">Recorded actions</th><th scope="col">Last activity</th></tr></thead><tbody><?php foreach ($staff as $member): ?><tr><td><strong><?= staffEscape($member['full_name']) ?></strong><span class="resident-username">@<?= staffEscape($member['username']) ?></span><?php if($member['role']==='staff'): ?><a href="account_access.php?username=<?= rawurlencode($member['username']) ?>">Manage access</a><?php endif; ?></td><td><?= staffEscape(ucfirst($member['role'])) ?> · <?= $member['is_active']?'Active':'Inactive' ?></td><td><?= staffEscape($member['email'] ?: 'No email provided') ?><span class="resident-username"><?= staffEscape($member['contact'] ?: 'No contact provided') ?></span></td><td><a href="activity.php?actor=<?= (int) $member['id'] ?>"><?= (int) $member['action_count'] ?> actions <i class="fas fa-arrow-right" aria-hidden="true"></i></a></td><td><?= $member['last_activity'] ? staffEscape(date('M j, Y · g:i a', strtotime($member['last_activity']))) : 'No report activity yet' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</div></main><?php include __DIR__ . '/notification_widget.php'; ?></body></html>
