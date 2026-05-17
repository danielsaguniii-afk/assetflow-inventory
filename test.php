<?php
// Test 1: PHP working?
echo "<h2 style='color:green'>✓ PHP is working</h2>";

// Test 2: Session
session_save_path(sys_get_temp_dir());
session_start();
echo "<h2 style='color:green'>✓ Session started</h2>";

// Test 3: DB connect
try {
    $pdo = new PDO("mysql:host=localhost;dbname=inventory_db;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    echo "<h2 style='color:green'>✓ Database connected</h2>";

    // Test 4: Users table
    $users = $pdo->query("SELECT id, username, password, status FROM users")->fetchAll();
    echo "<h2 style='color:green'>✓ Users table found (" . count($users) . " users)</h2>";
    echo "<pre style='background:#111;color:#eee;padding:1rem;'>";
    foreach($users as $u) {
        echo "ID: {$u['id']} | Username: {$u['username']} | Password: {$u['password']} | Status: {$u['status']}\n";
    }
    echo "</pre>";

} catch(Exception $e) {
    echo "<h2 style='color:red'>✗ DB Error: " . $e->getMessage() . "</h2>";
}
?>
