<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'dept_manager', 'section_chief']);
$stockType = 'in';
include __DIR__ . '/includes/stock_module.php';
