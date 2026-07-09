<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);
$user = current_user();

$branchFilter = $_GET['branch_id'] ?? '';
$deptFilter = $_GET['department_id'] ?? '';
$sectionFilter = $_GET['section_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$reportType = $_GET['type'] ?? 'inventory';

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT id, name, branch_id FROM departments ORDER BY name")->fetchAll();
$sections = $pdo->query("SELECT id, name, department_id FROM sections ORDER BY name")->fetchAll();

$where = [];
$params = [];
if ($branchFilter !== '') { $where[] = 'b.id = ?'; $params[] = $branchFilter; }
if ($deptFilter !== '') { $where[] = 'd.id = ?'; $params[] = $deptFilter; }
if ($sectionFilter !== '') { $where[] = 's.id = ?'; $params[] = $sectionFilter; }

$inventoryRows = []; $movementRows = []; $summary = [];

if ($reportType === 'inventory') {
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $stmt = $pdo->prepare(
        "SELECT i.*, s.name AS section_name, d.name AS dept_name, b.name AS branch_name, c.name AS category_name
         FROM items i JOIN sections s ON i.section_id=s.id JOIN departments d ON s.department_id=d.id JOIN branches b ON d.branch_id=b.id
         LEFT JOIN categories c ON i.category_id=c.id
         $whereSql ORDER BY b.name, d.name, s.name, i.name"
    );
    $stmt->execute($params);
    $inventoryRows = $stmt->fetchAll();

    $summary['total_items'] = count($inventoryRows);
    $summary['total_units'] = array_sum(array_column($inventoryRows, 'quantity'));
    $summary['low_stock'] = count(array_filter($inventoryRows, 'low_stock'));

    $categoryStmt = $pdo->prepare(
        "SELECT COALESCE(c.name, 'Uncategorized') AS name, COUNT(i.id) AS item_count
         FROM items i
         JOIN sections s ON i.section_id=s.id
         JOIN departments d ON s.department_id=d.id
         JOIN branches b ON d.branch_id=b.id
         LEFT JOIN categories c ON i.category_id=c.id
         " . $whereSql . "
         GROUP BY c.id, c.name
         ORDER BY item_count DESC"
    );
    $categoryStmt->execute($params);
    $categoryChartData = $categoryStmt->fetchAll();
} else {
    $mWhere = $where;
    $mParams = $params;
    if ($dateFrom !== '') { $mWhere[] = 'st.created_at >= ?'; $mParams[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo !== '') { $mWhere[] = 'st.created_at <= ?'; $mParams[] = $dateTo . ' 23:59:59'; }
    $whereSql = $mWhere ? ('WHERE ' . implode(' AND ', $mWhere)) : '';
    $stmt = $pdo->prepare(
        "SELECT st.*, i.name AS item_name, i.item_code, s.name AS section_name, d.name AS dept_name, b.name AS branch_name, u.full_name AS performed_by_name
         FROM stock_transactions st
         JOIN items i ON st.item_id = i.id
         JOIN sections s ON i.section_id=s.id JOIN departments d ON s.department_id=d.id JOIN branches b ON d.branch_id=b.id
         LEFT JOIN users u ON st.performed_by = u.id
         " . $whereSql . " ORDER BY st.created_at DESC LIMIT 500"
    );
    $stmt->execute($mParams);
    $movementRows = $stmt->fetchAll();
    $summary['total_in'] = array_sum(array_map(fn($r) => $r['type']==='in' ? $r['quantity'] : 0, $movementRows));
    $summary['total_out'] = array_sum(array_map(fn($r) => $r['type']==='out' ? $r['quantity'] : 0, $movementRows));

    $movementTypeStmt = $pdo->prepare(
        "SELECT st.type, COALESCE(SUM(st.quantity), 0) AS qty
         FROM stock_transactions st
         JOIN items i ON st.item_id = i.id
         JOIN sections s ON i.section_id=s.id
         JOIN departments d ON s.department_id=d.id
         JOIN branches b ON d.branch_id=b.id
         " . $whereSql . "
         GROUP BY st.type"
    );
    $movementTypeStmt->execute($mParams);
    $movementChartData = $movementTypeStmt->fetchAll();
}

$pageTitle = 'Reports';
$breadcrumb = 'Generate inventory & stock movement reports for any branch, department or section';
include __DIR__ . '/../includes/header.php';
?>

<div class="card no-print">
    <div class="card-toolbar">
        <div class="flex gap-8">
            <a href="?type=inventory" class="btn btn-sm <?= $reportType==='inventory'?'btn-primary':'btn-outline' ?>">Inventory Summary</a>
            <a href="?type=movement" class="btn btn-sm <?= $reportType==='movement'?'btn-primary':'btn-outline' ?>">Stock Movement</a>
        </div>
        <button onclick="window.print()" class="btn btn-sky btn-sm">&#128424; Print / Save PDF</button>
    </div>

    <form method="GET" class="flex gap-8" style="flex-wrap:wrap; margin-top:10px;">
        <input type="hidden" name="type" value="<?= e($reportType) ?>">
        <select name="branch_id" style="max-width:170px;">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $branchFilter==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="department_id" style="max-width:170px;">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $deptFilter==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="section_id" style="max-width:170px;">
            <option value="">All Sections</option>
            <?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sectionFilter==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <?php if ($reportType === 'movement'): ?>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" title="From date">
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" title="To date">
        <?php endif; ?>
        <button type="submit" class="btn btn-outline btn-sm">Apply Filters</button>
    </form>
</div>

<div class="card" id="printArea">
    <div class="flex items-center gap-12" style="margin-bottom:16px;">
        <img src="/uiri-ims/assets/img/uiri-logo.png" style="width:44px;">
        <div>
            <h2 class="mt-0 mb-0">Uganda Industrial Research Institute</h2>
            <div class="text-muted"><?= $reportType === 'inventory' ? 'Inventory Summary Report' : 'Stock Movement Report' ?> — Generated <?= date('d M Y, H:i') ?> by <?= e($user['full_name']) ?></div>
        </div>
    </div>

    <?php if ($reportType === 'inventory'): ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card"><div class="stat-icon icon-navy">&#128230;</div><div><div class="stat-value"><?= (int)$summary['total_items'] ?></div><div class="stat-label">Items in Scope</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-sky">&#128200;</div><div><div class="stat-value"><?= (int)$summary['total_units'] ?></div><div class="stat-label">Total Stock Units</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-red">&#9888;</div><div><div class="stat-value"><?= (int)$summary['low_stock'] ?></div><div class="stat-label">Low Stock Items</div></div></div>
        </div>
        <?php if (!empty($categoryChartData)): ?>
            <div class="grid-2" style="margin-bottom:20px;">
                <div class="card">
                    <h3>Category Breakdown</h3>
                    <div class="chart-card">
                        <canvas class="dashboard-chart" data-chart-type="pie" data-labels='<?= json_encode(array_column($categoryChartData, 'name')) ?>' data-values='<?= json_encode(array_column($categoryChartData, 'item_count')) ?>' data-colors='["rgba(255, 99, 132, 0.65)","rgba(54, 162, 235, 0.65)","rgba(255, 206, 86, 0.65)","rgba(75, 192, 192, 0.65)","rgba(153, 102, 255, 0.65)","rgba(255, 159, 64, 0.65)","rgba(99, 255, 132, 0.65)","rgba(255, 99, 255, 0.65)"]' data-label="Category item distribution"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3>Stock Status</h3>
                    <div class="chart-card">
                        <canvas class="dashboard-chart" data-chart-type="doughnut" data-labels='["Low Stock","Healthy Stock"]' data-values='[<?= (int)$summary['low_stock'] ?>, <?= (int)$summary['total_items'] - (int)$summary['low_stock'] ?>]' data-colors='["rgba(255, 99, 132, 0.65)","rgba(54, 162, 235, 0.65)"]' data-label="Inventory stock status"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <table>
            <thead><tr><th>Code</th><th>Item</th><th>Location</th><th>Category</th><th>Qty</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($inventoryRows as $it): ?>
                <tr>
                    <td><?= e($it['item_code']) ?></td>
                    <td><?= e($it['name']) ?></td>
                    <td style="font-size:12px;"><?= e($it['branch_name']) ?> / <?= e($it['dept_name']) ?> / <?= e($it['section_name']) ?></td>
                    <td><?= e($it['category_name'] ?? '—') ?></td>
                    <td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                    <td><?php if (low_stock($it)): ?><span class="badge badge-danger">Low Stock</span><?php else: ?><span class="badge badge-success">OK</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$inventoryRows): ?><tr><td colspan="6" class="text-muted">No items match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card"><div class="stat-icon icon-green">&#8659;</div><div><div class="stat-value"><?= (int)$summary['total_in'] ?></div><div class="stat-label">Total Units Received</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-red">&#8657;</div><div><div class="stat-value"><?= (int)$summary['total_out'] ?></div><div class="stat-label">Total Units Issued</div></div></div>
        </div>
        <?php if (!empty($movementChartData)): ?>
            <div class="grid-2" style="margin-bottom:20px;">
                <div class="card">
                    <h3>Movement by Type</h3>
                    <div class="chart-card">
                        <canvas class="dashboard-chart" data-chart-type="pie" data-labels='<?= json_encode(array_column($movementChartData, 'type')) ?>' data-values='<?= json_encode(array_column($movementChartData, 'qty')) ?>' data-colors='["rgba(54, 162, 235, 0.65)","rgba(255, 99, 132, 0.65)"]' data-label="Stock movement types"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3>Movement Volume</h3>
                    <div class="chart-card">
                        <canvas class="dashboard-chart" data-chart-type="bar" data-labels='<?= json_encode(array_column($movementChartData, 'type')) ?>' data-values='<?= json_encode(array_column($movementChartData, 'qty')) ?>' data-label="Movement volume by type"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <table>
            <thead><tr><th>Date</th><th>Item</th><th>Location</th><th>Type</th><th>Qty</th><th>By</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($movementRows as $m): ?>
                <tr>
                    <td style="font-size:12px;"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
                    <td><?= e($m['item_name']) ?> <span class="text-muted">(<?= e($m['item_code']) ?>)</span></td>
                    <td style="font-size:12px;"><?= e($m['branch_name']) ?> / <?= e($m['dept_name']) ?> / <?= e($m['section_name']) ?></td>
                    <td><?php if ($m['type']==='in'): ?><span class="badge badge-success">Stock In</span><?php else: ?><span class="badge badge-danger">Stock Out</span><?php endif; ?></td>
                    <td><?= (int)$m['quantity'] ?></td>
                    <td><?= e($m['performed_by_name'] ?? 'System') ?></td>
                    <td class="text-muted"><?= e($m['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$movementRows): ?><tr><td colspan="7" class="text-muted">No stock movements match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>@media print {
        @page { size: auto; margin: 12mm; }
        html, body { background: #fff !important; color: #000 !important; }
        .no-print, .sidebar, .topbar { display:none !important; }
        .content, .main { padding: 0 !important; margin: 0 !important; }
        .card, .chart-card, .stat-grid, table { page-break-inside: avoid; break-inside: avoid; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        table { width: 100% !important; border-collapse: collapse !important; }
        thead { display: table-header-group !important; }
        tfoot { display: table-footer-group !important; }
        tr { page-break-inside: avoid !important; break-inside: avoid-column !important; }
        td, th { border: 1px solid #ccc !important; }
        .chart-card { page-break-after: avoid; }
    }</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
