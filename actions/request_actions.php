<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['section_chief']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /uiri-ims/chief/requests.php'); exit; }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';

try {
    if ($op !== 'create') {
        throw new Exception('Invalid request.');
    }

    if (!$user['section_id']) {
        throw new Exception('Your account is not assigned to a section.');
    }

    $type = $_POST['type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $allowed = [];
    if ($user['can_procurement_liaison']) { $allowed[] = 'procurement'; }
    if ($user['can_request_writeoff']) { $allowed[] = 'writeoff'; }

    if ($title === '' || $description === '' || !in_array($type, $allowed, true)) {
        throw new Exception('Please provide a valid request type, title and description.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO section_requests (section_id, user_id, request_type, title, description)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user['section_id'], $user['id'], $type, $title, $description]);

    log_audit($pdo, $user['id'], 'create_request', 'section_request', (int)$pdo->lastInsertId(), "Submitted $type request: $title");
    set_flash('success', 'Your request has been submitted successfully.');
} catch (Exception $e) {
    set_flash('danger', $e->getMessage());
}

header('Location: /uiri-ims/chief/requests.php');
exit;
