<?php
// Expects $pageTitle and optionally $breadcrumb to be set before include.
$u = current_user();
$isProfileRole = $u && in_array($u['role'] ?? '', ['admin', 'dept_manager', 'section_chief'], true);
$profileInitials = '';
if ($u) {
    foreach (explode(' ', $u['full_name'] ?? '') as $part) {
        $profileInitials .= strtoupper(substr($part, 0, 1));
    }
    $profileInitials = substr($profileInitials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'UIRI IMS') ?> — UIRI Inventory Management</title>
<link rel="stylesheet" href="/uiri-ims/assets/css/style.css">
<link rel="icon" href="/uiri-ims/assets/img/uiri-logo.png">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-expanded="true" aria-controls="appSidebar" title="Toggle sidebar">
                <span></span><span></span><span></span>
            </button>
            <div>
                <h1><?= e($pageTitle ?? '') ?></h1>
                <?php if (!empty($breadcrumb)): ?>
                    <div class="breadcrumb"><?= $breadcrumb /* pre-escaped */ ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="topbar-right">
            <div class="profile-chip">
                <div class="profile-avatar">
                    <?php if (!empty($u['profile_picture'])): ?>
                        <img src="<?= e(user_avatar_url($u)) ?>" alt="Profile picture">
                    <?php else: ?>
                        <span><?= e($profileInitials) ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="profile-name"><?= e($u['full_name'] ?? '') ?></div>
                    <div class="profile-role"><?= e(role_label($u['role'] ?? '')) ?></div>
                </div>
            </div>
            <?php if ($isProfileRole): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('profileModal').classList.add('active')">Manage Profile</button>
            <?php endif; ?>
            <span class="scope-badge"><?= e(role_label($u['role'])) ?></span>
        </div>
    </div>
    <?php if ($isProfileRole): ?>
    <div class="modal-overlay" id="profileModal">
        <div class="modal-box">
            <button class="modal-close" onclick="document.getElementById('profileModal').classList.remove('active')">&times;</button>
            <h3>Manage Your Profile</h3>
            <form method="POST" action="/uiri-ims/actions/profile_actions.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="profile-preview-box">
                    <?php if (!empty($u['profile_picture'])): ?>
                        <img src="<?= e(user_avatar_url($u)) ?>" alt="Profile picture" class="profile-preview-image">
                    <?php else: ?>
                        <div class="profile-preview-image profile-preview-text"><?= e($profileInitials) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="6">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div class="content">
        <?php foreach (get_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
