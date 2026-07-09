<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin']); // Only admins create/delete/reassign sections
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /uiri-ims/admin/sections.php'); exit; }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';

try {
    if ($op === 'create') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $chiefId = !empty($_POST['chief_id']) ? (int)$_POST['chief_id'] : null;
        if ($name === '' || $deptId <= 0) throw new Exception('Section name and department are required.');

        $dept = $pdo->prepare("SELECT branch_id FROM departments WHERE id=?");
        $dept->execute([$deptId]);
        $branchId = $dept->fetchColumn();
        if (!$branchId) throw new Exception('Invalid department selected.');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO sections (department_id, name, chief_id) VALUES (?, ?, ?)");
        $stmt->execute([$deptId, $name, $chiefId]);
        $sectionId = (int)$pdo->lastInsertId();

        if ($chiefId) {
            $upd = $pdo->prepare("UPDATE users SET role='section_chief', branch_id=?, department_id=?, section_id=? WHERE id=?");
            $upd->execute([$branchId, $deptId, $sectionId, $chiefId]);
        }
        $pdo->commit();

        log_audit($pdo, $user['id'], 'create_section', 'section', $sectionId, "Created section '$name'");
        set_flash('success', "Section \"$name\" created successfully.");

    } elseif ($op === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        $chiefId = !empty($_POST['chief_id']) ? (int)$_POST['chief_id'] : null;
        if ($name === '' || $deptId <= 0) throw new Exception('Section name and department are required.');

        $dept = $pdo->prepare("SELECT branch_id FROM departments WHERE id=?");
        $dept->execute([$deptId]);
        $branchId = $dept->fetchColumn();

        $pdo->beginTransaction();

        $prev = $pdo->prepare("SELECT chief_id FROM sections WHERE id=?");
        $prev->execute([$id]);
        $prevChiefId = $prev->fetchColumn();
        if ($prevChiefId && $prevChiefId != $chiefId) {
            $pdo->prepare("UPDATE users SET section_id=NULL WHERE id=? AND role='section_chief'")->execute([$prevChiefId]);
        }

        $stmt = $pdo->prepare("UPDATE sections SET name=?, department_id=?, chief_id=? WHERE id=?");
        $stmt->execute([$name, $deptId, $chiefId, $id]);

        if ($chiefId) {
            $upd = $pdo->prepare("UPDATE users SET role='section_chief', branch_id=?, department_id=?, section_id=? WHERE id=?");
            $upd->execute([$branchId, $deptId, $id, $chiefId]);
        }
        $pdo->commit();

        log_audit($pdo, $user['id'], 'update_section', 'section', $id, "Updated section '$name'");
        set_flash('success', "Section updated successfully.");

    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT name FROM sections WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        $check = $pdo->prepare("SELECT COUNT(*) FROM items WHERE section_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            throw new Exception('Cannot delete a section that still has inventory items. Remove or reassign items first.');
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET section_id=NULL WHERE section_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$id]);
        $pdo->commit();

        log_audit($pdo, $user['id'], 'delete_section', 'section', $id, "Deleted section '$name'");
        set_flash('success', "Section deleted successfully.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('danger', $e->getMessage());
}

header('Location: /uiri-ims/admin/sections.php');
exit;
