<?php
/**
 * Shared modals for item Create / Edit / View / Stock movement.
 * Expects the including page to have already defined:
 *   $categories, $suppliers  (arrays)
 *   $modalMode = 'admin' | 'manager' | 'chief'
 *   For 'manager': $sectionsForForm (sections within the manager's department)
 *   For 'chief':   $fixedSectionId, $fixedSectionName
 * $modalMode defaults to 'admin' if not set (uses branches/departments/sections cascade).
 */
$modalMode = $modalMode ?? 'admin';
?>
<!-- Damaged / Missing Inventory Modal -->
<div class="modal-overlay" id="incidentModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('incidentModal').classList.remove('active')">&times;</button>
        <h3>Report Inventory Issue</h3>
        <p class="form-hint">Reporting an issue removes the affected quantity from available stock and records it in transaction history.</p>
        <form method="POST" action="/uiri-ims/actions/incident_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="report">
            <input type="hidden" name="item_id" id="incident_item_id">
            <div class="form-group"><label>Item</label><input type="text" id="incident_item_name" disabled></div>
            <div class="form-row">
                <div class="form-group"><label>Condition</label><select name="incident_type" required><option value="damaged">Damaged</option><option value="missing">Missing</option></select></div>
                <div class="form-group"><label>Quantity</label><input type="number" name="quantity" id="incident_quantity" min="1" required><div class="form-hint" id="incident_available"></div></div>
            </div>
            <div class="form-group"><label>Date Identified</label><input type="date" name="incident_date" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label>Details</label><textarea name="details" maxlength="500" rows="4" placeholder="Describe the damage or circumstances in which the item went missing" required></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Record Issue</button></div>
        </form>
    </div>
</div>

<?php if (in_array(current_user()['role'], ['admin','dept_manager'], true)): ?>
<div class="modal-overlay" id="resolveIncidentModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('resolveIncidentModal').classList.remove('active')">&times;</button>
        <h3>Resolve Inventory Issue #<span id="resolve_incident_number"></span></h3>
        <form method="POST" action="/uiri-ims/actions/incident_actions.php">
            <?= csrf_field() ?><input type="hidden" name="op" value="resolve"><input type="hidden" name="incident_id" id="resolve_incident_id">
            <div class="form-group"><label>Resolution</label><select name="resolution" required><option value="recovered">Recovered — restore quantity to stock</option><option value="written_off">Written Off — keep quantity deducted</option></select></div>
            <div class="form-group"><label>Resolution Note</label><textarea name="resolution_note" maxlength="500" rows="4" placeholder="Document the recovery or write-off decision" required></textarea></div>
            <button type="submit" class="btn btn-primary btn-block">Resolve Issue</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Create Item Modal -->
<div class="modal-overlay" id="createItemModal">
    <div class="modal-box" style="max-width:560px;">
        <button class="modal-close" onclick="document.getElementById('createItemModal').classList.remove('active')">&times;</button>
        <h3>Add New Inventory Item</h3>
        <form method="POST" action="/uiri-ims/actions/item_actions.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="create">

            <?php if ($modalMode === 'admin'): ?>
                <div class="form-row">
                    <div class="form-group"><label>Branch</label>
                        <select id="create_branch" onchange="filterDeptOptions('create')">
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Department</label>
                        <select id="create_dept" onchange="filterSectionOptions('create')">
                            <option value="">-- Select Department --</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Section</label>
                    <select name="section_id" id="create_section" required>
                        <option value="">-- Select Section --</option>
                    </select>
                </div>
            <?php elseif ($modalMode === 'manager'): ?>
                <div class="form-group"><label>Section</label>
                    <select name="section_id" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sectionsForForm as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php else: /* chief */ ?>
                <input type="hidden" name="section_id" value="<?= (int)$fixedSectionId ?>">
                <div class="form-group"><label>Section</label><input type="text" value="<?= e($fixedSectionName) ?>" disabled></div>
            <?php endif; ?>

            <div class="form-group"><label>Item Code / SKU <span class="text-muted">(optional)</span></label><input type="text" name="item_code" placeholder="Leave blank to auto-generate"></div>
            <div class="form-group"><label>Item Name</label><input type="text" name="name" required></div>
            <div class="form-row">
                <div class="form-group"><label>Category</label>
                    <select name="category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Supplier</label>
                    <select name="supplier_id">
                        <option value="">-- None --</option>
                        <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Serial / Batch No.</label><input type="text" name="serial_number" placeholder="Optional serial or batch identifier"></div>
                <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date"></div>
                <div class="form-group"><label>Unit Cost</label><input type="number" step="0.01" min="0" name="unit_cost" placeholder="Optional">
                    <div class="form-hint">Used for valuation if provided.</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Initial Quantity</label><input type="number" name="quantity" min="0" value="0" required></div>
                <div class="form-group"><label>Unit</label><input type="text" name="unit" value="pcs" required></div>
            </div>
            <div class="form-group"><label>Low Stock Threshold</label><input type="number" name="low_stock_threshold" min="0" value="5" required>
                <div class="form-hint">Item will show a "Low Stock" alert at or below this quantity.</div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="2"></textarea></div>
            <div class="form-group"><label>Item Image (optional)</label><input type="file" name="image" accept="image/*"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Add Item</button></div>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal-overlay" id="editItemModal">
    <div class="modal-box" style="max-width:560px;">
        <button class="modal-close" onclick="document.getElementById('editItemModal').classList.remove('active')">&times;</button>
        <h3>Edit Item</h3>
        <form method="POST" action="/uiri-ims/actions/item_actions.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="update">
            <input type="hidden" name="id" id="edit_item_id">

            <?php if ($modalMode === 'admin'): ?>
                <div class="form-row">
                    <div class="form-group"><label>Branch</label>
                        <select id="edit_branch" onchange="filterDeptOptions('edit')">
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Department</label>
                        <select id="edit_dept" onchange="filterSectionOptions('edit')">
                            <option value="">-- Select Department --</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Section</label>
                    <select name="section_id" id="edit_section" required>
                        <option value="">-- Select Section --</option>
                    </select>
                </div>
            <?php elseif ($modalMode === 'manager'): ?>
                <div class="form-group"><label>Section</label>
                    <select name="section_id" id="edit_section" required>
                        <?php foreach ($sectionsForForm as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="section_id" value="<?= (int)$fixedSectionId ?>">
            <?php endif; ?>

            <div class="form-group"><label>Item Name</label><input type="text" name="name" id="edit_item_name" required></div>
            <div class="form-row">
                <div class="form-group"><label>Category</label>
                    <select name="category_id" id="edit_item_category">
                        <option value="">-- None --</option>
                        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Supplier</label>
                    <select name="supplier_id" id="edit_item_supplier">
                        <option value="">-- None --</option>
                        <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Unit</label><input type="text" name="unit" id="edit_item_unit" required></div>
                <div class="form-group"><label>Low Stock Threshold</label><input type="number" name="low_stock_threshold" id="edit_item_threshold" min="0" required></div>
            </div>
            <div class="form-hint" style="margin:-8px 0 14px;">To change quantity, use the "Stock" (stock-in / stock-out) action instead — this keeps a movement history.</div>
            <div class="form-group"><label>Description</label><textarea name="description" id="edit_item_description" rows="2"></textarea></div>
            <div class="form-group"><label>Replace Image (optional)</label><input type="file" name="image" accept="image/*"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Save Changes</button></div>
        </form>
    </div>
</div>

<!-- View Item Modal -->
<div class="modal-overlay" id="viewItemModal">
    <div class="modal-box" style="max-width:480px;">
        <button class="modal-close" onclick="document.getElementById('viewItemModal').classList.remove('active')">&times;</button>
        <h3 id="view_item_name"></h3>
        <div id="view_item_body" style="font-size:13.5px; line-height:1.9;"></div>
    </div>
</div>

<!-- Stock Movement Modal -->
<div class="modal-overlay" id="stockModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('stockModal').classList.remove('active')">&times;</button>
        <h3>Stock Movement — <span id="stock_item_name"></span></h3>
        <form method="POST" action="/uiri-ims/actions/item_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="stock">
            <input type="hidden" name="id" id="stock_item_id">
            <div class="form-group"><label>Movement Type</label>
                <select name="type" required>
                    <option value="in">Stock In (Received)</option>
                    <option value="out">Stock Out (Issued)</option>
                </select>
            </div>
            <div class="form-group"><label>Quantity</label><input type="number" name="quantity" min="1" required></div>
            <div class="form-group"><label>Note (optional)</label><input type="text" name="note" placeholder="e.g. Delivery from supplier, issued to project X"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Record Movement</button></div>
        </form>
    </div>
</div>
