<script>
// allDepartments / allSections are declared by the including page (admin mode only)

function filterDeptOptions(prefix) {
    const branchId = document.getElementById(prefix + '_branch').value;
    const deptSelect = document.getElementById(prefix + '_dept');
    deptSelect.innerHTML = '<option value="">-- Select Department --</option>';
    (typeof allDepartments !== 'undefined' ? allDepartments : []).filter(d => String(d.branch_id) === String(branchId)).forEach(d => {
        deptSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
    });
    document.getElementById(prefix + '_section').innerHTML = '<option value="">-- Select Section --</option>';
}

function filterSectionOptions(prefix) {
    const deptId = document.getElementById(prefix + '_dept').value;
    const sectionSelect = document.getElementById(prefix + '_section');
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    (typeof allSections !== 'undefined' ? allSections : []).filter(s => String(s.department_id) === String(deptId)).forEach(s => {
        sectionSelect.innerHTML += `<option value="${s.id}">${s.name}</option>`;
    });
}

function openEditItem(it) {
    document.getElementById('edit_item_id').value = it.id;
    document.getElementById('edit_item_name').value = it.name;
    document.getElementById('edit_item_category').value = it.category_id || '';
    document.getElementById('edit_item_supplier').value = it.supplier_id || '';
    document.getElementById('edit_item_unit').value = it.unit;
    document.getElementById('edit_item_threshold').value = it.low_stock_threshold;
    document.getElementById('edit_item_description').value = it.description || '';

    // Admin mode: pre-select branch/dept/section cascade
    if (document.getElementById('edit_section') && document.getElementById('edit_branch')) {
        const sectionInfo = (typeof allSections !== 'undefined' ? allSections : []).find(s => String(s.id) === String(it.section_id));
        if (sectionInfo) {
            const deptInfo = (typeof allDepartments !== 'undefined' ? allDepartments : []).find(d => String(d.id) === String(sectionInfo.department_id));
            if (deptInfo) {
                document.getElementById('edit_branch').value = deptInfo.branch_id;
                filterDeptOptions('edit');
                document.getElementById('edit_dept').value = deptInfo.id;
                filterSectionOptions('edit');
                document.getElementById('edit_section').value = sectionInfo.id;
            }
        }
    } else if (document.getElementById('edit_section')) {
        document.getElementById('edit_section').value = it.section_id;
    }

    document.getElementById('editItemModal').classList.add('active');
}

function openViewItem(it) {
    document.getElementById('view_item_name').textContent = it.name;
    let img = it.image ? `<img src="/uiri-ims/uploads/items/${it.image}" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-bottom:12px;">` : '';
    document.getElementById('view_item_body').innerHTML = `
        ${img}
        <div><strong>Code:</strong> ${it.item_code}</div>
        <div><strong>Quantity:</strong> ${it.quantity} ${it.unit}</div>
        <div><strong>Low Stock Threshold:</strong> ${it.low_stock_threshold}</div>
        <div><strong>Category:</strong> ${it.category_name || '—'}</div>
        <div><strong>Supplier:</strong> ${it.supplier_name || '—'}</div>
        ${it.branch_name ? `<div><strong>Location:</strong> ${it.branch_name} / ${it.dept_name} / ${it.section_name}</div>` : (it.dept_name ? `<div><strong>Location:</strong> ${it.dept_name} / ${it.section_name}</div>` : (it.section_name ? `<div><strong>Section:</strong> ${it.section_name}</div>` : ''))}
        <div><strong>Description:</strong><br>${it.description ? it.description.replace(/</g,'&lt;') : '<span class="text-muted">No description</span>'}</div>
        <div class="text-muted" style="font-size:11.5px; margin-top:8px;">Added ${new Date(it.created_at).toLocaleDateString()} &middot; Last updated ${new Date(it.updated_at).toLocaleDateString()}</div>
    `;
    document.getElementById('viewItemModal').classList.add('active');
}

function openStockModal(id, name) {
    document.getElementById('stock_item_id').value = id;
    document.getElementById('stock_item_name').textContent = name;
    document.getElementById('stockModal').classList.add('active');
}
</script>
function openIncidentModal(id, name, quantity, unit) {
    document.getElementById('incident_item_id').value = id;
    document.getElementById('incident_item_name').value = name;
    document.getElementById('incident_quantity').max = quantity;
    document.getElementById('incident_quantity').value = '';
    document.getElementById('incident_available').textContent = quantity + ' ' + unit + ' currently available';
    document.getElementById('incidentModal').classList.add('active');
}

function openResolveIncident(id) {
    document.getElementById('resolve_incident_id').value = id;
    document.getElementById('resolve_incident_number').textContent = id;
    document.getElementById('resolveIncidentModal').classList.add('active');
}
