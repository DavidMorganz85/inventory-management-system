<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['dept_manager']);
$user = current_user();

$sectionFilter = $_GET['section_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$reportType = $_GET['type'] ?? 'inventory';

$sections = $pdo->prepare("SELECT id, name FROM sections WHERE department_id = ? ORDER BY name");
$sections->execute([$user['department_id']]);
$sections = $sections->fetchAll();

$selectedSectionName = 'All Sections';
if ($sectionFilter !== '') {
    foreach ($sections as $s) {
        if ((string)$s['id'] === (string)$sectionFilter) {
            $selectedSectionName = $s['name'];
            break;
        }
    }
}

$deptStmt = $pdo->prepare("SELECT d.name, b.name AS branch_name FROM departments d JOIN branches b ON d.branch_id=b.id WHERE d.id=?");
$deptStmt->execute([$user['department_id']]);
$dept = $deptStmt->fetch();

$where = ['s.department_id = ?'];
$params = [$user['department_id']];
if ($sectionFilter !== '') { $where[] = 's.id = ?'; $params[] = $sectionFilter; }

$inventoryRows = []; $movementRows = []; $summary = [];

if ($reportType === 'inventory') {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare(
        "SELECT i.*, s.name AS section_name, c.name AS category_name
         FROM items i JOIN sections s ON i.section_id=s.id LEFT JOIN categories c ON i.category_id=c.id
         $whereSql ORDER BY s.name, i.name"
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
         LEFT JOIN categories c ON i.category_id=c.id
         $whereSql
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
    $whereSql = 'WHERE ' . implode(' AND ', $mWhere);
    $stmt = $pdo->prepare(
        "SELECT st.*, i.name AS item_name, i.item_code, s.name AS section_name, u.full_name AS performed_by_name
         FROM stock_transactions st JOIN items i ON st.item_id=i.id JOIN sections s ON i.section_id=s.id
         LEFT JOIN users u ON st.performed_by=u.id
         $whereSql ORDER BY st.created_at DESC LIMIT 500"
    );
    $stmt->execute($mParams);
    $movementRows = $stmt->fetchAll();
    $summary['total_in'] = array_sum(array_map(fn($r) => $r['type']==='in' ? $r['quantity'] : 0, $movementRows));
    $summary['total_out'] = array_sum(array_map(fn($r) => $r['type']==='out' ? $r['quantity'] : 0, $movementRows));
}

$pageTitle = 'Reports';
$breadcrumb = 'Reports for items within your department only';
include __DIR__ . '/../includes/header.php';
?>

<div class="card no-print">
    <div class="card-toolbar">
        <div class="flex gap-8">
            <a href="?type=inventory" class="btn btn-sm <?= $reportType==='inventory'?'btn-primary':'btn-outline' ?>">Inventory Summary</a>
            <a href="?type=movement" class="btn btn-sm <?= $reportType==='movement'?'btn-primary':'btn-outline' ?>">Stock Movement</a>
        </div>
        <div class="flex gap-8">
            <button type="button" id="previewToggle" class="btn btn-outline btn-sm">Preview</button>
            <button onclick="window.print()" class="btn btn-sky btn-sm">&#128424; Print / Save PDF</button>
        </div>
    </div>
    <form method="GET" class="flex gap-8" style="flex-wrap:wrap; margin-top:10px;">
        <input type="hidden" name="type" value="<?= e($reportType) ?>">
        <select name="section_id" style="max-width:200px;">
            <option value="">All Sections</option>
            <?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sectionFilter==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <?php if ($reportType === 'movement'): ?>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
            <input type="date" name="date_to" value="<?= e($dateTo) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-outline btn-sm">Apply Filters</button>
    </form>
</div>

<div class="card">
    <div class="flex items-center gap-12" style="margin-bottom:16px;">
        <img src="/uiri-ims/assets/img/uiri-logo.png" style="width:44px;">
        <div>
            <h2 class="mt-0 mb-0">Uganda Industrial Research Institute</h2>
            <div class="text-muted"><?= e($dept['branch_name']) ?> / <?= e($dept['name']) ?> — <?= $reportType === 'inventory' ? 'Inventory Summary' : 'Stock Movement' ?> Report — Generated <?= date('d M Y, H:i') ?> by <?= e($user['full_name']) ?></div>
        </div>
    </div>

    <?php if ($reportType === 'inventory'): ?>
        <div class="report-meta" style="margin-bottom:20px; display:flex; flex-wrap:wrap; gap:16px;">
            <div><strong>Report Type:</strong> Inventory Summary</div>
            <div><strong>Department:</strong> <?= e($dept['name']) ?></div>
            <div><strong>Section:</strong> <?= e($selectedSectionName) ?></div>
            <div><strong>Generated by:</strong> <?= e($user['full_name']) ?></div>
            <div><strong>Generated at:</strong> <?= date('d M Y, H:i') ?></div>
        </div>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card"><div class="stat-icon icon-navy">&#128230;</div><div><div class="stat-value"><?= (int)$summary['total_items'] ?></div><div class="stat-label">Items</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-sky">&#128200;</div><div><div class="stat-value"><?= (int)$summary['total_units'] ?></div><div class="stat-label">Total Stock Units</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-red">&#9888;</div><div><div class="stat-value"><?= (int)$summary['low_stock'] ?></div><div class="stat-label">Low Stock Items</div></div></div>
        </div>
        <?php if (!empty($categoryChartData)): ?>
            <div class="grid-2" style="margin-bottom:20px;">
                <div class="card">
                    <h3>Category Breakdown</h3>
                    <div class="chart-card">
                        <canvas class="dashboard-chart" data-chart-type="pie" data-labels='<?= json_encode(array_column($categoryChartData, 'name')) ?>' data-values='<?= json_encode(array_column($categoryChartData, 'item_count')) ?>' data-colors='["rgba(255, 99, 132, 0.65)","rgba(54, 162, 235, 0.65)","rgba(255, 206, 86, 0.65)","rgba(75, 192, 192, 0.65)","rgba(153, 102, 255, 0.65)","rgba(255, 159, 64, 0.65)"]' data-label="Category breakdown"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3>Stock Status</h3>
                    <div class="chart-card">
                        <canvas class="dashboard-chart" data-chart-type="doughnut" data-labels='["Low Stock","Healthy Stock"]' data-values='[<?= (int)$summary['low_stock'] ?>, <?= (int)$summary['total_items'] - (int)$summary['low_stock'] ?>]' data-colors='["rgba(255, 99, 132, 0.65)","rgba(54, 162, 235, 0.65)"]' data-label="Stock status"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <table>
            <thead><tr><th>Code</th><th>Item</th><th>Section</th><th>Category</th><th>Qty</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($inventoryRows as $it): ?>
                <tr>
                    <td><?= e($it['item_code']) ?></td><td><?= e($it['name']) ?></td><td><?= e($it['section_name']) ?></td>
                    <td><?= e($it['category_name'] ?? '—') ?></td><td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                    <td><?php if (low_stock($it)): ?><span class="badge badge-danger">Low Stock</span><?php else: ?><span class="badge badge-success">OK</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$inventoryRows): ?><tr><td colspan="6" class="text-muted">No items match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="report-meta" style="margin-bottom:20px; display:flex; flex-wrap:wrap; gap:16px;">
            <div><strong>Report Type:</strong> Stock Movement</div>
            <div><strong>Department:</strong> <?= e($dept['name']) ?></div>
            <div><strong>Section:</strong> <?= e($selectedSectionName) ?></div>
            <div><strong>Generated by:</strong> <?= e($user['full_name']) ?></div>
            <div><strong>Generated at:</strong> <?= date('d M Y, H:i') ?></div>
        </div>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card"><div class="stat-icon icon-green">&#8659;</div><div><div class="stat-value"><?= (int)$summary['total_in'] ?></div><div class="stat-label">Units Received</div></div></div>
            <div class="stat-card"><div class="stat-icon icon-red">&#8657;</div><div><div class="stat-value"><?= (int)$summary['total_out'] ?></div><div class="stat-label">Units Issued</div></div></div>
        </div>
        <table>
            <thead><tr><th>Date</th><th>Item</th><th>Section</th><th>Type</th><th>Qty</th><th>By</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($movementRows as $m): ?>
                <tr>
                    <td style="font-size:12px;"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
                    <td><?= e($m['item_name']) ?> <span class="text-muted">(<?= e($m['item_code']) ?>)</span></td>
                    <td><?= e($m['section_name']) ?></td>
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

<style>
    .report-preview {
        max-width: 960px;
        margin: 0 auto;
        box-shadow: 0 0 0 1px #ddd, 0 20px 40px rgba(0,0,0,0.08);
        border-radius: 0;
        padding: 26px;
        background: #fff;
    }
    .report-preview table {
        border-collapse: collapse;
    }
    .report-preview table th,
    .report-preview table td {
        border: 1px solid #d5d5d5;
        padding: 10px 12px;
    }
    .report-preview .stat-grid,
    .report-preview .chart-card,
    .report-preview .report-meta {
        page-break-inside: avoid;
    }
    .report-preview .card-toolbar { display: none; }
    @media print {
        .no-print, .sidebar, .topbar { display:none !important; }
        .content { padding:0; }
        .main { display:block; }
        body { background:#fff; }
        .grid-2, .grid-3 { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 20px !important; }
        .grid-2 > .card, .grid-3 > .card { page-break-inside: avoid !important; break-inside: avoid !important; }
        .report-preview .chart-card { page-break-inside: avoid !important; break-inside: avoid !important; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var previewButton = document.getElementById('previewToggle');
    var reportCard = document.querySelector('.card:not(.no-print)');
    if (!previewButton || !reportCard) return;

    previewButton.addEventListener('click', function () {
        reportCard.classList.toggle('report-preview');
        previewButton.textContent = reportCard.classList.contains('report-preview') ? 'Exit Preview' : 'Preview';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
