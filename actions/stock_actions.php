<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'dept_manager', 'section_chief']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to_dashboard();
}
verify_csrf();

$user = current_user();
$type = $_POST['type'] ?? '';
$itemId = (int)($_POST['item_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);
$note = trim($_POST['note'] ?? '');
$redirectTo = $type === 'out' ? '/uiri-ims/stock-out.php' : '/uiri-ims/stock-in.php';

try {
    if (!in_array($type, ['in', 'out'], true) || $itemId <= 0 || $quantity <= 0) {
        throw new Exception('Select an item and enter a quantity greater than zero.');
    }
    if ($user['role'] === 'section_chief' && !empty($user['can_delegate_view'])) {
        throw new Exception('Your account has read-only delegate access and cannot record stock movements.');
    }
    if (!user_can_access_item($pdo, $user, $itemId)) {
        throw new Exception('You do not have permission to update this item.');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT item_code, name, quantity, unit FROM items WHERE id = ? FOR UPDATE');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new Exception('The selected item was not found.');
    }
    if ($type === 'out' && $quantity > (int)$item['quantity']) {
        throw new Exception('Only ' . (int)$item['quantity'] . ' ' . $item['unit'] . ' are currently available.');
    }

    $change = $type === 'in' ? $quantity : -$quantity;
    $pdo->prepare('UPDATE items SET quantity = quantity + ? WHERE id = ?')->execute([$change, $itemId]);
    $pdo->prepare('INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$itemId, $type, $quantity, $note !== '' ? $note : null, $user['id']]);
    log_audit($pdo, $user['id'], 'stock_' . $type, 'item', $itemId,
        $item['item_code'] . ' - ' . $item['name'] . ': ' . $quantity . ' ' . $item['unit']);
    $pdo->commit();

    set_flash('success', $type === 'in' ? 'Stock received successfully.' : 'Stock issued successfully.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', $e->getMessage());
}

header('Location: ' . $redirectTo);
exit;
