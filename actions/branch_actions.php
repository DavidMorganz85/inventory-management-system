<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin']); // Only admins manage branches
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /uiri-ims/admin/branches.php'); exit; }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';

try {
    if ($op === 'create') {
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($name === '') throw new Exception('Branch name is required.');

        $stmt = $pdo->prepare("INSERT INTO branches (name, location) VALUES (?, ?)");
        $stmt->execute([$name, $location]);
        log_audit($pdo, $user['id'], 'create_branch', 'branch', (int)$pdo->lastInsertId(), "Created branch '$name'");
        set_flash('success', "Branch \"$name\" created successfully.");

    } elseif ($op === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($name === '') throw new Exception('Branch name is required.');

        $stmt = $pdo->prepare("UPDATE branches SET name = ?, location = ? WHERE id = ?");
        $stmt->execute([$name, $location, $id]);
        log_audit($pdo, $user['id'], 'update_branch', 'branch', $id, "Updated branch '$name'");
        set_flash('success', "Branch updated successfully.");

    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        // Prevent deletion if departments still exist under this branch
        $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE branch_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            throw new Exception('Cannot delete a branch that still has departments. Remove or reassign departments first.');
        }

        $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
        $stmt->execute([$id]);
        log_audit($pdo, $user['id'], 'delete_branch', 'branch', $id, "Deleted branch '$name'");
        set_flash('success', "Branch deleted successfully.");
    }
} catch (Exception $e) {
    set_flash('danger', $e->getMessage());
}

header('Location: /uiri-ims/admin/branches.php');
exit;
