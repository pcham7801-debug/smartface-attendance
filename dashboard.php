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
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-chart-pie text-primary me-2"></i> Admin Dashboard</h3>
        <p class="text-muted">Overview of institution attendance monitoring and system metrics</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card stat-card border-0 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-primary text-white me-2">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Students</div>
                    <h5 class="fw-bold mb-0"><?= $totalStudents ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card border-0 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-dark text-white me-2">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <div class="text-muted small">Subjects</div>
                    <h5 class="fw-bold mb-0"><?= $totalSubjects ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card border-0 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-success text-white me-2">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <div class="text-muted small">Present Today</div>
                    <h5 class="fw-bold mb-0"><?= $presentToday ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card border-0 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-warning text-dark me-2">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="text-muted small">Late Today</div>
                    <h5 class="fw-bold mb-0"><?= $lateToday ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card border-0 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-danger text-white me-2">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div>
                    <div class="text-muted small">Absent Today</div>
                    <h5 class="fw-bold mb-0"><?= $absentToday ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card border-0 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-info text-white me-2">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Records</div>
                    <h5 class="fw-bold mb-0"><?= $totalAttendanceRecords ?></h5>
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

<?php include __DIR__ . '/footer.php'; ?>
