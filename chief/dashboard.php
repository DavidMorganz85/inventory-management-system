<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['section_chief']);
$user = current_user();

if (!$user['section_id']) {
    $pageTitle = 'Dashboard';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><div class="empty-icon">&#9888;</div>You have not been assigned to a section yet. Please contact the administrator.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$sectionStmt = $pdo->prepare(
    "SELECT s.*, d.name AS dept_name, b.name AS branch_name FROM sections s
     JOIN departments d ON s.department_id=d.id JOIN branches b ON d.branch_id=b.id WHERE s.id=?"
);
$sectionStmt->execute([$user['section_id']]);
$section = $sectionStmt->fetch();

$itemStats = $pdo->prepare(
    "SELECT COUNT(*) AS total_items, COALESCE(SUM(quantity),0) AS total_units,
            SUM(CASE WHEN quantity <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock
     FROM items WHERE section_id=?"
);
$itemStats->execute([$user['section_id']]);
$itemStats = $itemStats->fetch();

$recentItems = $pdo->prepare("SELECT * FROM items WHERE section_id=? ORDER BY created_at DESC LIMIT 6");
$recentItems->execute([$user['section_id']]);
$recentItems = $recentItems->fetchAll();

$lowStockItems = $pdo->prepare("SELECT * FROM items WHERE section_id=? AND quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 8");
$lowStockItems->execute([$user['section_id']]);
$lowStockItems = $lowStockItems->fetchAll();

$recentMovements = $pdo->prepare(
    "SELECT st.*, i.name AS item_name FROM stock_transactions st JOIN items i ON st.item_id=i.id
     WHERE i.section_id=? ORDER BY st.created_at DESC LIMIT 6"
);
$recentMovements->execute([$user['section_id']]);
$recentMovements = $recentMovements->fetchAll();

$pageTitle = 'Section Chief Dashboard';
$breadcrumb = e($section['branch_name']) . ' &rsaquo; ' . e($section['dept_name']) . ' &rsaquo; ' . e($section['name']);
include __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon icon-navy">&#128230;</div><div><div class="stat-value"><?= (int)$itemStats['total_items'] ?></div><div class="stat-label">Items in Your Section</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-sky">&#128200;</div><div><div class="stat-value"><?= (int)$itemStats['total_units'] ?></div><div class="stat-label">Total Stock Units</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-red">&#9888;</div><div><div class="stat-value"><?= (int)$itemStats['low_stock'] ?></div><div class="stat-label">Low Stock Alerts</div></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>&#9888; Low Stock Items</h3>
        <?php if (!$lowStockItems): ?>
            <div class="empty-state"><div class="empty-icon">&#10004;</div>All items in your section are sufficiently stocked.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Item</th><th>Qty</th></tr></thead>
                <tbody>
                <?php foreach ($lowStockItems as $it): ?>
                    <tr><td><?= e($it['name']) ?></td><td><span class="badge badge-danger"><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div style="margin-top:14px;"><a href="/uiri-ims/chief/items.php" class="btn btn-outline btn-sm">Manage Items &rarr;</a></div>
    </div>

    <div class="card">
        <h3>Recent Stock Movements</h3>
        <table>
            <thead><tr><th>Item</th><th>Type</th><th>Qty</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($recentMovements as $m): ?>
                <tr>
                    <td><?= e($m['item_name']) ?></td>
                    <td><?php if ($m['type']==='in'): ?><span class="badge badge-success">In</span><?php else: ?><span class="badge badge-danger">Out</span><?php endif; ?></td>
                    <td><?= (int)$m['quantity'] ?></td>
                    <td class="text-muted" style="font-size:12px;"><?= date('d M, H:i', strtotime($m['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentMovements): ?><tr><td colspan="4" class="text-muted">No stock movements yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
