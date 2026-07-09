<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);
$user = current_user();

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$managers = $pdo->query("SELECT id, full_name, email, department_id FROM users WHERE role IN ('dept_manager') OR (role='admin' AND 1=0) ORDER BY full_name")->fetchAll();
// Allow assigning any non-admin, non-section-chief-locked user OR promote a fresh user with no scope yet
$assignable = $pdo->query("SELECT id, full_name, email, role, department_id FROM users WHERE role != 'admin' ORDER BY full_name")->fetchAll();

$departments = $pdo->query(
    "SELECT d.*, b.name AS branch_name, u.full_name AS manager_name,
        (SELECT COUNT(*) FROM sections s WHERE s.department_id = d.id) AS section_count,
        (SELECT COUNT(*) FROM items i JOIN sections s ON i.section_id=s.id WHERE s.department_id=d.id) AS item_count
     FROM departments d
     JOIN branches b ON d.branch_id = b.id
     LEFT JOIN users u ON d.manager_id = u.id
     ORDER BY b.name, d.name"
)->fetchAll();

$pageTitle = 'Departments';
$breadcrumb = 'Organized under each branch, each with one Department Manager';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">All Departments (<?= count($departments) ?>)</h2>
        <button class="btn btn-primary" onclick="document.getElementById('createDeptModal').classList.add('active')" <?= !$branches ? 'disabled title="Create a branch first"' : '' ?>>+ New Department</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Department</th><th>Branch</th><th>Manager</th><th>Sections</th><th>Items</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td><strong><?= e($d['name']) ?></strong></td>
                    <td><?= e($d['branch_name']) ?></td>
                    <td><?= $d['manager_name'] ? e($d['manager_name']) : '<span class="badge badge-gray">Unassigned</span>' ?></td>
                    <td><span class="badge badge-navy"><?= (int)$d['section_count'] ?></span></td>
                    <td><?= (int)$d['item_count'] ?></td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick='openEditDept(<?= json_encode($d) ?>)'>Edit</button>
                        <form method="POST" action="/uiri-ims/actions/department_actions.php" onsubmit="return confirm('Delete this department?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$departments): ?><tr><td colspan="6" class="text-muted">No departments created yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createDeptModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('createDeptModal').classList.remove('active')">&times;</button>
        <h3>Create New Department</h3>
        <form method="POST" action="/uiri-ims/actions/department_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="create">
            <div class="form-group"><label>Department Name</label><input type="text" name="name" required placeholder="e.g. ICT Department"></div>
            <div class="form-group"><label>Branch</label>
                <select name="branch_id" required>
                    <option value="">-- Select Branch --</option>
                    <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Department Manager (optional)</label>
                <select name="manager_id">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($assignable as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Selecting a user here will set their role to Department Manager for this department.</div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Create Department</button></div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editDeptModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('editDeptModal').classList.remove('active')">&times;</button>
        <h3>Edit Department</h3>
        <form method="POST" action="/uiri-ims/actions/department_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="update">
            <input type="hidden" name="id" id="edit_dept_id">
            <div class="form-group"><label>Department Name</label><input type="text" name="name" id="edit_dept_name" required></div>
            <div class="form-group"><label>Branch</label>
                <select name="branch_id" id="edit_dept_branch" required>
                    <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Department Manager</label>
                <select name="manager_id" id="edit_dept_manager">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($assignable as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function openEditDept(d) {
    document.getElementById('edit_dept_id').value = d.id;
    document.getElementById('edit_dept_name').value = d.name;
    document.getElementById('edit_dept_branch').value = d.branch_id;
    document.getElementById('edit_dept_manager').value = d.manager_id || '';
    document.getElementById('editDeptModal').classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
