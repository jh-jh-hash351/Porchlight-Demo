<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/icons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groups - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
</head>
<body>
    <button class="icon-btn theme-toggle theme-float" aria-label="Toggle dark mode">
        <span class="icon-sun"><?= icon('sun') ?></span>
        <span class="icon-moon"><?= icon('moon') ?></span>
    </button>
    <div class="layout" style="justify-content: center; align-items: center; height: 80vh; text-align: center;">
        <div class="panel" style="max-width: 500px; padding: 40px;">
            <h1 style="font-family: var(--font-serif); margin-bottom: 16px;">Groups</h1>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">Join local neighborhood groups and circles.</p>
            <a href="index.php" class="btn btn-primary">Back to Feed</a>
        </div>
    </div>
</body>
</html>
