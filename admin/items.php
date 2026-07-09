<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);
$user = current_user();

// ---- Filters ----
$branchFilter = $_GET['branch_id'] ?? '';
$deptFilter = $_GET['department_id'] ?? '';
$sectionFilter = $_GET['section_id'] ?? '';
$search = trim($_GET['q'] ?? '');
$stockFilter = $_GET['stock'] ?? '';

$where = [];
$params = [];
if ($branchFilter !== '') { $where[] = 'b.id = ?'; $params[] = $branchFilter; }
if ($deptFilter !== '') { $where[] = 'd.id = ?'; $params[] = $deptFilter; }
if ($sectionFilter !== '') { $where[] = 's.id = ?'; $params[] = $sectionFilter; }
if ($search !== '') { $where[] = '(i.name LIKE ? OR i.item_code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($stockFilter === 'low') { $where[] = 'i.quantity <= i.low_stock_threshold'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$items = $pdo->prepare(
    "SELECT i.*, s.name AS section_name, d.name AS dept_name, b.name AS branch_name, c.name AS category_name, sup.name AS supplier_name
     FROM items i
     JOIN sections s ON i.section_id = s.id
     JOIN departments d ON s.department_id = d.id
     JOIN branches b ON d.branch_id = b.id
     LEFT JOIN categories c ON i.category_id = c.id
     LEFT JOIN suppliers sup ON i.supplier_id = sup.id
     $whereSql
     ORDER BY i.created_at DESC"
);
$items->execute($params);
$items = $items->fetchAll();

// Reference data for filters & create form
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT id, name, branch_id FROM departments ORDER BY name")->fetchAll();
$sections = $pdo->query("SELECT id, name, department_id FROM sections ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

$pageTitle = 'Inventory Items';
$breadcrumb = 'Full visibility across all branches, departments & sections';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">All Items (<?= count($items) ?>)</h2>
        <button class="btn btn-primary" onclick="document.getElementById('createItemModal').classList.add('active')" <?= !$sections ? 'disabled title="Create a section first"' : '' ?>>+ New Item</button>
    </div>

    <form method="GET" class="flex gap-8" style="flex-wrap:wrap; margin-bottom:16px;">
        <div class="search-input-wrap"><input type="search" name="q" placeholder="Search name or code..." value="<?= e($search) ?>"></div>
        <select name="branch_id" onchange="this.form.submit()" style="max-width:170px;">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $branchFilter==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="department_id" onchange="this.form.submit()" style="max-width:170px;">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $deptFilter==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="section_id" onchange="this.form.submit()" style="max-width:170px;">
            <option value="">All Sections</option>
            <?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sectionFilter==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="stock" onchange="this.form.submit()" style="max-width:150px;">
            <option value="">All Stock Levels</option>
            <option value="low" <?= $stockFilter==='low'?'selected':'' ?>>Low Stock Only</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <a href="/uiri-ims/admin/items.php" class="btn btn-outline btn-sm">Reset</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th></th><th>Item</th><th>Location</th><th>Category</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?php if ($it['image']): ?><img class="item-thumb" src="/uiri-ims/uploads/items/<?= e($it['image']) ?>"><?php else: ?><div class="item-thumb"></div><?php endif; ?></td>
                    <td><strong><?= e($it['name']) ?></strong><br><span class="text-muted" style="font-size:11px;"><?= e($it['item_code']) ?></span></td>
                    <td style="font-size:12px;"><?= e($it['branch_name']) ?> / <?= e($it['dept_name']) ?> / <?= e($it['section_name']) ?></td>
                    <td><?= e($it['category_name'] ?? '—') ?></td>
                    <td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                    <td><?php if (low_stock($it)): ?><span class="badge badge-danger">Low Stock</span><?php else: ?><span class="badge badge-success">OK</span><?php endif; ?></td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick='openViewItem(<?= json_encode($it) ?>)'>View</button>
                        <button class="btn btn-outline btn-sm" onclick='openEditItem(<?= json_encode($it) ?>)'>Edit</button>
                        <button class="btn btn-sky btn-sm" onclick='openStockModal(<?= (int)$it["id"] ?>, "<?= e($it['name']) ?>")'>Stock</button>
                        <form method="POST" action="/uiri-ims/actions/item_actions.php" onsubmit="return confirm('Permanently delete this item?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7" class="text-muted">No items match your filters.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/item_modals.php'; ?>

<script>
const allDepartments = <?= json_encode($departments) ?>;
const allSections = <?= json_encode($sections) ?>;
</script>
<?php include __DIR__ . '/../includes/item_modals_js.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
