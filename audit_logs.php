<?php
// admin/audit_logs.php
include __DIR__ . '/header.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT a.*, u.username, u.first_name, u.last_name, u.role
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE 1=1";

$params = [];
if (!empty($search)) {
    $sql .= " AND (a.action LIKE ? OR a.description LIKE ? OR u.username LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term];
}

$sql .= " ORDER BY a.created_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i> System Audit Logs</h3>
        <p class="text-muted">Security and operational audit trail for system activities and manual adjustments</p>
    </div>
</div>

<!-- Search Filter -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Search by Action, Description, or User..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-search me-1"></i> Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Audit Logs Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No audit logs found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-3 text-nowrap"><small class="fw-semibold text-muted"><?= date('M d, Y h:i:s A', strtotime($log['created_at'])) ?></small></td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                        <small class="text-muted">@<?= htmlspecialchars($log['username']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted font-italic">System / Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $log['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= ucfirst($log['role'] ?? 'system') ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td style="max-width: 400px;"><?= htmlspecialchars($log['description']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
