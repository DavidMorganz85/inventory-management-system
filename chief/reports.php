<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['section_chief']);
$user = current_user();
if (!$user['can_view_reports_own_section']) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>403 - Access Denied</h2><p>Your account is not permitted to view section reports.</p><a href="/uiri-ims/chief/dashboard.php">Return to dashboard</a></div>');
}
if (!$user['section_id']) {
    $pageTitle = 'Section Reports';
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

$reportType = $_GET['type'] ?? 'inventory';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$summary = [];
$inventoryRows = [];
$movementRows = [];

if ($reportType === 'movement') {
    $where = ['i.section_id = ?'];
    $params = [$user['section_id']];
    if ($dateFrom !== '') { $where[] = 'st.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo !== '') { $where[] = 'st.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT st.*, i.name AS item_name, i.item_code, u.full_name AS performed_by_name
         FROM stock_transactions st
         JOIN items i ON st.item_id=i.id
         LEFT JOIN users u ON st.performed_by=u.id
         $whereSql
         ORDER BY st.created_at DESC LIMIT 500"
    );

    $stmt->execute($params);
    $movementRows = $stmt->fetchAll();
    $summary['total_in'] = array_sum(array_map(fn($r) => $r['type'] === 'in' ? $r['quantity'] : 0, $movementRows));
    $summary['total_out'] = array_sum(array_map(fn($r) => $r['type'] === 'out' ? $r['quantity'] : 0, $movementRows));
} else {
    $stmt = $pdo->prepare(
        "SELECT i.*, c.name AS category_name
         FROM items i
         LEFT JOIN categories c ON i.category_id=c.id
         WHERE i.section_id = ?
         ORDER BY i.name"
    );
    $stmt->execute([$user['section_id']]);
    $inventoryRows = $stmt->fetchAll();

    $summary['total_items'] = count($inventoryRows);
    $summary['total_units'] = array_sum(array_column($inventoryRows, 'quantity'));
    $summary['low_stock'] = count(array_filter($inventoryRows, 'low_stock'));
}

$pageTitle = 'Section Reports';
$breadcrumb = e($section['branch_name']) . ' &rsaquo; ' . e($section['dept_name']) . ' &rsaquo; ' . e($section['section_name']);
include __DIR__ . '/../includes/header.php';
?>

<div class="card no-print">
    <div class="card-toolbar">
        <div class="flex gap-8">
            <a href="?type=inventory" class="btn btn-sm <?= $reportType === 'inventory' ? 'btn-primary' : 'btn-outline' ?>">Inventory Summary</a>
            <a href="?type=movement" class="btn btn-sm <?= $reportType === 'movement' ? 'btn-primary' : 'btn-outline' ?>">Stock Movement</a>
        </div>
        <button onclick="window.print()" class="btn btn-sky btn-sm">&#128424; Print / Save PDF</button>
    </div>
    <form method="GET" class="flex gap-8" style="flex-wrap:wrap; margin-top:10px;">
        <input type="hidden" name="type" value="<?= e($reportType) ?>">
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
        <input type="date" name="date_to" value="<?= e($dateTo) ?>">
        <button type="submit" class="btn btn-outline btn-sm">Apply Filters</button>
    </form>
</div>

<div class="card">
    <?php if ($reportType === 'inventory'): ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card"><div class="stat-icon icon-navy">&#128230;</div><div><div class="stat-value"><?= (int)$summary['total_items'] ?></div><div class="stat-label">Items</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-sky">&#128200;</div><div><div class="stat-value"><?= (int)$summary['total_units'] ?></div><div class="stat-label">Total Units</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-red">&#9888;</div><div><div class="stat-value"><?= (int)$summary['low_stock'] ?></div><div class="stat-label">Low Stock</div></div></div>
        </div>
        <table>
            <thead><tr><th>Code</th><th>Item</th><th>Category</th><th>Qty</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($inventoryRows as $it): ?>
                <tr>
                    <td><?= e($it['item_code']) ?></td>
                    <td><?= e($it['name']) ?></td>
                    <td><?= e($it['category_name'] ?? '—') ?></td>
                    <td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                    <td><?php if (low_stock($it)): ?><span class="badge badge-danger">Low Stock</span><?php else: ?><span class="badge badge-success">OK</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$inventoryRows): ?><tr><td colspan="5" class="text-muted">No inventory records found for your section.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card"><div class="stat-icon icon-green">&#8659;</div><div><div class="stat-value"><?= (int)$summary['total_in'] ?></div><div class="stat-label">Units Received</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-red">&#8657;</div><div><div class="stat-value"><?= (int)$summary['total_out'] ?></div><div class="stat-label">Units Issued</div></div></div>
        </div>
        <table>
            <thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Qty</th><th>By</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($movementRows as $m): ?>
                <tr>
                    <td style="font-size:12px;"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
                    <td><?= e($m['item_name']) ?> <span class="text-muted">(<?= e($m['item_code']) ?>)</span></td>
                    <td><?php if ($m['type'] === 'in'): ?><span class="badge badge-success">Stock In</span><?php else: ?><span class="badge badge-danger">Stock Out</span><?php endif; ?></td>
                    <td><?= (int)$m['quantity'] ?></td>
                    <td><?= e($m['performed_by_name'] ?? 'System') ?></td>
                    <td class="text-muted"><?= e($m['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$movementRows): ?><tr><td colspan="6" class="text-muted">No stock movements match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
