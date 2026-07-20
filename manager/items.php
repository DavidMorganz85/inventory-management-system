<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['dept_manager']);
$user = current_user();

$sectionFilter = $_GET['section_id'] ?? '';
$search = trim($_GET['q'] ?? '');
$stockFilter = $_GET['stock'] ?? '';
$conditionFilter = $_GET['condition'] ?? '';

$where = ['s.department_id = ?'];
$params = [$user['department_id']];
if ($sectionFilter !== '') { $where[] = 's.id = ?'; $params[] = $sectionFilter; }
if ($search !== '') { $where[] = '(i.name LIKE ? OR i.item_code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($stockFilter === 'low') { $where[] = 'i.quantity <= i.low_stock_threshold'; }
if (in_array($conditionFilter, ['damaged','missing'], true)) { $where[] = "EXISTS (SELECT 1 FROM inventory_incidents ii WHERE ii.item_id=i.id AND ii.status='open' AND ii.incident_type=?)"; $params[] = $conditionFilter; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$items = $pdo->prepare(
    "SELECT i.*, s.name AS section_name, c.name AS category_name, sup.name AS supplier_name,
      (SELECT COALESCE(SUM(ii.quantity),0) FROM inventory_incidents ii WHERE ii.item_id=i.id AND ii.status='open' AND ii.incident_type='damaged') AS damaged_qty,
      (SELECT COALESCE(SUM(ii.quantity),0) FROM inventory_incidents ii WHERE ii.item_id=i.id AND ii.status='open' AND ii.incident_type='missing') AS missing_qty,
      (SELECT ii.id FROM inventory_incidents ii WHERE ii.item_id=i.id AND ii.status='open' ORDER BY ii.id LIMIT 1) AS open_incident_id
     FROM items i
     JOIN sections s ON i.section_id = s.id
     LEFT JOIN categories c ON i.category_id = c.id
     LEFT JOIN suppliers sup ON i.supplier_id = sup.id
     $whereSql ORDER BY i.created_at DESC"
);
$items->execute($params);
$items = $items->fetchAll();

$sectionsForForm = $pdo->prepare("SELECT id, name FROM sections WHERE department_id = ? ORDER BY name");
$sectionsForForm->execute([$user['department_id']]);
$sectionsForForm = $sectionsForForm->fetchAll();

$sectionsForFilter = $sectionsForForm; // same scope
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

$modalMode = 'manager';

$pageTitle = 'Inventory Items';
$breadcrumb = 'All items across sections in your department — create, edit and manage stock (delete restricted to Administrator)';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">Department Items (<?= count($items) ?>)</h2>
        <button class="btn btn-primary" onclick="document.getElementById('createItemModal').classList.add('active')" <?= !$sectionsForForm ? 'disabled' : '' ?>>+ New Item</button>
    </div>

    <form method="GET" class="flex gap-8" style="flex-wrap:wrap; margin-bottom:16px;">
        <div class="search-input-wrap"><input type="search" name="q" placeholder="Search name or code..." value="<?= e($search) ?>"></div>
        <select name="section_id" onchange="this.form.submit()" style="max-width:200px;">
            <option value="">All Sections</option>
            <?php foreach ($sectionsForFilter as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sectionFilter==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="condition" onchange="this.form.submit()" style="max-width:180px;"><option value="">All Conditions</option><option value="damaged" <?= $conditionFilter==='damaged'?'selected':'' ?>>Damaged Items</option><option value="missing" <?= $conditionFilter==='missing'?'selected':'' ?>>Missing Items</option></select>
        <select name="stock" onchange="this.form.submit()" style="max-width:150px;">
            <option value="">All Stock Levels</option>
            <option value="low" <?= $stockFilter==='low'?'selected':'' ?>>Low Stock Only</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <a href="/uiri-ims/manager/items.php" class="btn btn-outline btn-sm">Reset</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th></th><th>Item</th><th>Section</th><th>Category</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?php if ($it['image']): ?><img class="item-thumb" src="/uiri-ims/uploads/items/<?= e($it['image']) ?>"><?php else: ?><div class="item-thumb"></div><?php endif; ?></td>
                    <td><strong><?= e($it['name']) ?></strong><br><span class="text-muted" style="font-size:11px;"><?= e($it['item_code']) ?></span></td>
                    <td><?= e($it['section_name']) ?></td>
                    <td><?= e($it['category_name'] ?? '—') ?></td>
                    <td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                    <td><?php if ((int)$it['damaged_qty']): ?><span class="badge badge-warning">Damaged: <?= (int)$it['damaged_qty'] ?></span><?php endif; ?> <?php if ((int)$it['missing_qty']): ?><span class="badge badge-danger">Missing: <?= (int)$it['missing_qty'] ?></span><?php endif; ?> <?php if (!(int)$it['damaged_qty'] && !(int)$it['missing_qty']): ?><?php if (low_stock($it)): ?><span class="badge badge-danger">Low Stock</span><?php else: ?><span class="badge badge-success">OK</span><?php endif; ?><?php endif; ?></td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick='openViewItem(<?= json_encode($it) ?>)'>View</button>
                        <button class="btn btn-outline btn-sm" onclick='openEditItem(<?= json_encode($it) ?>)'>Edit</button>
                        <button class="btn btn-sky btn-sm" onclick='openStockModal(<?= (int)$it["id"] ?>, "<?= e($it['name']) ?>")'>Stock</button>
                        <button class="btn btn-outline btn-sm" onclick='openIncidentModal(<?= (int)$it["id"] ?>, <?= json_encode($it['name']) ?>, <?= (int)$it["quantity"] ?>, <?= json_encode($it['unit']) ?>)'>Report Issue</button>
                        <?php if ($it['open_incident_id']): ?><button class="btn btn-sky btn-sm" onclick="openResolveIncident(<?= (int)$it['open_incident_id'] ?>)">Resolve Issue</button><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7" class="text-muted">No items found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="form-hint" style="margin-top:10px;">As a Department Manager you can create, view and update items in any section of your department, but deleting items is restricted to the Administrator.</p>
</div>

<?php include __DIR__ . '/../includes/item_modals.php'; ?>
<?php include __DIR__ . '/../includes/item_modals_js.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
