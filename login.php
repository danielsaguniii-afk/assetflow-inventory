<?php
session_save_path(sys_get_temp_dir());
session_start();

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    require_once 'includes/db.php';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password']) {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'name'     => $user['full_name'],
            'role'     => $user['role'],
        ];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AssetFlow — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=Instrument+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:#0e0f11; --surface:#16181c; --surface2:#1e2026;
  --border:rgba(255,255,255,0.08); --border2:rgba(255,255,255,0.14);
  --text:#edeae4; --muted:#7c7a85;
  --accent:#c8ff57; --accent-d:rgba(200,255,87,0.13);
  --danger:#ff5f57; --danger-d:rgba(255,95,87,0.13);
}
body { font-family:'Instrument Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
.wrap { width:100%; max-width:400px; }
.logo { display:flex; align-items:center; gap:10px; margin-bottom:2rem; justify-content:center; }
.logo-mark { width:38px; height:38px; background:var(--accent); border-radius:10px; display:grid; place-items:center; }
.logo-text { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; }
.logo-text span { color:var(--accent); }
.card { background:var(--surface); border:1px solid var(--border2); border-radius:14px; padding:2rem; }
.card-title { font-family:'Syne',sans-serif; font-size:18px; font-weight:700; margin-bottom:4px; }
.card-sub { font-size:13px; color:var(--muted); margin-bottom:1.75rem; }
.form-group { display:flex; flex-direction:column; gap:5px; margin-bottom:1rem; }
label { font-size:11.5px; font-weight:500; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); }
input[type=text], input[type=password] { background:var(--surface2); border:1px solid var(--border2); border-radius:8px; color:var(--text); font-family:'Instrument Sans',sans-serif; font-size:14px; padding:0.65rem 0.9rem; outline:none; width:100%; transition:border-color .15s, box-shadow .15s; }
input:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-d); }
input::placeholder { color:#454350; }
.btn-login { width:100%; height:42px; background:var(--accent); color:#0e0f11; border:none; border-radius:8px; font-family:'Instrument Sans',sans-serif; font-size:14px; font-weight:600; cursor:pointer; margin-top:0.5rem; transition:background .15s; }
.btn-login:hover { background:#b0e83e; }
.error { display:flex; align-items:center; gap:8px; background:var(--danger-d); border:1px solid rgba(255,95,87,.25); color:var(--danger); border-radius:8px; padding:.65rem .9rem; font-size:13px; margin-bottom:1rem; }
.hint { background:var(--accent-d); border:1px solid rgba(200,255,87,.18); border-radius:8px; padding:.75rem 1rem; font-size:12.5px; color:var(--muted); margin-top:1.25rem; }
.hint strong { color:var(--accent); font-family:monospace; }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-mark">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0e0f11" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="20">
        <rect x="2" y="7" width="20" height="14" rx="2"/>
        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
        <line x1="12" y1="12" x2="12" y2="17"/>
        <line x1="9.5" y1="14.5" x2="14.5" y2="14.5"/>
      </svg>
    </div>
    <span class="logo-text">Asset<span>Flow</span></span>
  </div>
  <div class="card">
    <div class="card-title">Sign in</div>
    <div class="card-sub">Enter your credentials to continue.</div>

    <?php if ($error): ?>
    <div class="error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus placeholder="Enter username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Enter password">
      </div>
      <button type="submit" class="btn-login">Sign In</button>
    </form>

   
  </div>
</div>
</body>
</html>
