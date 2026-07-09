<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

$total = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT a.*, u.full_name, u.role FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute();
$logs = $stmt->fetchAll();
$totalPages = max(1, ceil($total / $perPage));

$pageTitle = 'Audit Logs';
$breadcrumb = 'Complete trail of all actions performed across the system';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2 class="mt-0">System Activity Log (<?= (int)$total ?> entries)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date &amp; Time</th><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="font-size:12px; white-space:nowrap;"><?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><?= e($log['full_name'] ?? 'Unknown / System') ?></td>
                    <td><?= $log['role'] ? e(role_label($log['role'])) : '—' ?></td>
                    <td><span class="badge badge-navy"><?= e(str_replace('_',' ', $log['action'])) ?></span></td>
                    <td class="text-muted" style="font-size:12.5px;"><?= e($log['details']) ?></td>
                    <td class="text-muted" style="font-size:11.5px;"><?= e($log['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="6" class="text-muted">No activity recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="flex gap-8" style="margin-top:16px; justify-content:center;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>" class="btn btn-sm <?= $p==$page?'btn-primary':'btn-outline' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
