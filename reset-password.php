<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/icons.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    die('Invalid request. No token provided.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($password === '' || $confirmPassword === '') {
        $error = 'Both password fields are required.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Verify token and expiration
            $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()');
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Update password and clear the token
                $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
                $stmt->execute([$hashedPassword, $user['id']]);
                $success = 'Your password has been reset! You can now <a href="login.php">log in</a>.';
            } else {
                $error = 'This reset link is invalid or has expired.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Porchlight</title>
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
        .auth-success {
            color: var(--positive);
            font-size: 14px;
            text-align: center;
            margin-bottom: 16px;
            line-height: 1.6;
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
            <h1>New Password</h1>
            <p>Enter your new password below</p>
        </div>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-success"><?= $success ?></div>
        <?php else: ?>
            <form class="auth-form" method="POST">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        <?php endif; ?>

        <div class="auth-footer">
            <a href="login.php">Return to Sign In</a><br>
            <a href="index.php" style="display: block; margin-top: 15px; font-size: 13px; color: var(--text-muted);">← Back to Home</a>
        </div>
    </div>
</body>
</html>
