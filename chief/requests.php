<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['section_chief']);
$user = current_user();
if (!$user['can_procurement_liaison'] && !$user['can_request_writeoff']) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>403 - Access Denied</h2><p>Your account is not permitted to submit requests.</p><a href="/uiri-ims/chief/dashboard.php">Return to dashboard</a></div>');
}
if (!$user['section_id']) {
    $pageTitle = 'Section Requests';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><div class="empty-icon">&#9888;</div>You have not been assigned to a section yet. Please contact the administrator.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$sectionStmt = $pdo->prepare(
    "SELECT s.name AS section_name, d.name AS dept_name, b.name AS branch_name
     FROM sections s
     JOIN departments d ON s.department_id=d.id
     JOIN branches b ON d.branch_id=b.id
     WHERE s.id=?"
);
$sectionStmt->execute([$user['section_id']]);
$section = $sectionStmt->fetch();

$allowedTypes = [];
if ($user['can_procurement_liaison']) {
    $allowedTypes['procurement'] = 'Procurement Request';
}
if ($user['can_request_writeoff']) {
    $allowedTypes['writeoff'] = 'Disposal / Write-off Request';
}

$requestStmt = $pdo->prepare(
    "SELECT sr.*, u.full_name AS submitted_by
     FROM section_requests sr
     JOIN users u ON sr.user_id=u.id
     WHERE sr.section_id = ?
     ORDER BY sr.created_at DESC LIMIT 50"
);
$requestStmt->execute([$user['section_id']]);
$requests = $requestStmt->fetchAll();

$pageTitle = 'Section Requests';
$breadcrumb = e($section['branch_name']) . ' &rsaquo; ' . e($section['dept_name']) . ' &rsaquo; ' . e($section['section_name']);
include __DIR__ . '/../includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <h3>Submit a New Request</h3>
        <form method="POST" action="/uiri-ims/actions/request_actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="create">
            <div class="form-group"><label>Request Type</label>
                <select name="type" required>
                    <option value="">-- Select Request Type --</option>
                    <?php foreach ($allowedTypes as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="4" required></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Submit Request</button></div>
        </form>
    </div>
    <div class="card">
        <h3>Recent Requests</h3>
        <table>
            <thead><tr><th>Date</th><th>Type</th><th>Status</th><th>Title</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td style="font-size:12px;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    <td><?= e($allowedTypes[$r['request_type']] ?? ucfirst($r['request_type'])) ?></td>
                    <td><?php if ($r['status'] === 'pending'): ?><span class="badge badge-warning">Pending</span><?php elseif ($r['status'] === 'approved'): ?><span class="badge badge-success">Approved</span><?php else: ?><span class="badge badge-danger">Rejected</span><?php endif; ?></td>
                    <td><?= e($r['title']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?><tr><td colspan="4" class="text-muted">No requests have been submitted for this section yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
