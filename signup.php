<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Role defaults to 'User' in DB schema
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
                $stmt->execute([$username, $email, $hashedPassword]);
                $success = 'Account created! You can now <a href="login.php">log in</a>.';
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
    <title>Sign Up - Porchlight</title>
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
            <h1>Join Porchlight</h1>
            <p>Connect with your neighborhood</p>
        </div>

        <?php if ($error): ?>
            <div class="auth-error"><?= security_encode($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-success"><?= $success ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign In</a><br>
            <a href="index.php" style="display: block; margin-top: 15px; font-size: 13px; color: var(--text-muted);">← Back to Home</a>
        </div}
    </div>
</body>
</html>
