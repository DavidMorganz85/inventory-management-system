<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin']); // Only admins create/delete/reassign departments
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /uiri-ims/admin/departments.php'); exit; }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';

try {
    if ($op === 'create') {
        $branchId = (int)($_POST['branch_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $managerId = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        if ($name === '' || $branchId <= 0) throw new Exception('Department name and branch are required.');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO departments (branch_id, name, manager_id) VALUES (?, ?, ?)");
        $stmt->execute([$branchId, $name, $managerId]);
        $deptId = (int)$pdo->lastInsertId();

        if ($managerId) {
            $upd = $pdo->prepare("UPDATE users SET role='dept_manager', branch_id=?, department_id=?, section_id=NULL WHERE id=?");
            $upd->execute([$branchId, $deptId, $managerId]);
        }
        $pdo->commit();

        log_audit($pdo, $user['id'], 'create_department', 'department', $deptId, "Created department '$name'");
        set_flash('success', "Department \"$name\" created successfully.");

    } elseif ($op === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $branchId = (int)($_POST['branch_id'] ?? 0);
        $managerId = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        if ($name === '' || $branchId <= 0) throw new Exception('Department name and branch are required.');

        $pdo->beginTransaction();

        // Unbind previous manager if changed
        $prev = $pdo->prepare("SELECT manager_id FROM departments WHERE id=?");
        $prev->execute([$id]);
        $prevManagerId = $prev->fetchColumn();
        if ($prevManagerId && $prevManagerId != $managerId) {
            $pdo->prepare("UPDATE users SET department_id=NULL WHERE id=? AND role='dept_manager'")->execute([$prevManagerId]);
        }

        $stmt = $pdo->prepare("UPDATE departments SET name=?, branch_id=?, manager_id=? WHERE id=?");
        $stmt->execute([$name, $branchId, $managerId, $id]);

        if ($managerId) {
            $upd = $pdo->prepare("UPDATE users SET role='dept_manager', branch_id=?, department_id=?, section_id=NULL WHERE id=?");
            $upd->execute([$branchId, $id, $managerId]);
        }
        $pdo->commit();

        log_audit($pdo, $user['id'], 'update_department', 'department', $id, "Updated department '$name'");
        set_flash('success', "Department updated successfully.");

    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        $check = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE department_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            throw new Exception('Cannot delete a department that still has sections. Remove sections first.');
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET department_id=NULL WHERE department_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
        $pdo->commit();

        log_audit($pdo, $user['id'], 'delete_department', 'department', $id, "Deleted department '$name'");
        set_flash('success', "Department deleted successfully.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('danger', $e->getMessage());
}

header('Location: /uiri-ims/admin/departments.php');
exit;
