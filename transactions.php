<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','dept_manager','section_chief']);
$user = current_user();

$role = $user['role'];
$sectionId = $user['section_id'];
$departmentId = $user['department_id'];

$search = trim($_GET['q'] ?? '');
$sectionFilter = $_GET['section_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = [];
$params = [];

if ($role === 'admin') {
    $where[] = '1=1';
} elseif ($role === 'dept_manager') {
    $where[] = 's.department_id = ?';
    $params[] = $departmentId;
} else {
    $where[] = 'i.section_id = ?';
    $params[] = $sectionId;
}

if ($sectionFilter !== '' && $role !== 'section_chief') {
    $where[] = 'i.section_id = ?';
    $params[] = $sectionFilter;
}
if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.item_code LIKE ? OR u.full_name LIKE ? OR s.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dateFrom !== '') {
    $where[] = 'st.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'st.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sections = [];
if ($role === 'admin') {
    $sections = $pdo->query('SELECT id, name FROM sections ORDER BY name')->fetchAll();
} elseif ($role === 'dept_manager') {
    $stmt = $pdo->prepare('SELECT id, name FROM sections WHERE department_id = ? ORDER BY name');
    $stmt->execute([$departmentId]);
    $sections = $stmt->fetchAll();
}

$stmt = $pdo->prepare(
    "SELECT st.*, i.item_code, i.name AS item_name, s.name AS section_name, d.name AS dept_name, u.full_name AS performed_by_name
     FROM stock_transactions st
     JOIN items i ON st.item_id = i.id
     JOIN sections s ON i.section_id = s.id
     JOIN departments d ON s.department_id = d.id
     LEFT JOIN users u ON st.performed_by = u.id
     $whereSql
     ORDER BY st.created_at DESC"
);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$pageTitle = 'Transactions';
$breadcrumb = 'Track borrowed and moved items across sections';
include __DIR__ . '/includes/header.php';
?>

<div class="card no-print">
    <form method="GET" class="flex gap-8" style="flex-wrap:wrap; margin-bottom:16px;">
        <div class="form-group" style="flex:1; min-width:220px;">
            <label>Search</label>
            <input type="search" name="q" placeholder="Item, code, user, section..." value="<?= e($search) ?>">
        </div>
        <?php if ($role !== 'section_chief'): ?>
            <div class="form-group" style="flex:1; min-width:220px;">
                <label>Section</label>
                <select name="section_id">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= (int)$sec['id'] ?>" <?= $sectionFilter === (string)$sec['id'] ? 'selected' : '' ?>><?= e($sec['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="form-group" style="min-width:160px;"><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div class="form-group" style="min-width:160px;"><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        <div class="form-actions" style="align-items:flex-end; margin-top:22px;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            <a href="/uiri-ims/transactions.php" class="btn btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Section</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Performed By</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><?= e(date('d M Y, H:i', strtotime($tx['created_at']))) ?></td>
                    <td><?= e($tx['item_code']) ?> / <?= e($tx['item_name']) ?></td>
                    <td><?= e($tx['section_name']) ?> / <?= e($tx['dept_name']) ?></td>
                    <td><?= $tx['type'] === 'in' ? '<span class="badge badge-success">Received</span>' : '<span class="badge badge-danger">Issued</span>' ?></td>
                    <td><?= (int)$tx['quantity'] ?></td>
                    <td><?= e($tx['performed_by_name'] ?? 'System') ?></td>
                    <td><?= e($tx['note'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$transactions): ?><tr><td colspan="7" class="text-muted">No transactions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
