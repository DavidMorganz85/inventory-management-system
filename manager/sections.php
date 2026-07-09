<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['dept_manager']);
$user = current_user();

$sections = $pdo->prepare(
    "SELECT s.*, u.full_name AS chief_name, u.email AS chief_email,
        (SELECT COUNT(*) FROM items i WHERE i.section_id = s.id) AS item_count,
        (SELECT COALESCE(SUM(i.quantity),0) FROM items i WHERE i.section_id = s.id) AS stock_units,
        (SELECT COUNT(*) FROM items i WHERE i.section_id = s.id AND i.quantity <= i.low_stock_threshold) AS low_stock_count
     FROM sections s LEFT JOIN users u ON s.chief_id = u.id
     WHERE s.department_id = ? ORDER BY s.name"
);
$sections->execute([$user['department_id']]);
$sections = $sections->fetchAll();

$pageTitle = 'Sections';
$breadcrumb = 'All sections within your department';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2 class="mt-0">Sections in Your Department (<?= count($sections) ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Section</th><th>Section Chief</th><th>Items</th><th>Stock Units</th><th>Low Stock</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($sections as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?php if ($s['chief_name']): ?><?= e($s['chief_name']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($s['chief_email']) ?></span><?php else: ?><span class="badge badge-gray">Unassigned</span><?php endif; ?></td>
                    <td><?= (int)$s['item_count'] ?></td>
                    <td><?= (int)$s['stock_units'] ?></td>
                    <td><?php if ($s['low_stock_count'] > 0): ?><span class="badge badge-danger"><?= (int)$s['low_stock_count'] ?></span><?php else: ?><span class="badge badge-success">0</span><?php endif; ?></td>
                    <td><a href="/uiri-ims/manager/items.php?section_id=<?= (int)$s['id'] ?>" class="btn btn-outline btn-sm">View Items</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sections): ?><tr><td colspan="6" class="text-muted">No sections in your department yet. Contact the administrator to have sections created.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
   </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
