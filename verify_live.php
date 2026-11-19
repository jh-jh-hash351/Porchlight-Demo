<?php
try {
    $root = 'C:\xampp\htdocs\porchlight\includes';
    $dbPath = $root . '\porchlight.db';
    
    if (!file_exists($dbPath)) {
        die("ERROR: Database file NOT found at $dbPath\n");
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "--- XAMPP Live Database Check ---\n";
    $users = $db->query("SELECT user_id, username, status FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users in Live DB: " . count($users) . "\n";
    foreach ($users as $u) {
        echo "ID: {$u['user_id']} | User: {$u['username']} | Status: {$u['status']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
