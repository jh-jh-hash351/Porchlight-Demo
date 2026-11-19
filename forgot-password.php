<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/icons.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
                $stmt->execute([$token, $expires, $user['id']]);

                // In a real app, you would send this link via email.
                // Since this is a local XAMPP site, we'll show the link for testing.
                $resetLink = "http://localhost/porchlight/reset-password.php?token=" . $token;
                $success = 'A reset link has been generated! <br><br><strong>For testing, here is your link:</strong><br><a href="' . $resetLink . '" style="color: var(--accent); font-weight: 600;">Reset your password now</a>';
            } else {
                // Security tip: don't reveal if the email exists or not, but for a simple site we can.
                $error = 'No account found with that email address.';
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
    <title>Forgot Password - Porchlight</title>
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
            <h1>Reset Password</h1>
            <p>Enter your email to get a reset link</p>
        </div>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-success"><?= $success ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Send Reset Link</button>
        </form>

        <div class="auth-footer">
            Remembered your password? <a href="login.php">Sign In</a><br>
            <a href="index.php" style="display: block; margin-top: 15px; font-size: 13px; color: var(--text-muted);">← Back to Home</a>
        </div>
    </div>
</body>
</html>
