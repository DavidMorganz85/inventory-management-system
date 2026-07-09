<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'dept_manager', 'section_chief']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to_dashboard(); }
verify_csrf();

$user = current_user();
$op = $_POST['op'] ?? '';

if ($user['role'] === 'section_chief' && !empty($user['can_delegate_view']) && in_array($op, ['create', 'update', 'stock'], true)) {
    throw new Exception('Read-only delegate access is enabled for your account; item modifications are not allowed.');
}

$redirectMap = ['admin' => '/uiri-ims/admin/items.php', 'dept_manager' => '/uiri-ims/manager/items.php', 'section_chief' => '/uiri-ims/chief/items.php'];
$redirectTo = $redirectMap[$user['role']];

$availableItemColumns = [];
try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM items WHERE Field IN ('serial_number','expiry_date','purchase_date','unit_cost')");
    $availableItemColumns = $columnStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Exception $e) {
    $availableItemColumns = [];
}

function handle_item_image_upload(): ?string {
    if (empty($_FILES['image']['name']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) throw new Exception('Image upload failed.');

    $allowed = ['jpg','jpeg','png','webp','gif'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) throw new Exception('Only JPG, PNG, WEBP or GIF images are allowed.');
    if ($_FILES['image']['size'] > 4 * 1024 * 1024) throw new Exception('Image must be under 4MB.');

    $filename = 'item_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/items/' . $filename;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) throw new Exception('Could not save uploaded image.');
    return $filename;
}

try {
    if ($op === 'create') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $itemCodeInput = trim($_POST['item_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $serialNumber = trim($_POST['serial_number'] ?? '') ?: null;
        $expiryDate = trim($_POST['expiry_date'] ?? '') ?: null;
        $purchaseDate = trim($_POST['purchase_date'] ?? '') ?: null;
        $unitCost = $_POST['unit_cost'] !== '' ? (float)$_POST['unit_cost'] : null;
        $description = trim($_POST['description'] ?? '');
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $unit = trim($_POST['unit'] ?? 'pcs');
        $threshold = max(0, (int)($_POST['low_stock_threshold'] ?? 5));

        if ($name === '' || $sectionId <= 0 || $categoryId === null) throw new Exception('Item name, category and section are required.');
        if (!user_can_access_section($pdo, $user, $sectionId)) {
            throw new Exception('You do not have permission to add items to that section.');
        }

        $catRow = $pdo->prepare("SELECT name FROM categories WHERE id=?");
        $catRow->execute([$categoryId]);
        $catName = $catRow->fetchColumn() ?: 'ITM';

        $code = $itemCodeInput !== '' ? $itemCodeInput : generate_item_code($pdo, $catName);
        $image = handle_item_image_upload();

        $insertCols = ['section_id', 'category_id', 'supplier_id', 'item_code', 'name', 'description', 'quantity', 'unit', 'low_stock_threshold', 'image', 'added_by'];
        $insertValues = [$sectionId, $categoryId, $supplierId, $code, $name, $description, $quantity, $unit, $threshold, $image, $user['id']];

        if (in_array('serial_number', $availableItemColumns, true)) {
            $insertCols[] = 'serial_number';
            $insertValues[] = $serialNumber;
        }
        if (in_array('expiry_date', $availableItemColumns, true)) {
            $insertCols[] = 'expiry_date';
            $insertValues[] = $expiryDate ?: null;
        }
        if (in_array('purchase_date', $availableItemColumns, true)) {
            $insertCols[] = 'purchase_date';
            $insertValues[] = $purchaseDate ?: null;
        }
        if (in_array('unit_cost', $availableItemColumns, true)) {
            $insertCols[] = 'unit_cost';
            $insertValues[] = $unitCost;
        }

        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        $stmt = $pdo->prepare(
            "INSERT INTO items (" . implode(', ', $insertCols) . ") VALUES ($placeholders)"
        );
        $stmt->execute($insertValues);
        $itemId = (int)$pdo->lastInsertId();

        if ($quantity > 0) {
            $pdo->prepare("INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by) VALUES (?, 'in', ?, 'Initial stock on item creation', ?)")
                ->execute([$itemId, $quantity, $user['id']]);
        }

        log_audit($pdo, $user['id'], 'create_item', 'item', $itemId, "Created item '$name' ($code)");
        set_flash('success', "Item \"$name\" ($code) added successfully.");

    } elseif ($op === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $serialNumber = trim($_POST['serial_number'] ?? '') ?: null;
        $expiryDate = trim($_POST['expiry_date'] ?? '') ?: null;
        $purchaseDate = trim($_POST['purchase_date'] ?? '') ?: null;
        $unitCost = $_POST['unit_cost'] !== '' ? (float)$_POST['unit_cost'] : null;
        $description = trim($_POST['description'] ?? '');
        $unit = trim($_POST['unit'] ?? 'pcs');
        $threshold = max(0, (int)($_POST['low_stock_threshold'] ?? 5));
        $newSectionId = (int)($_POST['section_id'] ?? 0);

        if ($name === '' || $id <= 0) throw new Exception('Invalid item.');
        if (!user_can_access_item($pdo, $user, $id)) throw new Exception('You do not have permission to edit this item.');
        // If moving sections, verify permission on the destination section too
        if ($newSectionId && !user_can_access_section($pdo, $user, $newSectionId)) {
            throw new Exception('You do not have permission to move this item to that section.');
        }

        $image = handle_item_image_upload();

        $updateCols = ['name = ?', 'category_id = ?', 'supplier_id = ?', 'description = ?', 'unit = ?', 'low_stock_threshold = ?', 'section_id = ?'];
        $updateValues = [$name, $categoryId, $supplierId, $description, $unit, $threshold, $newSectionId ?: null];

        if ($image) {
            $updateCols[] = 'image = ?';
            $updateValues[] = $image;
        }
        if (in_array('serial_number', $availableItemColumns, true)) {
            $updateCols[] = 'serial_number = ?';
            $updateValues[] = $serialNumber;
        }
        if (in_array('expiry_date', $availableItemColumns, true)) {
            $updateCols[] = 'expiry_date = ?';
            $updateValues[] = $expiryDate ?: null;
        }
        if (in_array('purchase_date', $availableItemColumns, true)) {
            $updateCols[] = 'purchase_date = ?';
            $updateValues[] = $purchaseDate ?: null;
        }
        if (in_array('unit_cost', $availableItemColumns, true)) {
            $updateCols[] = 'unit_cost = ?';
            $updateValues[] = $unitCost;
        }

        $updateValues[] = $id;
        $stmt = $pdo->prepare("UPDATE items SET " . implode(', ', $updateCols) . " WHERE id=?");
        $stmt->execute($updateValues);

        log_audit($pdo, $user['id'], 'update_item', 'item', $id, "Updated item '$name'");
        set_flash('success', "Item updated successfully.");

    } elseif ($op === 'stock') {
        // Stock-in / stock-out movement (creates or issues stock)
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $qty = (int)($_POST['quantity'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if (!in_array($type, ['in','out'], true) || $qty <= 0) throw new Exception('Invalid stock movement.');
        if (!user_can_access_item($pdo, $user, $id)) throw new Exception('You do not have permission to update stock for this item.');

        $itemStmt = $pdo->prepare("SELECT quantity, name FROM items WHERE id=?");
        $itemStmt->execute([$id]);
        $item = $itemStmt->fetch();
        if (!$item) throw new Exception('Item not found.');

        if ($type === 'out' && $qty > $item['quantity']) {
            throw new Exception('Cannot issue more stock than is currently available (' . $item['quantity'] . ' in stock).');
        }

        $pdo->beginTransaction();
        $newQty = $type === 'in' ? $item['quantity'] + $qty : $item['quantity'] - $qty;
        $pdo->prepare("UPDATE items SET quantity=? WHERE id=?")->execute([$newQty, $id]);
        $pdo->prepare("INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by) VALUES (?, ?, ?, ?, ?)")
            ->execute([$id, $type, $qty, $note ?: null, $user['id']]);
        $pdo->commit();

        log_audit($pdo, $user['id'], 'stock_' . $type, 'item', $id, "{$item['name']}: {$type} {$qty}");
        set_flash('success', "Stock " . ($type === 'in' ? 'received' : 'issued') . " successfully.");

    } elseif ($op === 'delete') {
        // Absolute delete rights reserved for admin only
        if ($user['role'] !== 'admin') throw new Exception('Only administrators can delete inventory items.');
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT name FROM items WHERE id=?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]);
        log_audit($pdo, $user['id'], 'delete_item', 'item', $id, "Deleted item '$name'");
        set_flash('success', "Item deleted successfully.");
    }
} catch (Exception $e) {
    set_flash('danger', $e->getMessage());
}

header('Location: ' . $redirectTo);
exit;
