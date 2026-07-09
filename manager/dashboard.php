<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['dept_manager']);
$user = current_user();

if (!$user['department_id']) {
    $pageTitle = 'Dashboard';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><div class="empty-icon">&#9888;</div>You have not been assigned to a department yet. Please contact the administrator.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$deptStmt = $pdo->prepare("SELECT d.*, b.name AS branch_name FROM departments d JOIN branches b ON d.branch_id=b.id WHERE d.id=?");
$deptStmt->execute([$user['department_id']]);
$dept = $deptStmt->fetch();

$sectionCount = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE department_id=?");
$sectionCount->execute([$user['department_id']]);
$sectionCount = $sectionCount->fetchColumn();

$itemStats = $pdo->prepare(
    "SELECT COUNT(*) AS total_items, COALESCE(SUM(i.quantity),0) AS total_units,
            SUM(CASE WHEN i.quantity <= i.low_stock_threshold THEN 1 ELSE 0 END) AS low_stock
     FROM items i JOIN sections s ON i.section_id=s.id WHERE s.department_id=?"
);
$itemStats->execute([$user['department_id']]);
$itemStats = $itemStats->fetch();

$bySection = $pdo->prepare(
    "SELECT s.id, s.name, u.full_name AS chief_name, COUNT(i.id) AS item_count, COALESCE(SUM(i.quantity),0) AS stock_units
     FROM sections s LEFT JOIN items i ON i.section_id=s.id LEFT JOIN users u ON s.chief_id=u.id
     WHERE s.department_id=? GROUP BY s.id, s.name, u.full_name ORDER BY s.name"
);
$bySection->execute([$user['department_id']]);
$bySection = $bySection->fetchAll();

$sectionStock = $pdo->prepare(
    "SELECT s.name, COALESCE(COUNT(i.id),0) AS item_count, COALESCE(SUM(i.quantity),0) AS stock_units
     FROM sections s LEFT JOIN items i ON i.section_id=s.id
     WHERE s.department_id=? GROUP BY s.id, s.name ORDER BY s.name"
);
$sectionStock->execute([$user['department_id']]);
$sectionStock = $sectionStock->fetchAll();

$lowStockItems = $pdo->prepare(
    "SELECT i.*, s.name AS section_name FROM items i JOIN sections s ON i.section_id=s.id
     WHERE s.department_id=? AND i.quantity <= i.low_stock_threshold ORDER BY i.quantity ASC LIMIT 8"
);
$lowStockItems->execute([$user['department_id']]);
$lowStockItems = $lowStockItems->fetchAll();

$pageTitle = 'Department Manager Dashboard';
$breadcrumb = e($dept['branch_name']) . ' &rsaquo; ' . e($dept['name']);
include __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon icon-navy">&#128194;</div><div><div class="stat-value"><?= (int)$sectionCount ?></div><div class="stat-label">Sections in Department</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-sky">&#128230;</div><div><div class="stat-value"><?= (int)$itemStats['total_items'] ?></div><div class="stat-label">Inventory Items</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-gold">&#128200;</div><div><div class="stat-value"><?= (int)$itemStats['total_units'] ?></div><div class="stat-label">Total Stock Units</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-red">&#9888;</div><div><div class="stat-value"><?= (int)$itemStats['low_stock'] ?></div><div class="stat-label">Low Stock Alerts</div></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Item Count by Section</h3>
        <div class="chart-card" style="height:320px;">
            <canvas class="dashboard-chart" data-chart-type="bar" data-labels='<?= json_encode(array_column($sectionStock, 'name')) ?>' data-values='<?= json_encode(array_column($sectionStock, 'item_count')) ?>' data-label="Items per section"></canvas>
        </div>
    </div>

    <div class="card">
        <h3>Stock Units by Section</h3>
        <div class="chart-card" style="height:320px;">
            <canvas class="dashboard-chart" data-chart-type="bar" data-labels='<?= json_encode(array_column($sectionStock, 'name')) ?>' data-values='<?= json_encode(array_column($sectionStock, 'stock_units')) ?>' data-label="Stock units per section" data-colors='["rgba(54, 162, 235, 0.65)","rgba(75, 192, 192, 0.65)","rgba(153, 102, 255, 0.65)","rgba(255, 159, 64, 0.65)"]'></canvas>
        </div>
    </div>
</div>

<div class="card">
        <h3>&#9888; Low Stock Items</h3>
        <?php if (!$lowStockItems): ?>
            <div class="empty-state"><div class="empty-icon">&#10004;</div>All items in your department are sufficiently stocked.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Item</th><th>Section</th><th>Qty</th></tr></thead>
                <tbody>
                <?php foreach ($lowStockItems as $it): ?>
                    <tr>
                        <td><?= e($it['name']) ?></td>
                        <td><?= e($it['section_name']) ?></td>
                        <td><span class="badge badge-danger"><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div style="margin-top:14px;"><a href="/uiri-ims/manager/items.php" class="btn btn-outline btn-sm">Manage Items &rarr;</a></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
