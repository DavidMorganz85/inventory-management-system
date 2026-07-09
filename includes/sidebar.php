<?php
$u = current_user();
$role = $u['role'];
$current = basename($_SERVER['SCRIPT_NAME']);

// [role => base path] for building links
$base = match ($role) {
    'admin' => '/uiri-ims/admin',
    'dept_manager' => '/uiri-ims/manager',
    'section_chief' => '/uiri-ims/chief',
    default => '/uiri-ims',
};

function nav_active($file, $current) { return $file === $current ? 'active' : ''; }

$initials = '';
foreach (explode(' ', $u['full_name']) as $part) { $initials .= strtoupper(substr($part, 0, 1)); }
$initials = substr($initials, 0, 2);
?>
<aside class="sidebar">
    <div class="brand">
        <img src="/uiri-ims/assets/img/uiri-logo.png" alt="UIRI">
        <div class="brand-text">
            <strong>UIRI IMS</strong>
            <span>Inventory Management</span>
        </div>
    </div>

    <nav>
        <?php if ($role === 'admin'): ?>
            <div class="nav-section-label">Overview</div>
            <a class="nav-link <?= nav_active('dashboard.php', $current) ?>" href="<?= $base ?>/dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>

            <div class="nav-section-label">Structure</div>
            <a class="nav-link <?= nav_active('branches.php', $current) ?>" href="<?= $base ?>/branches.php"><span class="nav-icon">&#127970;</span> Branches</a>
            <a class="nav-link <?= nav_active('departments.php', $current) ?>" href="<?= $base ?>/departments.php"><span class="nav-icon">&#128193;</span> Departments</a>
            <a class="nav-link <?= nav_active('sections.php', $current) ?>" href="<?= $base ?>/sections.php"><span class="nav-icon">&#128194;</span> Sections</a>

            <div class="nav-section-label">Operations</div>
            <a class="nav-link <?= nav_active('items.php', $current) ?>" href="<?= $base ?>/items.php"><span class="nav-icon">&#128230;</span> Inventory Items</a>
            <a class="nav-link <?= nav_active('transactions.php', $current) ?>" href="/uiri-ims/transactions.php"><span class="nav-icon">&#128179;</span> Transactions</a>
            <a class="nav-link <?= nav_active('users.php', $current) ?>" href="<?= $base ?>/users.php"><span class="nav-icon">&#128100;</span> Users &amp; Roles</a>
            <a class="nav-link <?= nav_active('suppliers.php', $current) ?>" href="<?= $base ?>/suppliers.php"><span class="nav-icon">&#128666;</span> Suppliers</a>
            <a class="nav-link <?= nav_active('reports.php', $current) ?>" href="<?= $base ?>/reports.php"><span class="nav-icon">&#128202;</span> Reports</a>
            <a class="nav-link <?= nav_active('audit_logs.php', $current) ?>" href="<?= $base ?>/audit_logs.php"><span class="nav-icon">&#128220;</span> Audit Logs</a>

        <?php elseif ($role === 'dept_manager'): ?>
            <div class="nav-section-label">Overview</div>
            <a class="nav-link <?= nav_active('dashboard.php', $current) ?>" href="<?= $base ?>/dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>

            <div class="nav-section-label">Department</div>
            <a class="nav-link <?= nav_active('sections.php', $current) ?>" href="<?= $base ?>/sections.php"><span class="nav-icon">&#128194;</span> Sections</a>
            <a class="nav-link <?= nav_active('items.php', $current) ?>" href="<?= $base ?>/items.php"><span class="nav-icon">&#128230;</span> Inventory Items</a>
            <a class="nav-link <?= nav_active('transactions.php', $current) ?>" href="/uiri-ims/transactions.php"><span class="nav-icon">&#128179;</span> Transactions</a>
            <a class="nav-link <?= nav_active('reports.php', $current) ?>" href="<?= $base ?>/reports.php"><span class="nav-icon">&#128202;</span> Reports</a>

        <?php elseif ($role === 'section_chief'): ?>
            <div class="nav-section-label">Overview</div>
            <a class="nav-link <?= nav_active('dashboard.php', $current) ?>" href="<?= $base ?>/dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>

            <div class="nav-section-label">My Section</div>
            <a class="nav-link <?= nav_active('items.php', $current) ?>" href="<?= $base ?>/items.php"><span class="nav-icon">&#128230;</span> Inventory Items</a>
            <a class="nav-link <?= nav_active('transactions.php', $current) ?>" href="/uiri-ims/transactions.php"><span class="nav-icon">&#128179;</span> Transactions</a>
            <?php if (!empty($u['can_view_reports_own_section'])): ?>
                <a class="nav-link <?= nav_active('reports.php', $current) ?>" href="<?= $base ?>/reports.php"><span class="nav-icon">&#128202;</span> Section Reports</a>
            <?php endif; ?>
            <?php if (!empty($u['can_procurement_liaison']) || !empty($u['can_request_writeoff'])): ?>
                <a class="nav-link <?= nav_active('requests.php', $current) ?>" href="<?= $base ?>/requests.php"><span class="nav-icon">&#128179;</span> Requests</a>
            <?php endif; ?>
            <?php if (!empty($u['can_physical_audit'])): ?>
                <a class="nav-link <?= nav_active('audit.php', $current) ?>" href="<?= $base ?>/audit.php"><span class="nav-icon">&#128200;</span> Stock Audit</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar"><?= e($initials) ?></div>
            <div>
                <div><?= e($u['full_name']) ?></div>
                <div class="role-tag"><?= e(role_label($role)) ?></div>
            </div>
        </div>
        <a href="/uiri-ims/auth/logout.php" class="logout-btn">Log out</a>
    </div>
</aside>
