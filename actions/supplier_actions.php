<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /uiri-ims/admin/suppliers.php'); exit; }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';

try {
    if ($op === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new Exception('Supplier name is required.');
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, trim($_POST['contact_person'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['address'] ?? '')]);
        log_audit($pdo, $user['id'], 'create_supplier', 'supplier', (int)$pdo->lastInsertId(), "Created supplier '$name'");
        set_flash('success', "Supplier \"$name\" added successfully.");

    } elseif ($op === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new Exception('Supplier name is required.');
        $stmt = $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?");
        $stmt->execute([$name, trim($_POST['contact_person'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['address'] ?? ''), $id]);
        log_audit($pdo, $user['id'], 'update_supplier', 'supplier', $id, "Updated supplier '$name'");
        set_flash('success', "Supplier updated successfully.");

    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE id=?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        $pdo->prepare("UPDATE items SET supplier_id=NULL WHERE supplier_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]);
        log_audit($pdo, $user['id'], 'delete_supplier', 'supplier', $id, "Deleted supplier '$name'");
        set_flash('success', "Supplier deleted successfully.");
    }
} catch (Exception $e) {
    set_flash('danger', $e->getMessage());
}

header('Location: /uiri-ims/admin/suppliers.php');
exit;
