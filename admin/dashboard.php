<?php
// admin/dashboard.php
include __DIR__ . '/header.php';

$today = date('Y-m-d');

// 1. Stat Counters
$stmt = $db->query("SELECT COUNT(*) FROM students");
$totalStudents = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM subjects WHERE status = 'active'");
$totalSubjects = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                             SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                             SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                      FROM attendance WHERE attendance_date = ?");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

$presentToday = intval($todayStats['present_count']);
$lateToday = intval($todayStats['late_count']);
$absentToday = intval($todayStats['absent_count']);

$stmt = $db->query("SELECT COUNT(*) FROM attendance");
$totalAttendanceRecords = $stmt->fetchColumn();

// 2. Chart Data: Attendance Status Breakdown (Overall)
$stmt = $db->query("SELECT status, COUNT(*) as count FROM attendance GROUP BY status");
$statusBreakdown = $stmt->fetchAll();
$statusLabels = [];
$statusCounts = [];
foreach ($statusBreakdown as $sb) {
    $statusLabels[] = $sb['status'];
    $statusCounts[] = intval($sb['count']);
}

// 3. Chart Data: Subject Attendance Breakdown
$stmt = $db->query("SELECT s.subject_code, COUNT(a.id) as att_count FROM subjects s LEFT JOIN attendance a ON s.id = a.subject_id GROUP BY s.id ORDER BY att_count DESC LIMIT 5");
$subjectBreakdown = $stmt->fetchAll();
$subjLabels = [];
$subjCounts = [];
foreach ($subjectBreakdown as $sb) {
    $subjLabels[] = $sb['subject_code'];
    $subjCounts[] = intval($sb['att_count']);
}

// 4. Recent Attendance Log
$stmt = $db->query("SELECT a.*, u.first_name, u.last_name, u.student_id as stu_code, s.subject_code 
                    FROM attendance a 
                    JOIN students st ON a.student_id = st.id 
                    JOIN users u ON st.user_id = u.id 
                    JOIN subjects s ON a.subject_id = s.id 
                    ORDER BY a.created_at DESC LIMIT 6");
$recentLogs = $stmt->fetchAll();

// 5. Pending Sign-Out Requests
$stmt = $db->query("SELECT a.*, u.first_name, u.last_name, u.student_id as stu_code, u.course, u.section,
                           s.subject_code, s.subject_name, s.room
                    FROM attendance a 
                    JOIN students st ON a.student_id = st.id 
                    JOIN users u ON st.user_id = u.id 
                    JOIN subjects s ON a.subject_id = s.id 
                    WHERE a.signout_status = 'pending'
                    ORDER BY a.updated_at DESC");
$pendingSignouts = $stmt->fetchAll();
$pendingSignoutCount = count($pendingSignouts);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-chart-pie text-primary me-2"></i> Admin Dashboard</h3>
        <p class="text-muted">Overview of institution attendance monitoring and system metrics</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="row row-cols-2 row-cols-sm-3 row-cols-xl-6 g-3 mb-4">
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-primary text-white me-2">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Total Students</div>
                    <h5 class="fw-bold mb-0 text-dark"><?= $totalStudents ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-dark text-white me-2">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Subjects</div>
                    <h5 class="fw-bold mb-0 text-dark"><?= $totalSubjects ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-success text-white me-2">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Present Today</div>
                    <h5 class="fw-bold mb-0 text-dark"><?= $presentToday ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-warning text-dark me-2">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Late Today</div>
                    <h5 class="fw-bold mb-0 text-dark"><?= $lateToday ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-danger text-white me-2">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Absent Today</div>
                    <h5 class="fw-bold mb-0 text-dark"><?= $absentToday ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-info text-white me-2">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Total Records</div>
                    <h5 class="fw-bold mb-0 text-dark"><?= $totalAttendanceRecords ?></h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sign-Out Approval Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-clipboard-check text-warning me-2"></i> Attendance Sign-Out Approvals
                    </h5>
                    <small class="text-muted">Review, approve, or reject student sign-out requests in real-time</small>
                </div>
                <div>
                    <?php if ($pendingSignoutCount > 0): ?>
                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold" id="pendingBadge">
                            <i class="fa-solid fa-bell me-1 fa-bounce"></i> <?= $pendingSignoutCount ?> Pending Sign-Out<?= $pendingSignoutCount > 1 ? 's' : '' ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-semibold" id="pendingBadge">
                            <i class="fa-solid fa-check-double me-1"></i> No Pending Sign-Out Requests
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="signoutApprovalTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Student</th>
                                <th>Course & Section</th>
                                <th>Subject</th>
                                <th>Time In</th>
                                <th>Time Out (Requested)</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Approval Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingSignouts)): ?>
                                <tr id="noSignoutRow">
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-circle-check text-success fs-3 d-block mb-2"></i>
                                        All active attendance sessions are up-to-date. No sign-out approval requests.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingSignouts as $req): ?>
                                    <tr id="signout-row-<?= $req['id'] ?>">
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></div>
                                            <div class="small text-muted"><i class="fa-solid fa-id-card me-1"></i> <?= htmlspecialchars($req['stu_code'] ?? 'N/A') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars(($req['course'] ?? '') . ' ' . ($req['section'] ?? '')) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-primary"><?= htmlspecialchars($req['subject_code']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($req['subject_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><i class="fa-regular fa-clock me-1 text-success"></i> <?= date('h:i A', strtotime($req['time_in'])) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning"><i class="fa-solid fa-hourglass-half me-1"></i> <?= date('h:i A', strtotime($req['time_out'])) ?></span>
                                            <?php if (!empty($req['signout_reason'])): ?>
                                                <div class="small text-muted mt-1 fst-italic">"<?= htmlspecialchars($req['signout_reason']) ?>"</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock-rotate-left me-1"></i> Awaiting Approval</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group shadow-sm">
                                                <button type="button" class="btn btn-sm btn-success fw-bold px-3 btn-action-signout" data-id="<?= $req['id'] ?>" data-action="approve" title="Approve Sign Out">
                                                    <i class="fa-solid fa-check me-1"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3 btn-action-signout" data-id="<?= $req['id'] ?>" data-action="reject" title="Reject Sign Out">
                                                    <i class="fa-solid fa-xmark me-1"></i> Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-doughnut text-primary me-2"></i> Attendance Status Breakdown</h5>
                <div style="height: 260px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-column text-primary me-2"></i> Attendance by Top Subjects</h5>
                <div style="height: 260px;">
                    <canvas id="subjectChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Attendance Table -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Recent Attendance Records</h5>
                <a href="<?= baseUrl('admin/attendance.php') ?>" class="btn btn-outline-primary btn-sm fw-bold">View All Records</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Student ID</th>
                                <th>Student Name</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Status</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLogs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No attendance logs available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($log['stu_code']) ?></td>
                                        <td><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['subject_code']) ?></span></td>
                                        <td><?= date('M d, Y', strtotime($log['attendance_date'])) ?></td>
                                        <td><?= date('h:i A', strtotime($log['time_in'])) ?></td>
                                        <td>
                                            <span class="badge <?= $log['status'] === 'Present' ? 'badge-present' : ($log['status'] === 'Late' ? 'badge-late' : 'badge-absent') ?>">
                                                <?= htmlspecialchars($log['status']) ?>
                                            </span>
                                        </td>
                                        <td><small class="text-muted"><i class="fa-solid fa-camera me-1"></i> <?= htmlspecialchars($log['verification_method']) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Status Doughnut Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusCounts) ?>,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#06b6d4']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Subject Bar Chart
    const subjCtx = document.getElementById('subjectChart').getContext('2d');
    new Chart(subjCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($subjLabels) ?>,
            datasets: [{
                label: 'Attendance Count',
                data: <?= json_encode($subjCounts) ?>,
                backgroundColor: '#2563eb'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>

<script>
// Sign-Out Approval Actions
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-action-signout').forEach(btn => {
        btn.addEventListener('click', async () => {
            const attendanceId = btn.dataset.id;
            const action = btn.dataset.action;
            const actionLabel = action === 'approve' ? 'APPROVE' : 'REJECT';

            if (!confirm(`Are you sure you want to ${actionLabel} this sign-out request?`)) return;

            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const response = await fetch('<?= baseUrl("api/approve_signout.php") ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ attendance_id: attendanceId, action: action })
                });

                const result = await response.json();
                if (result.success) {
                    const row = document.getElementById('signout-row-' + attendanceId);
                    if (row) {
                        row.style.transition = 'opacity 0.4s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            // Check if table is now empty
                            const remaining = document.querySelectorAll('[id^="signout-row-"]');
                            if (remaining.length === 0) {
                                const tbody = document.querySelector('#signoutApprovalTable tbody');
                                tbody.innerHTML = '<tr id="noSignoutRow"><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-circle-check text-success fs-3 d-block mb-2"></i>All sign-out requests have been processed.</td></tr>';
                                const badge = document.getElementById('pendingBadge');
                                if (badge) {
                                    badge.className = 'badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-semibold';
                                    badge.innerHTML = '<i class="fa-solid fa-check-double me-1"></i> No Pending Sign-Out Requests';
                                }
                            } else {
                                const badge = document.getElementById('pendingBadge');
                                if (badge && badge.classList.contains('bg-warning')) {
                                    badge.innerHTML = '<i class="fa-solid fa-bell me-1 fa-bounce"></i> ' + remaining.length + ' Pending Sign-Out' + (remaining.length > 1 ? 's' : '');
                                }
                            }
                        }, 400);
                    }
                    alert(result.message);
                } else {
                    alert(result.message || 'Failed to process request.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (err) {
                alert('Connection error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
