<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin']); // Only admins manage users and roles
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /uiri-ims/admin/users.php'); exit; }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';

try {
    if ($op === 'create') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $branchId = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $sectionId = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
        $canPhysicalAudit = !empty($_POST['can_physical_audit']) ? 1 : 0;
        $canDelegateView = !empty($_POST['can_delegate_view']) ? 1 : 0;
        $canProcurementLiaison = !empty($_POST['can_procurement_liaison']) ? 1 : 0;
        $canViewReportsOwnSection = !empty($_POST['can_view_reports_own_section']) ? 1 : 0;
        $canRequestWriteoff = !empty($_POST['can_request_writeoff']) ? 1 : 0;

        if ($fullName === '' || $email === '' || $password === '' || !in_array($role, ['admin','dept_manager','section_chief'], true)) {
            throw new Exception('All fields are required and role must be valid.');
        }
        if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters.');

        // Enforce scope consistency
        if ($role === 'admin') { $branchId = $departmentId = $sectionId = null; }
        if ($role === 'dept_manager') { $sectionId = null; if (!$branchId || !$departmentId) throw new Exception('Department managers require a branch and department.'); }
        if ($role === 'section_chief') { if (!$branchId || !$departmentId || !$sectionId) throw new Exception('Section chiefs require a branch, department and section.'); }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, password, role, branch_id, department_id, section_id, status,
                can_physical_audit, can_delegate_view, can_procurement_liaison,
                can_view_reports_own_section, can_request_writeoff)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $fullName, $email, $hash, $role, $branchId, $departmentId, $sectionId,
            $canPhysicalAudit, $canDelegateView, $canProcurementLiaison,
            $canViewReportsOwnSection, $canRequestWriteoff
        ]);
        $newId = (int)$pdo->lastInsertId();

        if ($role === 'dept_manager' && $departmentId) {
            $pdo->prepare("UPDATE departments SET manager_id=? WHERE id=?")->execute([$newId, $departmentId]);
        }
        if ($role === 'section_chief' && $sectionId) {
            $pdo->prepare("UPDATE sections SET chief_id=? WHERE id=?")->execute([$newId, $sectionId]);
        }
        $pdo->commit();

        log_audit($pdo, $user['id'], 'create_user', 'user', $newId, "Created user '$fullName' ($role)");
        set_flash('success', "User \"$fullName\" created successfully.");

    } elseif ($op === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['active','disabled'], true)) throw new Exception('Invalid status.');
        if ($id === $user['id']) throw new Exception('You cannot disable your own account.');

        if ($status === 'active') {
            $pdo->prepare("UPDATE users SET status='active', failed_login_attempts=0, last_failed_login=NULL WHERE id=?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE users SET status='disabled' WHERE id=?")->execute([$id]);
        }
        log_audit($pdo, $user['id'], 'update_user_status', 'user', $id, "Set status to $status");
        set_flash('success', "User status updated.");

    } elseif ($op === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters.');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
        log_audit($pdo, $user['id'], 'reset_password', 'user', $id, "Password reset by admin");
        set_flash('success', "Password reset successfully.");

    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $user['id']) throw new Exception('You cannot delete your own account.');

        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE departments SET manager_id=NULL WHERE manager_id=?")->execute([$id]);
        $pdo->prepare("UPDATE sections SET chief_id=NULL WHERE chief_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $pdo->commit();

        log_audit($pdo, $user['id'], 'delete_user', 'user', $id, "Deleted user '$name'");
        set_flash('success', "User deleted successfully.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('danger', $e->getMessage());
}

header('Location: /uiri-ims/admin/users.php');
exit;
