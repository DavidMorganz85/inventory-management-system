<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect_to_dashboard();
} else {
    header('Location: /uiri-ims/auth/login.php');
    exit;
}
