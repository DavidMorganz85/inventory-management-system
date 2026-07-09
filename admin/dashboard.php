<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);
$user = current_user();

$totalBranches = $pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
$totalDepartments = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$totalSections = $pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
$totalItems = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$totalStockUnits = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM items")->fetchColumn();
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM items WHERE quantity <= low_stock_threshold")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$selectedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$requestedCharts = $_GET['chart'] ?? [];
if (!is_array($requestedCharts)) {
    $requestedCharts = [$requestedCharts];
}
$chartOptions = [
    'branch_stock' => 'Branch stock units',
    'dept_low_stock' => 'Low stock by department',
    'section_items' => 'Section item count',
    'category_breakdown' => 'Category breakdown',
];
$selectedCharts = array_values(array_intersect(array_keys($chartOptions), $requestedCharts));
if (!$selectedCharts) {
    $selectedCharts = ['branch_stock', 'section_items'];
}

$deptFilterSql = '';
$deptParams = [];
if ($selectedDeptId > 0) {
    $deptFilterSql = 'WHERE d.id = ?';
    $deptParams[] = $selectedDeptId;
}

$branchStockData = [];
if (in_array('branch_stock', $selectedCharts, true)) {
    $stmt = $pdo->prepare(
        "SELECT b.name, COALESCE(SUM(i.quantity),0) AS stock_units
         FROM branches b
         LEFT JOIN departments d ON d.branch_id = b.id
         LEFT JOIN sections s ON s.department_id = d.id
         LEFT JOIN items i ON i.section_id = s.id
         " . ($selectedDeptId > 0 ? 'WHERE d.id = ?' : '') . "
         GROUP BY b.id, b.name"
    );
    $stmt->execute($deptParams);
    $branchStockData = $stmt->fetchAll();
}

$deptLowStockData = [];
if (in_array('dept_low_stock', $selectedCharts, true)) {
    $stmt = $pdo->prepare(
        "SELECT d.name, COUNT(i.id) AS low_stock_count
         FROM departments d
         LEFT JOIN sections s ON s.department_id = d.id
         LEFT JOIN items i ON i.section_id = s.id AND i.quantity <= i.low_stock_threshold
         " . ($selectedDeptId > 0 ? 'WHERE d.id = ?' : '') . "
         GROUP BY d.id, d.name"
    );
    $stmt->execute($deptParams);
    $deptLowStockData = $stmt->fetchAll();
}

$sectionItemData = [];
if (in_array('section_items', $selectedCharts, true)) {
    $sectionStmt = $pdo->prepare(
        "SELECT s.name, COUNT(i.id) AS item_count
         FROM sections s
         LEFT JOIN items i ON i.section_id = s.id
         LEFT JOIN departments d ON s.department_id = d.id
         " . ($selectedDeptId > 0 ? 'WHERE d.id = ?' : '') . "
         GROUP BY s.id, s.name
         ORDER BY s.name"
    );
    $sectionStmt->execute($deptParams);
    $sectionItemData = $sectionStmt->fetchAll();
}

$categoryData = [];
if (in_array('category_breakdown', $selectedCharts, true)) {
    $categoryStmt = $pdo->prepare(
        "SELECT c.name, COUNT(i.id) AS item_count
         FROM categories c
         LEFT JOIN items i ON i.category_id = c.id
         LEFT JOIN sections s ON i.section_id = s.id
         LEFT JOIN departments d ON s.department_id = d.id
         " . ($selectedDeptId > 0 ? 'WHERE d.id = ?' : '') . "
         GROUP BY c.id, c.name"
    );
    $categoryStmt->execute($deptParams);
    $categoryData = $categoryStmt->fetchAll();
}

$recentItems = $pdo->query(
    "SELECT i.*, s.name AS section_name, d.name AS dept_name, b.name AS branch_name
     FROM items i
     JOIN sections s ON i.section_id = s.id
     JOIN departments d ON s.department_id = d.id
     JOIN branches b ON d.branch_id = b.id
     ORDER BY i.created_at DESC LIMIT 6"
)->fetchAll();

$lowStockItems = $pdo->query(
    "SELECT i.*, s.name AS section_name, d.name AS dept_name
     FROM items i JOIN sections s ON i.section_id = s.id JOIN departments d ON s.department_id = d.id
     WHERE i.quantity <= i.low_stock_threshold
     ORDER BY i.quantity ASC LIMIT 6"
)->fetchAll();

$byBranch = $pdo->query(
    "SELECT b.name, COUNT(i.id) AS item_count, COALESCE(SUM(i.quantity),0) AS stock_units
     FROM branches b
     LEFT JOIN departments d ON d.branch_id = b.id
     LEFT JOIN sections s ON s.department_id = d.id
     LEFT JOIN items i ON i.section_id = s.id
     GROUP BY b.id, b.name"
)->fetchAll();

$recentActivity = $pdo->query(
    "SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Administrator Dashboard';
$breadcrumb = 'Full system visibility — all branches, departments &amp; sections';
include __DIR__ . '/../includes/header.php';
?>

<div class="card no-print" style="margin-bottom:20px;">
    <form method="GET" class="form-row" style="align-items:flex-end; gap:14px;">
        <div class="form-group" style="flex:1; min-width:240px;">
            <label>Department</label>
            <select name="department_id">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int)$dept['id'] ?>" <?= $selectedDeptId === (int)$dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:2; min-width:320px;">
            <label>Charts</label>
            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <?php foreach ($chartOptions as $key => $label): ?>
                    <label class="checkbox-inline" style="font-weight:400;">
                        <input type="checkbox" name="chart[]" value="<?= e($key) ?>" <?= in_array($key, $selectedCharts, true) ? 'checked' : '' ?>> <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-actions" style="gap:10px; margin-top:18px;">
            <button type="submit" class="btn btn-primary btn-sm">Update Charts</button>
            <a href="/uiri-ims/admin/dashboard.php" class="btn btn-outline btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon icon-navy">&#127970;</div><div><div class="stat-value"><?= (int)$totalBranches ?></div><div class="stat-label">Branches</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-sky">&#128193;</div><div><div class="stat-value"><?= (int)$totalDepartments ?></div><div class="stat-label">Departments</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-gold">&#128194;</div><div><div class="stat-value"><?= (int)$totalSections ?></div><div class="stat-label">Sections</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-navy">&#128230;</div><div><div class="stat-value"><?= (int)$totalItems ?></div><div class="stat-label">Inventory Items</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-red">&#9888;</div><div><div class="stat-value"><?= (int)$lowStockCount ?></div><div class="stat-label">Low Stock Alerts</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-green">&#128100;</div><div><div class="stat-value"><?= (int)$totalUsers ?></div><div class="stat-label">System Users</div></div></div>
</div>

<div class="grid-2">
    <?php if (in_array('branch_stock', $selectedCharts, true)): ?>
        <div class="card">
            <h3>Stock Distribution by Branch</h3>
            <div class="chart-card" style="height:320px;">
                <canvas class="dashboard-chart" data-chart-type="bar" data-labels='<?= json_encode(array_column($branchStockData, 'name')) ?>' data-values='<?= json_encode(array_column($branchStockData, 'stock_units')) ?>' data-label="Stock units by branch"></canvas>
            </div>
            <div style="margin-top:14px;"><a href="/uiri-ims/admin/branches.php" class="btn btn-outline btn-sm">Manage Branches &rarr;</a></div>
        </div>
    <?php endif; ?>

    <?php if (in_array('dept_low_stock', $selectedCharts, true)): ?>
        <div class="card">
            <h3>Department Low Stock</h3>
            <div class="chart-card" style="height:320px;">
                <canvas class="dashboard-chart" data-chart-type="bar" data-labels='<?= json_encode(array_column($deptLowStockData, 'name')) ?>' data-values='<?= json_encode(array_column($deptLowStockData, 'low_stock_count')) ?>' data-label="Low stock counts by department"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!in_array('branch_stock', $selectedCharts, true) && !in_array('dept_low_stock', $selectedCharts, true)): ?>
        <div class="card" style="flex:1 1 100%;">
            <h3>&#9888; Low Stock Items</h3>
            <?php if (!$lowStockItems): ?>
                <div class="empty-state"><div class="empty-icon">&#10004;</div>All items are sufficiently stocked.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Item</th><th>Section</th><th>Qty</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStockItems as $it): ?>
                        <tr>
                            <td><?= e($it['name']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($it['item_code']) ?></span></td>
                            <td><?= e($it['section_name']) ?></td>
                            <td><span class="badge badge-danger"><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (in_array('section_items', $selectedCharts, true) || in_array('category_breakdown', $selectedCharts, true)): ?>
    <div class="grid-2">
        <?php if (in_array('section_items', $selectedCharts, true)): ?>
            <div class="card">
                <h3>Section Item Count</h3>
                <div class="chart-card" style="height:320px;">
                    <canvas class="dashboard-chart" data-chart-type="bar" data-labels='<?= json_encode(array_column($sectionItemData, 'name')) ?>' data-values='<?= json_encode(array_column($sectionItemData, 'item_count')) ?>' data-label="Items per section"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array('category_breakdown', $selectedCharts, true)): ?>
            <?php
                $categoryColors = [
                    'rgba(255, 99, 132, 0.65)',
                    'rgba(54, 162, 235, 0.65)',
                    'rgba(255, 206, 86, 0.65)',
                    'rgba(75, 192, 192, 0.65)',
                    'rgba(153, 102, 255, 0.65)',
                    'rgba(255, 159, 64, 0.65)',
                    'rgba(199, 199, 199, 0.65)',
                    'rgba(83, 102, 255, 0.65)',
                ];
                $categorySliceColors = array_slice(array_merge($categoryColors, $categoryColors), 0, count($categoryData));
            ?>
            <div class="card">
                <h3>Category Breakdown</h3>
                <div class="chart-card" style="height:320px;">
                    <canvas class="dashboard-chart" data-chart-type="pie" data-labels='<?= json_encode(array_column($categoryData, 'name')) ?>' data-values='<?= json_encode(array_column($categoryData, 'item_count')) ?>' data-colors='<?= json_encode($categorySliceColors) ?>' data-label="Items by category"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3>&#9888; Low Stock Items</h3>
        <?php if (!$lowStockItems): ?>
            <div class="empty-state"><div class="empty-icon">&#10004;</div>All items are sufficiently stocked.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Item</th><th>Section</th><th>Qty</th></tr></thead>
                <tbody>
                <?php foreach ($lowStockItems as $it): ?>
                    <tr>
                        <td><?= e($it['name']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($it['item_code']) ?></span></td>
                        <td><?= e($it['section_name']) ?></td>
                        <td><span class="badge badge-danger"><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Recently Added Items</h3>
        <table>
            <thead><tr><th>Item</th><th>Branch / Dept / Section</th><th>Qty</th></tr></thead>
            <tbody>
            <?php foreach ($recentItems as $it): ?>
                <tr>
                    <td><?= e($it['name']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($it['item_code']) ?></span></td>
                    <td style="font-size:12px;"><?= e($it['branch_name']) ?> / <?= e($it['dept_name']) ?> / <?= e($it['section_name']) ?></td>
                    <td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentItems): ?><tr><td colspan="3" class="text-muted">No items yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <div style="margin-top:14px;"><a href="/uiri-ims/admin/items.php" class="btn btn-outline btn-sm">View All Items &rarr;</a></div>
    </div>

    <div class="card">
        <h3>Items by Stock Status</h3>
        <div class="chart-card" style="height:320px;">
            <canvas id="statusChart" data-labels='["Low Stock","Healthy Stock"]' data-values='[<?= (int)$lowStockCount ?>, <?= (int)$totalItems - (int)$lowStockCount ?>]'></canvas>
        </div>
    </div>
</div>

<div class="card">
        <h3>Recent Activity</h3>
        <table>
            <thead><tr><th>User</th><th>Action</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($recentActivity as $log): ?>
                <tr>
                    <td><?= e($log['full_name'] ?? 'System') ?></td>
                    <td style="font-size:12px;"><?= e(str_replace('_',' ', $log['action'])) ?></td>
                    <td style="font-size:12px;" class="text-muted"><?= date('d M, H:i', strtotime($log['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:14px;"><a href="/uiri-ims/admin/audit_logs.php" class="btn btn-outline btn-sm">View Full Audit Log &rarr;</a></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
