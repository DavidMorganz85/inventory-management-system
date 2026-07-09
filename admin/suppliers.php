<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);

$suppliers = $pdo->query(
    "SELECT s.*, (SELECT COUNT(*) FROM items i WHERE i.supplier_id = s.id) AS item_count FROM suppliers s ORDER BY s.name"
)->fetchAll();

$pageTitle = 'Suppliers';
$breadcrumb = 'Vendors supplying inventory items across UIRI';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-toolbar">
        <h2 class="mt-0 mb-0">All Suppliers (<?= count($suppliers) ?>)</h2>
        <button class="btn btn-primary" onclick="document.getElementById('createSupplierModal').classList.add('active')">+ New Supplier</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Contact Person</th><th>Phone</th><th>Email</th><th>Items Supplied</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= e($s['contact_person']) ?></td>
                    <td><?= e($s['phone']) ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td><span class="badge badge-navy"><?= (int)$s['item_count'] ?></span></td>
                    <td class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick='openEditSupplier(<?= json_encode($s) ?>)'>Edit</button>
                        <form method="POST" action="/uiri-ims/actions/supplier_actions.php" onsubmit="return confirm('Delete this supplier?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$suppliers): ?><tr><td colspan="6" class="text-muted">No suppliers added yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="createSupplierModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('createSupplierModal').classList.remove('active')">&times;</button>
        <h3>Add New Supplier</h3>
        <form method="POST" action="/uiri-ims/actions/supplier_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="create">
            <div class="form-group"><label>Supplier Name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person"></div>
            <div class="form-row">
                <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email"></div>
            </div>
            <div class="form-group"><label>Address</label><input type="text" name="address"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Add Supplier</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editSupplierModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('editSupplierModal').classList.remove('active')">&times;</button>
        <h3>Edit Supplier</h3>
        <form method="POST" action="/uiri-ims/actions/supplier_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="update">
            <input type="hidden" name="id" id="edit_sup_id">
            <div class="form-group"><label>Supplier Name</label><input type="text" name="name" id="edit_sup_name" required></div>
            <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" id="edit_sup_contact"></div>
            <div class="form-row">
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_sup_phone"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_sup_email"></div>
            </div>
            <div class="form-group"><label>Address</label><input type="text" name="address" id="edit_sup_address"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function openEditSupplier(s) {
    document.getElementById('edit_sup_id').value = s.id;
    document.getElementById('edit_sup_name').value = s.name;
    document.getElementById('edit_sup_contact').value = s.contact_person || '';
    document.getElementById('edit_sup_phone').value = s.phone || '';
    document.getElementById('edit_sup_email').value = s.email || '';
    document.getElementById('edit_sup_address').value = s.address || '';
    document.getElementById('editSupplierModal').classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
