<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/accounts.php';
require_once __DIR__ . '/includes/icons.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$addAccount = isset($_GET['add']) && $_GET['add'] === '1';

if (isset($_SESSION['user']) && !$addAccount) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $addAccount = !empty($_POST['add_account']);

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter both your email/username and password.';
    } else {
        try {
            // Rate Limiting Check
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmtLimit = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
            $stmtLimit->execute([$ip]);
            if ($stmtLimit->fetchColumn() >= 5) {
                die('Too many failed login attempts. Please try again in 15 minutes.');
            }

            // Look for user by email or username
            $stmt = $pdo->prepare('SELECT id, username, password, status, profile_pic FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'Active') {
                    $error = 'Your account is currently inactive or banned.';
                } else {
                    // Log success
                    $stmtLog = $pdo->prepare('INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, 1)');
                    $stmtLog->execute([$ip, $identifier]);

                    // Keep the previously signed-in account in the switcher
                    if (isset($_SESSION['user'])) {
                        $stmtOld = $pdo->prepare('SELECT id, username, profile_pic FROM users WHERE id = ?');
                        $stmtOld->execute([$_SESSION['user']['id']]);
                        if ($oldAccount = $stmtOld->fetch()) {
                            remember_account($oldAccount);
                        }
                    }

                    // Regenerate session ID for security
                    session_regenerate_id(true);

                    // Store basic user info in session
                    $_SESSION['user'] = session_user_from_row($user);
                    remember_account($user);

                    header('Location: index.php');
                    exit;
                }
            } else {
                // Log failure
                $stmtLog = $pdo->prepare('INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, 0)');
                $stmtLog->execute([$ip, $identifier]);
                $error = 'Invalid email/username or password.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred.'; // Generic error message
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
    <style>
        .auth-container {
            max-width: 400px;
            margin: 80px auto;
            padding: 32px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-header h1 {
            font-family: var(--font-serif);
            font-size: 28px;
            margin-bottom: 8px;
        }
        .auth-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-group input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-control);
            background: var(--surface-muted);
            outline: none;
        }
        .form-group input:focus {
            border-color: var(--accent);
        }
        .auth-error {
            color: var(--like);
            font-size: 14px;
            text-align: center;
            margin-bottom: 16px;
        }
        .auth-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <button class="icon-btn theme-toggle theme-float" aria-label="Toggle dark mode">
        <span class="icon-sun"><?= icon('sun') ?></span>
        <span class="icon-moon"><?= icon('moon') ?></span>
    </button>
    <div class="auth-container">
        <div class="auth-header">
            <h1><?= $addAccount ? 'Add Account' : 'Welcome Back' ?></h1>
            <p><?= $addAccount ? 'Sign in to another account to switch between them' : "Sign in to see what's happening" ?></p>
        </div>

        <?php if ($error): ?>
            <div class="auth-error"><?= security_encode($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <?php if ($addAccount): ?>
                <input type="hidden" name="add_account" value="1">
            <?php endif; ?>
            <div class="form-group">
                <label for="identifier">Email or Username</label>
                <input type="text" id="identifier" name="identifier" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>

        <div class="auth-footer">
            <div style="margin-bottom: 10px;"><a href="forgot-password.php" style="font-size: 13px; color: var(--text-secondary);">Forgot password?</a></div>
            <?php if ($addAccount): ?>
                <a href="settings.php" style="font-size: 13px; color: var(--text-muted);">Cancel and go back to settings</a>
            <?php else: ?>
                Don't have an account? <a href="signup.php">Create one</a><br>
                <a href="index.php" style="display: block; margin-top: 15px; font-size: 13px; color: var(--text-muted);">← Back to Home</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
