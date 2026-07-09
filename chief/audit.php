<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['section_chief']);
$user = current_user();
if (!$user['can_physical_audit']) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>403 - Access Denied</h2><p>Your account is not permitted to perform stock audits.</p><a href="/uiri-ims/chief/dashboard.php">Return to dashboard</a></div>');
}
if (!$user['section_id']) {
    $pageTitle = 'Stock Audit';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><div class="empty-icon">&#9888;</div>You have not been assigned to a section yet. Please contact the administrator.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$sectionStmt = $pdo->prepare(
    "SELECT s.name AS section_name, d.name AS dept_name, b.name AS branch_name
     FROM sections s
     JOIN departments d ON s.department_id=d.id
     JOIN branches b ON d.branch_id=b.id
     WHERE s.id=?"
);
$sectionStmt->execute([$user['section_id']]);
$section = $sectionStmt->fetch();

$itemStmt = $pdo->prepare(
    "SELECT i.*, c.name AS category_name, 
            SUM(CASE WHEN st.type='out' THEN st.quantity ELSE 0 END) AS total_issued,
            SUM(CASE WHEN st.type='in' THEN st.quantity ELSE 0 END) AS total_received
     FROM items i
     LEFT JOIN categories c ON i.category_id=c.id
     LEFT JOIN stock_transactions st ON st.item_id=i.id
     WHERE i.section_id = ?
     GROUP BY i.id
     ORDER BY i.name"
);
$itemStmt->execute([$user['section_id']]);
$items = $itemStmt->fetchAll();

$itemStats = $pdo->prepare(
    "SELECT COUNT(*) AS total_items, COALESCE(SUM(quantity),0) AS total_units,
            SUM(CASE WHEN quantity <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock
     FROM items WHERE section_id = ?"
);
$itemStats->execute([$user['section_id']]);
$itemStats = $itemStats->fetch();

$pageTitle = 'Stock Audit';
$breadcrumb = e($section['branch_name']) . ' &rsaquo; ' . e($section['dept_name']) . ' &rsaquo; ' . e($section['section_name']);
include __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon icon-navy">&#128230;</div><div><div class="stat-value"><?= (int)$itemStats['total_items'] ?></div><div class="stat-label">Items to Audit</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-sky">&#128200;</div><div><div class="stat-value"><?= (int)$itemStats['total_units'] ?></div><div class="stat-label">Stock Units</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-red">&#9888;</div><div><div class="stat-value"><?= (int)$itemStats['low_stock'] ?></div><div class="stat-label">Low Stock Items</div></div></div>
</div>

<div class="card">
    <h3>Current Section Inventory</h3>
    <table>
        <thead><tr><th>Item</th><th>Category</th><th>Qty</th><th>Received</th><th>Issued</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['name']) ?></td>
                <td><?= e($it['category_name'] ?? '—') ?></td>
                <td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                <td><?= (int)$it['total_received'] ?></td>
                <td><?= (int)$it['total_issued'] ?></td>
                <td><?php if (low_stock($it)): ?><span class="badge badge-danger">Low Stock</span><?php else: ?><span class="badge badge-success">OK</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="6" class="text-muted">No items found in your assigned section.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
