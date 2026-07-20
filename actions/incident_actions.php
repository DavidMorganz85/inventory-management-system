<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'dept_manager', 'section_chief']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /uiri-ims/incidents.php'); exit; }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';
$itemsPath = match ($user['role']) {
    'admin' => '/uiri-ims/admin/items.php',
    'dept_manager' => '/uiri-ims/manager/items.php',
    default => '/uiri-ims/chief/items.php',
};
$redirectTo = $itemsPath;

try {
    if ($user['role'] === 'section_chief' && !empty($user['can_delegate_view'])) {
        throw new Exception('Your read-only account cannot change inventory incidents.');
    }

    if ($op === 'report') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $type = $_POST['incident_type'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $details = trim($_POST['details'] ?? '');
        $incidentDate = $_POST['incident_date'] ?? '';
        if (!in_array($type, ['damaged', 'missing'], true) || $itemId <= 0 || $quantity <= 0 || $details === '') {
            throw new Exception('Item, incident type, quantity and details are required.');
        }
        $date = DateTime::createFromFormat('Y-m-d', $incidentDate);
        if (!$date || $date->format('Y-m-d') !== $incidentDate || $incidentDate > date('Y-m-d')) {
            throw new Exception('Enter a valid incident date that is not in the future.');
        }
        if (!user_can_access_item($pdo, $user, $itemId)) throw new Exception('You cannot report an incident for this item.');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT item_code, name, quantity, unit FROM items WHERE id=? FOR UPDATE');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) throw new Exception('Item not found.');
        if ($quantity > (int)$item['quantity']) throw new Exception('Only ' . (int)$item['quantity'] . ' ' . $item['unit'] . ' are available.');

        $pdo->prepare('INSERT INTO inventory_incidents (item_id,incident_type,quantity,details,incident_date,reported_by) VALUES (?,?,?,?,?,?)')
            ->execute([$itemId, $type, $quantity, $details, $incidentDate, $user['id']]);
        $incidentId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE items SET quantity=quantity-? WHERE id=?')->execute([$quantity, $itemId]);
        $note = ucfirst($type) . ' inventory reported - Incident #' . $incidentId;
        $pdo->prepare("INSERT INTO stock_transactions (item_id,type,quantity,note,performed_by) VALUES (?,'out',?,?,?)")
            ->execute([$itemId, $quantity, $note, $user['id']]);
        log_audit($pdo, $user['id'], 'report_' . $type . '_inventory', 'inventory_incident', $incidentId, $item['item_code'] . ': ' . $quantity . ' ' . $item['unit']);
        $pdo->commit();
        set_flash('success', 'Inventory incident #' . $incidentId . ' reported successfully.');
        $redirectTo = $itemsPath . '?condition=' . rawurlencode($type);

    } elseif ($op === 'resolve') {
        if (!in_array($user['role'], ['admin', 'dept_manager'], true)) throw new Exception('Only administrators and department managers can resolve incidents.');
        $incidentId = (int)($_POST['incident_id'] ?? 0);
        $resolution = $_POST['resolution'] ?? '';
        $note = trim($_POST['resolution_note'] ?? '');
        if (!in_array($resolution, ['recovered', 'written_off'], true) || $incidentId <= 0 || $note === '') {
            throw new Exception('Select a resolution and enter a resolution note.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT ii.*,i.item_code,i.name,i.unit FROM inventory_incidents ii JOIN items i ON ii.item_id=i.id WHERE ii.id=? FOR UPDATE');
        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch();
        if (!$incident) throw new Exception('Incident not found.');
        if ($incident['status'] !== 'open') throw new Exception('This incident has already been resolved.');
        if (!user_can_access_item($pdo, $user, (int)$incident['item_id'])) throw new Exception('You cannot resolve this incident.');

        $pdo->prepare('UPDATE inventory_incidents SET status=?,resolved_by=?,resolution_note=?,resolved_at=NOW() WHERE id=?')
            ->execute([$resolution, $user['id'], $note, $incidentId]);
        if ($resolution === 'recovered') {
            $pdo->prepare('UPDATE items SET quantity=quantity+? WHERE id=?')->execute([$incident['quantity'], $incident['item_id']]);
            $txNote = 'Inventory recovered - Incident #' . $incidentId;
            $pdo->prepare("INSERT INTO stock_transactions (item_id,type,quantity,note,performed_by) VALUES (?,'in',?,?,?)")
                ->execute([$incident['item_id'], $incident['quantity'], $txNote, $user['id']]);
        }
        log_audit($pdo, $user['id'], 'resolve_inventory_incident', 'inventory_incident', $incidentId, $resolution . ': ' . $incident['item_code']);
        $pdo->commit();
        set_flash('success', 'Incident #' . $incidentId . ' marked as ' . str_replace('_', ' ', $resolution) . '.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('danger', $e->getMessage());
}

header('Location: ' . $redirectTo);
exit;
