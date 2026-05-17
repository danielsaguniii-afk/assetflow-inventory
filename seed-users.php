<?php
require_once 'includes/db.php';
$db = getDB();

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('admin','staff') DEFAULT 'staff',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("DELETE FROM users WHERE username IN ('admin','staff')");
$db->prepare("INSERT INTO users (username,password,full_name,role,status) VALUES (?,?,?,?,?)")->execute(['admin','admin123','System Administrator','admin','active']);
$db->prepare("INSERT INTO users (username,password,full_name,role,status) VALUES (?,?,?,?,?)")->execute(['staff','staff123','Staff User','staff','active']);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seed Done</title>
<style>body{font-family:monospace;background:#0e0f11;color:#edeae4;padding:2rem;}.ok{color:#34d399;}.btn{display:inline-block;margin-top:1rem;padding:10px 24px;background:#c8ff57;color:#000;border-radius:8px;text-decoration:none;font-weight:bold;}</style>
</head><body>
<h2 class="ok">✓ Users created successfully!</h2>
<p>Username: <strong>admin</strong> | Password: <strong>admin123</strong></p>
<p>Username: <strong>staff</strong> | Password: <strong>staff123</strong></p>
<a class="btn" href="login.php">→ Go to Login NOW</a>
</body></html>
