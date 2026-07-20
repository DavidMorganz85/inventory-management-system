<?php
// Expects $stockType to be either "in" or "out".
if (!in_array($stockType ?? '', ['in', 'out'], true)) {
    throw new RuntimeException('Invalid stock module type.');
}

$isStockIn = $stockType === 'in';
$user = current_user();
$isReadOnly = $user['role'] === 'section_chief' && !empty($user['can_delegate_view']);
$search = trim($_GET['q'] ?? '');

$scope = ['1=1'];
$params = [];
if ($user['role'] === 'dept_manager') {
    $scope[] = 'd.id = ?';
    $params[] = $user['department_id'];
} elseif ($user['role'] === 'section_chief') {
    $scope[] = 's.id = ?';
    $params[] = $user['section_id'];
}
if ($search !== '') {
    $scope[] = '(i.name LIKE ? OR i.item_code LIKE ? OR s.name LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$whereSql = 'WHERE ' . implode(' AND ', $scope);

$itemStmt = $pdo->prepare(
    "SELECT i.id, i.item_code, i.name, i.quantity, i.unit, s.name AS section_name, d.name AS dept_name
     FROM items i
     JOIN sections s ON i.section_id = s.id
     JOIN departments d ON s.department_id = d.id
     $whereSql ORDER BY i.name"
);
$itemStmt->execute($params);
$items = $itemStmt->fetchAll();

$historyParams = [$stockType];
$historyScope = ['st.type = ?'];
if ($user['role'] === 'dept_manager') {
    $historyScope[] = 'd.id = ?';
    $historyParams[] = $user['department_id'];
} elseif ($user['role'] === 'section_chief') {
    $historyScope[] = 's.id = ?';
    $historyParams[] = $user['section_id'];
}
$historyStmt = $pdo->prepare(
    'SELECT st.*, i.item_code, i.name AS item_name, i.unit, s.name AS section_name, u.full_name AS user_name
     FROM stock_transactions st
     JOIN items i ON st.item_id = i.id
     JOIN sections s ON i.section_id = s.id
     JOIN departments d ON s.department_id = d.id
     LEFT JOIN users u ON st.performed_by = u.id
     WHERE ' . implode(' AND ', $historyScope) . '
     ORDER BY st.created_at DESC'
);
$historyStmt->execute($historyParams);
$history = $historyStmt->fetchAll();

$pageTitle = $isStockIn ? 'Stock In' : 'Stock Out';
$breadcrumb = $isStockIn ? 'Receive inventory and update available quantities' : 'Issue inventory while preventing negative stock';
include __DIR__ . '/header.php';
?>

<div class="grid-2">
    <div class="card no-print">
        <h2 class="mt-0"><?= $isStockIn ? 'Receive Stock' : 'Issue Stock' ?></h2>
        <?php if ($isReadOnly): ?>
            <div class="alert alert-info">Your account is configured for read-only access. You can view movement history but cannot record stock.</div>
        <?php elseif (!$items): ?>
            <div class="empty-state">No inventory items are available in your assigned scope.</div>
        <?php else: ?>
            <form method="POST" action="/uiri-ims/actions/stock_actions.php">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="<?= e($stockType) ?>">
                <div class="form-group">
                    <label>Inventory Item</label>
                    <select name="item_id" id="stock_item" required>
                        <option value="">-- Select item --</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= (int)$item['id'] ?>" data-quantity="<?= (int)$item['quantity'] ?>" data-unit="<?= e($item['unit']) ?>">
                                <?= e($item['item_code'] . ' - ' . $item['name'] . ' (' . $item['quantity'] . ' ' . $item['unit'] . ' available)') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint" id="stock_available">Choose an item to see its current balance.</div>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="stock_quantity" min="1" step="1" required>
                </div>
                <div class="form-group">
                    <label><?= $isStockIn ? 'Receiving Note / Reference' : 'Issue Note / Recipient' ?></label>
                    <textarea name="note" rows="3" maxlength="255" placeholder="<?= $isStockIn ? 'e.g. Delivery note number, supplier or purchase order' : 'e.g. Recipient, work order or reason for issue' ?>"></textarea>
                </div>
                <button type="submit" class="btn <?= $isStockIn ? 'btn-primary' : 'btn-sky' ?> btn-block">
                    <?= $isStockIn ? 'Receive Stock' : 'Issue Stock' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card no-print">
        <h3 class="mt-0">Find Inventory</h3>
        <form method="GET" class="flex gap-8">
            <div class="search-input-wrap" style="flex:1"><input type="search" name="q" placeholder="Item, code or section..." value="<?= e($search) ?>"></div>
            <button class="btn btn-outline btn-sm" type="submit">Search</button>
        </form>
        <p class="form-hint"><?= count($items) ?> item(s) available in your permitted inventory scope.</p>
        <a class="btn btn-outline btn-sm" href="/uiri-ims/transactions.php">View All Transactions &rarr;</a>
    </div>
</div>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">Recent <?= $isStockIn ? 'Receipts' : 'Issues' ?></h2>
        <button class="btn btn-outline btn-sm no-print" onclick="window.print()">Print</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Item</th><th>Section</th><th>Quantity</th><th>Recorded By</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($history as $tx): ?>
                <tr>
                    <td><?= e(date('d M Y, H:i', strtotime($tx['created_at']))) ?></td>
                    <td><strong><?= e($tx['item_name']) ?></strong><br><span class="text-muted"><?= e($tx['item_code']) ?></span></td>
                    <td><?= e($tx['section_name']) ?></td>
                    <td><span class="badge <?= $isStockIn ? 'badge-success' : 'badge-danger' ?>"><?= (int)$tx['quantity'] ?> <?= e($tx['unit']) ?></span></td>
                    <td><?= e($tx['user_name'] ?? 'System') ?></td>
                    <td><?= e($tx['note'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="6" class="text-muted">No <?= $isStockIn ? 'stock receipts' : 'stock issues' ?> recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const stockItem = document.getElementById('stock_item');
if (stockItem) {
    stockItem.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const quantity = option.dataset.quantity;
        const unit = option.dataset.unit || '';
        document.getElementById('stock_available').textContent = quantity === undefined
            ? 'Choose an item to see its current balance.'
            : `Current balance: ${quantity} ${unit}`;
        <?php if (!$isStockIn): ?>
        document.getElementById('stock_quantity').max = quantity || '';
        <?php endif; ?>
    });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
