<?php
require_once 'config.php';


$error = '';
$success = '';
$fields = [];
foreach (['username', 'password', 'confirm_password', 'full_name', 'email', 'contact', 'address'] as $field) {
    $fields[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
}
foreach (['username', 'full_name', 'email', 'address'] as $field) {
    $fields[$field] = trim($fields[$field]);
}
$escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = $fields['username'];
    $password  = $fields['password'];
    $confirm   = $fields['confirm_password'];
    $full_name = $fields['full_name'];
    $email     = $fields['email'];
    $contact   = $fields['contact'];
    $address   = $fields['address'];

    // --- Validate Gmail ---
    if (in_array('', $fields, true)) {
        $error = 'Please complete all required fields.';
    } elseif (mb_strlen($username) > 50 || mb_strlen($full_name) > 100 || mb_strlen($email) > 100 || mb_strlen($contact) > 50 || mb_strlen($address) > 255) {
        $error = 'One or more fields exceed the allowed length.';
    } elseif (!preg_match('/^[0-9]{11}$/D', $contact)) {
        $error = 'Contact number must contain exactly 11 digits. Letters, spaces, and symbols are not allowed.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/D', $username)) {
        $error = 'Username must be 3–50 characters and use only letters, numbers, or underscores.';
    } elseif (mb_strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must have at least 8 characters, including a letter and a number.';
    } elseif (strlen($password) > 72) {
        $error = 'Password must be no longer than 72 bytes.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+\-]+@gmail\.com$/i', $email)) {
        $error = 'Only Gmail addresses (@gmail.com) are accepted.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
        // Check duplicate username or email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $error = 'Username or email already exists.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, email_verified, verification_token, contact, address, role) VALUES (?, ?, ?, ?, 0, NULL, ?, ?, 'resident')");
            if ($stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $email, $contact, $address])) {
                $success = 'Registration successful! Your account is ready. You can sign in now.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000'
                ? 'Username or email already exists.'
                : 'Registration is temporarily unavailable. Please try again later.';
            error_log('Registration database error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Barangay Mudiang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <style>
        :root {
            --primary: #2563eb; --primary-dark: #1d4ed8;
            --text: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; }

        /* ── Left panel ── */
        .split-left {
            width: 380px; flex-shrink: 0;
            background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 100%);
            display: flex; flex-direction: column; justify-content: center;
            padding: 56px 44px; color: white;
        }
        .brand-icon {
            width: 56px; height: 56px; background: var(--primary); border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 28px;
        }
        .split-left h1 { font-size: 22px; font-weight: 700; line-height: 1.35; margin-bottom: 10px; }
        .split-left > p { font-size: 14px; color: #94a3b8; line-height: 1.6; margin-bottom: 36px; }
        .steps { display: flex; flex-direction: column; gap: 18px; }
        .step { display: flex; align-items: flex-start; gap: 14px; }
        .step-num {
            width: 28px; height: 28px; border-radius: 50%; background: rgba(37,99,235,0.25);
            border: 1px solid rgba(37,99,235,0.5); color: #93c5fd;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0; margin-top: 1px;
        }
        .step-text strong { display: block; font-size: 13px; font-weight: 600; color: #e2e8f0; }
        .step-text span { font-size: 12px; color: #64748b; }
        .left-footer { margin-top: auto; padding-top: 40px; font-size: 12px; color: #475569; }

        /* ── Right panel ── */
        .split-right {
            flex: 1; background: #f8fafc;
            display: flex; align-items: flex-start; justify-content: center;
            padding: 40px 32px; overflow-y: auto;
        }
        .reg-box { width: 100%; max-width: 560px; }
        .reg-box h2 { font-size: 22px; font-weight: 700; color: var(--text); }
        .reg-box .sub { font-size: 14px; color: var(--text-muted); margin-top: 4px; margin-bottom: 28px; }

        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
        .alert i { margin-top: 1px; flex-shrink: 0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        .section-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--text-muted); margin: 20px 0 14px; padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 5px; }
        .req { color: #ef4444; }

        .input-wrap { position: relative; }
        .input-wrap i.fi { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; color: #94a3b8; pointer-events: none; }
        .input-wrap i.field-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; pointer-events: none; }
        .input-wrap input {
            width: 100%; padding: 10px 36px 10px 36px; border: 1px solid var(--border);
            border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; color: var(--text);
            background: white; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-wrap input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .input-wrap input.valid { border-color: #10b981; }
        .input-wrap input.invalid { border-color: #ef4444; }
        .field-icon.valid { color: #10b981; }
        .field-icon.invalid { color: #ef4444; }

        .email-hint { font-size: 11px; color: var(--text-muted); margin-top: 5px; display: flex; align-items: center; gap: 5px; }
        .email-hint.ok { color: #10b981; } .email-hint.err { color: #ef4444; }

        .btn-register {
            width: 100%; padding: 12px; background: var(--primary); color: white;
            border: none; border-radius: 8px; font-family: 'Inter', sans-serif;
            font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px;
        }
        .btn-register:hover { background: var(--primary-dark); }
        .btn-register:disabled { background: #94a3b8; cursor: not-allowed; }

        .divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; color: var(--text-muted); font-size: 12px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .links { display: flex; justify-content: center; gap: 24px; }
        .links a { font-size: 13px; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 6px; font-weight: 500; }
        .links a:hover { color: var(--primary-dark); }

        @media (max-width: 820px) {
            body { flex-direction: column; }
            .split-left { width: 100%; padding: 32px 24px; }
            .steps { flex-direction: row; flex-wrap: wrap; gap: 12px; }
            .split-right { padding: 28px 20px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
<?php include __DIR__ . '/ui_theme.php'; ?>
    <link rel="stylesheet" href="assets/registration.css">
</head>
<body class="auth-page registration-page">
    <!-- Left branding panel -->
    <div class="split-left"><div class="auth-brand">Mudiang / Community Services Portal</div>
        <a class="registration-home" href="index.php"><span aria-hidden="true">&larr;</span> Back to home</a>
        <div class="brand-icon"><i class="fas fa-map-marked-alt"></i></div>
        <h1>Your community.<br>Your voice.</h1>
        <p>Register with Barangay Mudiang to report concerns, track updates, and stay connected to community services.</p>
        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">
                    <strong>Create your account</strong>
                    <span>Fill in your personal details below</span>
                </div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">
                    <strong>Sign in to your account</strong>
                    <span>Use your username and password</span>
                </div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">
                    <strong>Start reporting</strong>
                    <span>Pin complaints on the map and track progress</span>
                </div>
            </div>
        </div>
        <div class="left-footer">© Barangay Mudiang · GIS Complaint System</div>
    </div>

    <!-- Right form panel -->
    <div class="split-right">
        <div class="reg-box">
            <div class="registration-kicker">RESIDENT REGISTRATION</div>
            <h2>Create your account</h2>
            <p class="sub">Enter your details to get started. Fields marked <span class="req">*</span> are required.</p>
            <?php if (!$success): ?>
            <div class="registration-notice"><i class="fas fa-envelope-open-text" aria-hidden="true"></i><div><strong>Have your Gmail ready</strong><p>Use an active Gmail address for account recovery. You can sign in immediately after registering.</p></div></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><i class="fas fa-exclamation-circle"></i><?php echo $escape($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success" role="status"><i class="fas fa-envelope"></i><span><?php echo $escape($success); ?></span></div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" id="regForm">
                <div class="section-label"><span class="section-number">01</span> Personal Information</div>
                <div class="form-group">
                    <label for="full_name">Full Name <span class="req">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-user fi"></i>
                        <input type="text" name="full_name" id="full_name" maxlength="100" autocomplete="name" aria-describedby="nameHint" placeholder="Enter your full name" value="<?php echo $escape($fields['full_name']); ?>" required>
                    </div>
                    <p class="field-help" id="nameHint">Use your full name so barangay staff can identify your account.</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact">Contact Number <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-phone fi"></i>
                            <input type="tel" name="contact" id="contact" inputmode="numeric" minlength="11" maxlength="11" pattern="[0-9]{11}" title="Contact number must contain exactly 11 digits." aria-describedby="contactHint" autocomplete="tel" placeholder="09123456789" value="<?php echo $escape($fields['contact']); ?>" required>
                        </div>
                        <p class="field-help" id="contactHint">Exactly 11 digits. No spaces or symbols.</p>
                    </div>
                    <div class="form-group">
                        <label for="emailInput">Gmail Address <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope fi"></i>
                            <input type="email" name="email" id="emailInput" maxlength="100" placeholder="yourname@gmail.com"
                                value="<?php echo $escape($fields['email']); ?>" required autocomplete="email" aria-describedby="emailHint">
                            <i class="fas field-icon" id="emailIcon"></i>
                        </div>
                        <div class="email-hint" id="emailHint"><i class="fas fa-info-circle"></i> Only @gmail.com addresses are accepted</div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Home Address <span class="req">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-map-marker-alt fi"></i>
                        <input type="text" name="address" id="address" maxlength="255" autocomplete="street-address" aria-describedby="addressHint" placeholder="House number, street, purok, barangay" value="<?php echo $escape($fields['address']); ?>" required>
                    </div>
                    <p class="field-help" id="addressHint">Include your purok and barangay for a complete address.</p>
                </div>

                <div class="section-label"><span class="section-number">02</span> Account Credentials</div>
                <div class="form-group">
                    <label for="username">Username <span class="req">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-at fi"></i>
                        <input type="text" name="username" id="username" minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]{3,50}" title="Use 3–50 letters, numbers, or underscores." aria-describedby="usernameHint" autocomplete="username" placeholder="Choose a username" value="<?php echo $escape($fields['username']); ?>" required>
                    </div>
                    <p class="field-help" id="usernameHint">3–50 characters. Letters, numbers, and underscores only.</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-lock fi"></i>
                            <input type="password" name="password" id="password" minlength="8" autocomplete="new-password" aria-describedby="passwordRequirements" placeholder="Create a password" required>
                            <button type="button" class="password-toggle" data-password="password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-lock fi"></i>
                            <input type="password" name="confirm_password" id="confirm_password" autocomplete="new-password" placeholder="Repeat password" required>
                            <button type="button" class="password-toggle" data-password="confirm_password" aria-label="Show confirm password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </div>

                <div class="password-requirements" id="passwordRequirements">
                    <strong>Password requirements</strong>
                    <ul><li id="passwordLength">At least 8 characters</li><li id="passwordLetter">At least one letter</li><li id="passwordNumber">At least one number</li><li id="passwordLimit">No more than 72 bytes</li><li id="passwordMatch">Both passwords match</li></ul>
                </div>
                <p class="registration-footnote"><i class="fas fa-info-circle" aria-hidden="true"></i> Use accurate contact details so staff can follow up on your reports.</p>
                <button type="submit" class="btn-register" id="submitBtn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            <?php endif; ?>

            <div class="divider"><span>OR</span></div>
            <div class="links">
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Already have an account?</a>
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>

    <script>
        const contactInput = document.getElementById('contact');
        if (contactInput) {
            function validateContact() {
                contactInput.value = contactInput.value.replace(/[^0-9]/g, '').slice(0, 11);
                contactInput.setCustomValidity(/^[0-9]{11}$/.test(contactInput.value) ? '' : 'Contact number must contain exactly 11 digits.');
            }
            contactInput.addEventListener('beforeinput', event => {
                if (event.inputType === 'insertText' && event.data && /[^0-9]/.test(event.data)) event.preventDefault();
            });
            contactInput.addEventListener('paste', event => {
                const text = event.clipboardData?.getData('text');
                if (typeof text !== 'string') return;
                event.preventDefault();
                contactInput.setRangeText(text.replace(/[^0-9]/g, ''), contactInput.selectionStart, contactInput.selectionEnd, 'end');
                validateContact();
            });
            contactInput.addEventListener('input', validateContact);
            contactInput.addEventListener('change', validateContact);
            validateContact();
        }

        const emailInput = document.getElementById('emailInput');
        const emailIcon  = document.getElementById('emailIcon');
        const emailHint  = document.getElementById('emailHint');
        const submitBtn  = document.getElementById('submitBtn');

        function validateGmail(val) {
            return /^[a-zA-Z0-9._%+\-]+@gmail\.com$/i.test(val.trim());
        }

        if (emailInput) emailInput.addEventListener('input', function () {
            const val = this.value.trim();
            this.setCustomValidity(val && !validateGmail(val) ? 'Enter a valid @gmail.com address.' : '');
            if (!val) {
                this.className = '';
                emailIcon.className = 'fas field-icon';
                emailHint.className = 'email-hint';
                emailHint.innerHTML = '<i class="fas fa-info-circle"></i> Only @gmail.com addresses are accepted';
                submitBtn.disabled = false;
                return;
            }
            if (validateGmail(val)) {
                this.classList.remove('invalid'); this.classList.add('valid');
                emailIcon.className = 'fas fa-check-circle field-icon valid';
                emailHint.className = 'email-hint ok';
                emailHint.innerHTML = '<i class="fas fa-check-circle"></i> Valid Gmail address';
                submitBtn.disabled = false;
            } else {
                this.classList.remove('valid'); this.classList.add('invalid');
                emailIcon.className = 'fas fa-times-circle field-icon invalid';
                emailHint.className = 'email-hint err';
                emailHint.innerHTML = '<i class="fas fa-times-circle"></i> Must be a valid @gmail.com address';
                submitBtn.disabled = false;
            }
        });

        // Trigger on load if value pre-filled (after error)
        if (emailInput && emailInput.value) emailInput.dispatchEvent(new Event('input'));
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        function validatePasswords() {
            const password = passwordInput.value;
            const checks = {
                passwordLength: Array.from(password).length >= 8,
                passwordLetter: /[a-zA-Z]/.test(password),
                passwordNumber: /[0-9]/.test(password),
                passwordLimit: password.length > 0 && new TextEncoder().encode(password).length <= 72,
                passwordMatch: password.length > 0 && confirmInput.value === password
            };
            Object.entries(checks).forEach(([id, met]) => document.getElementById(id).classList.toggle('met', met));
            passwordInput.setCustomValidity(!password || (checks.passwordLength && checks.passwordLetter && checks.passwordNumber && checks.passwordLimit) ? '' : 'Use at least 8 characters with a letter and a number, up to 72 bytes.');
            confirmInput.setCustomValidity(confirmInput.value && confirmInput.value !== passwordInput.value ? 'Passwords do not match.' : '');
        }
        if (passwordInput && confirmInput) {
            passwordInput.addEventListener('input', validatePasswords);
            confirmInput.addEventListener('input', validatePasswords);
        }
        document.querySelectorAll('[data-password]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.password);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(show));
                button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${input.id === 'confirm_password' ? 'confirm password' : 'password'}`);
                button.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        });
    </script>

    <?php include 'chatbot_widget.php'; ?>
</body>
</html>
