<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['section_chief']);
$user = current_user();

if (!$user['section_id']) {
    $pageTitle = 'Inventory Items';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><div class="empty-icon">&#9888;</div>You have not been assigned to a section yet. Please contact the administrator.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$search = trim($_GET['q'] ?? '');
$stockFilter = $_GET['stock'] ?? '';
$isReadOnly = !empty($user['can_delegate_view']);

$where = ['i.section_id = ?'];
$params = [$user['section_id']];
if ($search !== '') { $where[] = '(i.name LIKE ? OR i.item_code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($stockFilter === 'low') { $where[] = 'i.quantity <= i.low_stock_threshold'; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$items = $pdo->prepare(
    "SELECT i.*, c.name AS category_name, sup.name AS supplier_name
     FROM items i LEFT JOIN categories c ON i.category_id = c.id LEFT JOIN suppliers sup ON i.supplier_id = sup.id
     $whereSql ORDER BY i.created_at DESC"
);
$items->execute($params);
$items = $items->fetchAll();

$sectionStmt = $pdo->prepare("SELECT name FROM sections WHERE id=?");
$sectionStmt->execute([$user['section_id']]);
$fixedSectionName = $sectionStmt->fetchColumn();
$fixedSectionId = $user['section_id'];

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$modalMode = 'chief';

$pageTitle = 'Inventory Items';
$breadcrumb = 'Items in your section only — other sections are not visible to you';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0"><?= e($fixedSectionName) ?> — Items (<?= count($items) ?>)</h2>
        <?php if (!$isReadOnly): ?>
            <button class="btn btn-primary" onclick="document.getElementById('createItemModal').classList.add('active')">+ New Item</button>
        <?php endif; ?>
    </div>

    <?php if ($isReadOnly): ?>
        <div class="alert alert-info">Your account is configured as a read-only delegate. You can view section items but cannot create, edit, or update stock movements.</div>
    <?php endif; ?>

    <form method="GET" class="flex gap-8" style="flex-wrap:wrap; margin-bottom:16px;">
        <div class="search-input-wrap"><input type="search" name="q" placeholder="Search name or code..." value="<?= e($search) ?>"></div>
        <select name="stock" onchange="this.form.submit()" style="max-width:150px;">
            <option value="">All Stock Levels</option>
            <option value="low" <?= $stockFilter==='low'?'selected':'' ?>>Low Stock Only</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <a href="/uiri-ims/chief/items.php" class="btn btn-outline btn-sm">Reset</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th></th><th>Item</th><th>Category</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?php if ($it['image']): ?><img class="item-thumb" src="/uiri-ims/uploads/items/<?= e($it['image']) ?>"><?php else: ?><div class="item-thumb"></div><?php endif; ?></td>
                    <td><strong><?= e($it['name']) ?></strong><br><span class="text-muted" style="font-size:11px;"><?= e($it['item_code']) ?></span></td>
                    <td><?= e($it['category_name'] ?? '—') ?></td>
                    <td><?= (int)$it['quantity'] ?> <?= e($it['unit']) ?></td>
                    <td><?php if (low_stock($it)): ?><span class="badge badge-danger">Low Stock</span><?php else: ?><span class="badge badge-success">OK</span><?php endif; ?></td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick='openViewItem(<?= json_encode($it) ?>)'>View</button>
                        <?php if (!$isReadOnly): ?>
                            <button class="btn btn-outline btn-sm" onclick='openEditItem(<?= json_encode($it) ?>)'>Edit</button>
                            <button class="btn btn-sky btn-sm" onclick='openStockModal(<?= (int)$it["id"] ?>, "<?= e($it['name']) ?>")'>Stock</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="6" class="text-muted">No items found in your section yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="form-hint" style="margin-top:10px;">As a Section Chief you can create, view and update items in your section, but deleting items is restricted to the Administrator.</p>
</div>

<?php include __DIR__ . '/../includes/item_modals.php'; ?>
<?php include __DIR__ . '/../includes/item_modals_js.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
