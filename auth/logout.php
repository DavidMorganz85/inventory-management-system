<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    log_audit($pdo, $_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], null);
}

$_SESSION = [];
session_destroy();
header('Location: /uiri-ims/auth/login.php');
exit;
