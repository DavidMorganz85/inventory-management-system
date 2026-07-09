<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);
$user = current_user();

$branches = $pdo->query(
    "SELECT b.*,
        (SELECT COUNT(*) FROM departments d WHERE d.branch_id = b.id) AS dept_count,
        (SELECT COUNT(*) FROM items i JOIN sections s ON i.section_id=s.id JOIN departments d ON s.department_id=d.id WHERE d.branch_id=b.id) AS item_count
     FROM branches b ORDER BY b.name"
)->fetchAll();

$pageTitle = 'Branches';
$breadcrumb = 'Top level of the organizational hierarchy';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">All Branches (<?= count($branches) ?>)</h2>
        <button class="btn btn-primary" onclick="document.getElementById('createBranchModal').classList.add('active')">+ New Branch</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Branch Name</th><th>Location</th><th>Departments</th><th>Items</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($branches as $b): ?>
                <tr>
                    <td><strong><?= e($b['name']) ?></strong></td>
                    <td><?= e($b['location']) ?></td>
                    <td><span class="badge badge-navy"><?= (int)$b['dept_count'] ?></span></td>
                    <td><?= (int)$b['item_count'] ?></td>
                    <td class="text-muted"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick='openEditBranch(<?= json_encode($b) ?>)'>Edit</button>
                        <form method="POST" action="/uiri-ims/actions/branch_actions.php" onsubmit="return confirm('Delete this branch? This cannot be undone.');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$branches): ?><tr><td colspan="6" class="text-muted">No branches created yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createBranchModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('createBranchModal').classList.remove('active')">&times;</button>
        <h3>Create New Branch</h3>
        <form method="POST" action="/uiri-ims/actions/branch_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="create">
            <div class="form-group"><label>Branch Name</label><input type="text" name="name" required placeholder="e.g. Nakawa HQ"></div>
            <div class="form-group"><label>Location</label><input type="text" name="location" placeholder="e.g. Nakawa Industrial Area, Kampala"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Create Branch</button></div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editBranchModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('editBranchModal').classList.remove('active')">&times;</button>
        <h3>Edit Branch</h3>
        <form method="POST" action="/uiri-ims/actions/branch_actions.php" id="editBranchForm">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="update">
            <input type="hidden" name="id" id="edit_branch_id">
            <div class="form-group"><label>Branch Name</label><input type="text" name="name" id="edit_branch_name" required></div>
            <div class="form-group"><label>Location</label><input type="text" name="location" id="edit_branch_location"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function openEditBranch(b) {
    document.getElementById('edit_branch_id').value = b.id;
    document.getElementById('edit_branch_name').value = b.name;
    document.getElementById('edit_branch_location').value = b.location || '';
    document.getElementById('editBranchModal').classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
