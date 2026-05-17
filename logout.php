<?php
ini_set('session.use_strict_mode', 1);
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
