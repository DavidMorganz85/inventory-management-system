<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);
$user = current_user();

$departments = $pdo->query(
    "SELECT d.id, d.name, b.name AS branch_name FROM departments d JOIN branches b ON d.branch_id=b.id ORDER BY b.name, d.name"
)->fetchAll();
$assignable = $pdo->query("SELECT id, full_name, email, role, section_id FROM users WHERE role != 'admin' ORDER BY full_name")->fetchAll();

$sections = $pdo->query(
    "SELECT s.*, d.name AS dept_name, b.name AS branch_name, u.full_name AS chief_name,
        (SELECT COUNT(*) FROM items i WHERE i.section_id = s.id) AS item_count
     FROM sections s
     JOIN departments d ON s.department_id = d.id
     JOIN branches b ON d.branch_id = b.id
     LEFT JOIN users u ON s.chief_id = u.id
     ORDER BY b.name, d.name, s.name"
)->fetchAll();

$pageTitle = 'Sections';
$breadcrumb = 'Smallest unit of the hierarchy — items live here, each with one Section Chief';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">All Sections (<?= count($sections) ?>)</h2>
        <button class="btn btn-primary" onclick="document.getElementById('createSectionModal').classList.add('active')" <?= !$departments ? 'disabled title="Create a department first"' : '' ?>>+ New Section</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Section</th><th>Department</th><th>Branch</th><th>Section Chief</th><th>Items</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($sections as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= e($s['dept_name']) ?></td>
                    <td><?= e($s['branch_name']) ?></td>
                    <td><?= $s['chief_name'] ? e($s['chief_name']) : '<span class="badge badge-gray">Unassigned</span>' ?></td>
                    <td><?= (int)$s['item_count'] ?></td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick='openEditSection(<?= json_encode($s) ?>)'>Edit</button>
                        <form method="POST" action="/uiri-ims/actions/section_actions.php" onsubmit="return confirm('Delete this section?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sections): ?><tr><td colspan="6" class="text-muted">No sections created yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createSectionModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('createSectionModal').classList.remove('active')">&times;</button>
        <h3>Create New Section</h3>
        <form method="POST" action="/uiri-ims/actions/section_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="create">
            <div class="form-group"><label>Section Name</label><input type="text" name="name" required placeholder="e.g. Networking Unit"></div>
            <div class="form-group"><label>Department</label>
                <select name="department_id" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['branch_name']) ?> — <?= e($d['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Section Chief (optional)</label>
                <select name="chief_id">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($assignable as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Selecting a user here will set their role to Section Chief for this section.</div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Create Section</button></div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editSectionModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('editSectionModal').classList.remove('active')">&times;</button>
        <h3>Edit Section</h3>
        <form method="POST" action="/uiri-ims/actions/section_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="update">
            <input type="hidden" name="id" id="edit_section_id">
            <div class="form-group"><label>Section Name</label><input type="text" name="name" id="edit_section_name" required></div>
            <div class="form-group"><label>Department</label>
                <select name="department_id" id="edit_section_dept" required>
                    <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['branch_name']) ?> — <?= e($d['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Section Chief</label>
                <select name="chief_id" id="edit_section_chief">
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
function openEditSection(s) {
    document.getElementById('edit_section_id').value = s.id;
    document.getElementById('edit_section_name').value = s.name;
    document.getElementById('edit_section_dept').value = s.department_id;
    document.getElementById('edit_section_chief').value = s.chief_id || '';
    document.getElementById('editSectionModal').classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
