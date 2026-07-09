<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);
$user = current_user();

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT id, name, branch_id FROM departments ORDER BY name")->fetchAll();
$sections = $pdo->query("SELECT id, name, department_id FROM sections ORDER BY name")->fetchAll();

$users = $pdo->query(
    "SELECT u.*, b.name AS branch_name, d.name AS dept_name, s.name AS section_name
     FROM users u
     LEFT JOIN branches b ON u.branch_id = b.id
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN sections s ON u.section_id = s.id
     ORDER BY FIELD(u.role,'admin','dept_manager','section_chief'), u.full_name"
)->fetchAll();

$pageTitle = 'Users & Roles';
$breadcrumb = 'Create accounts and assign roles across the organization';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">All Users (<?= count($users) ?>)</h2>
        <button class="btn btn-primary" onclick="document.getElementById('createUserModal').classList.add('active')">+ New User</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Permissions</th><th>Scope</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="user-row-name">
                            <?php if (!empty($u['profile_picture'])): ?>
                                <img src="/uiri-ims/uploads/profiles/<?= e($u['profile_picture']) ?>" alt="Profile" class="user-row-avatar">
                            <?php else: ?>
                                <div class="user-row-avatar user-row-avatar-placeholder"><?= e(substr(preg_replace('/[^A-Z]/', '', strtoupper($u['full_name'])), 0, 2)) ?></div>
                            <?php endif; ?>
                            <strong><?= e($u['full_name']) ?></strong>
                        </div>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $u['role']==='admin' ? 'badge-danger' : ($u['role']==='dept_manager' ? 'badge-navy' : 'badge-warning') ?>"><?= e(role_label($u['role'])) ?></span>
                    </td>
                    <td style="font-size:12px;">
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge badge-dark">All privileges</span>
                        <?php elseif ($u['role'] === 'dept_manager'): ?>
                            <span class="badge badge-navy">Department Manager</span>
                        <?php elseif ($u['role'] === 'section_chief'): ?>
                            <?php
                                $permBadges = [];
                                if ($u['can_physical_audit']) $permBadges[] = '<span class="badge badge-sky">Auditor</span>';
                                if ($u['can_delegate_view']) $permBadges[] = '<span class="badge badge-purple">Delegate</span>';
                                if ($u['can_procurement_liaison']) $permBadges[] = '<span class="badge badge-orange">Procurement</span>';
                                if ($u['can_view_reports_own_section']) $permBadges[] = '<span class="badge badge-green">Reports</span>';
                                if ($u['can_request_writeoff']) $permBadges[] = '<span class="badge badge-red">Write-off</span>';
                                if ($permBadges) {
                                    echo implode(' ', $permBadges);
                                } else {
                                    echo '<span class="text-muted">No extra permissions</span>';
                                }
                            ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['status'] === 'active'): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick="openResetPassword(<?= (int)$u['id'] ?>, '<?= e($u['full_name']) ?>')">Reset PW</button>
                        <?php if ($u['id'] != $user['id']): ?>
                            <form method="POST" action="/uiri-ims/actions/user_actions.php" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="op" value="update_status">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="status" value="<?= $u['status']==='active'?'disabled':'active' ?>">
                                <button type="submit" class="btn btn-sm <?= $u['status']==='active' ? 'btn-outline' : 'btn-sky' ?>"><?= $u['status']==='active' ? 'Disable' : 'Enable' ?></button>
                            </form>
                            <form method="POST" action="/uiri-ims/actions/user_actions.php" onsubmit="return confirm('Permanently delete this user?');" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="op" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge-gray">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('createUserModal').classList.remove('active')">&times;</button>
        <h3>Create New User</h3>
        <form method="POST" action="/uiri-ims/actions/user_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="create">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="6"></div>
            <div class="form-group"><label>Role</label>
                <select name="role" id="new_user_role" onchange="toggleScopeFields()" required>
                    <option value="">-- Select Role --</option>
                    <option value="admin">Administrator</option>
                    <option value="dept_manager">Department Manager</option>
                    <option value="section_chief">Section Chief</option>
                </select>
            </div>
            <div id="scopeFields" style="display:none;">
                <div class="form-group"><label>Branch</label>
                    <select name="branch_id" id="new_user_branch" onchange="filterDeptOptions()">
                        <option value="">-- Select Branch --</option>
                        <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="deptFieldWrap"><label>Department</label>
                    <select name="department_id" id="new_user_dept" onchange="filterSectionOptions()">
                        <option value="">-- Select Department --</option>
                    </select>
                </div>
                <div class="form-group" id="sectionFieldWrap" style="display:none;"><label>Section</label>
                    <select name="section_id" id="new_user_section">
                        <option value="">-- Select Section --</option>
                    </select>
                </div>
                <div class="form-group" id="chiefPermissions" style="display:none; border:1px solid var(--border); padding: 12px; border-radius: 10px; background:#fbfcff;">
                    <label>Section Chief Permissions</label>
                    <div class="form-group"><label><input type="checkbox" name="can_physical_audit" value="1"> Physical Stock Auditor</label></div>
                    <div class="form-group"><label><input type="checkbox" name="can_delegate_view" value="1"> Read-only Delegate</label></div>
                    <div class="form-group"><label><input type="checkbox" name="can_procurement_liaison" value="1"> Procurement Liaison</label></div>
                    <div class="form-group"><label><input type="checkbox" name="can_view_reports_own_section" value="1"> Report Viewer (own section only)</label></div>
                    <div class="form-group"><label><input type="checkbox" name="can_request_writeoff" value="1"> Disposal/Write-off Requester</label></div>
                </div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Create User</button></div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPwModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('resetPwModal').classList.remove('active')">&times;</button>
        <h3>Reset Password for <span id="reset_pw_name"></span></h3>
        <form method="POST" action="/uiri-ims/actions/user_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="reset_password">
            <input type="hidden" name="id" id="reset_pw_id">
            <div class="form-group"><label>New Password</label><input type="password" name="password" required minlength="6"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Reset Password</button></div>
        </form>
    </div>
</div>

<script>
const allDepartments = <?= json_encode($departments) ?>;
const allSections = <?= json_encode($sections) ?>;

function toggleScopeFields() {
    const role = document.getElementById('new_user_role').value;
    document.getElementById('scopeFields').style.display = (role === 'admin' || role === '') ? 'none' : 'block';
    document.getElementById('sectionFieldWrap').style.display = (role === 'section_chief') ? 'block' : 'none';
    document.getElementById('chiefPermissions').style.display = (role === 'section_chief') ? 'block' : 'none';
    if (role !== 'section_chief') {
        document.querySelectorAll('#chiefPermissions input').forEach(input => input.checked = false);
    }
}

function filterDeptOptions() {
    const branchId = document.getElementById('new_user_branch').value;
    const deptSelect = document.getElementById('new_user_dept');
    deptSelect.innerHTML = '<option value="">-- Select Department --</option>';
    allDepartments.filter(d => String(d.branch_id) === String(branchId)).forEach(d => {
        deptSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
    });
    document.getElementById('new_user_section').innerHTML = '<option value="">-- Select Section --</option>';
}

function filterSectionOptions() {
    const deptId = document.getElementById('new_user_dept').value;
    const sectionSelect = document.getElementById('new_user_section');
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    allSections.filter(s => String(s.department_id) === String(deptId)).forEach(s => {
        sectionSelect.innerHTML += `<option value="${s.id}">${s.name}</option>`;
    });
}

function openResetPassword(id, name) {
    document.getElementById('reset_pw_id').value = id;
    document.getElementById('reset_pw_name').textContent = name;
    document.getElementById('resetPwModal').classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
