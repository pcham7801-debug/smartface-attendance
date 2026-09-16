<?php
// student/dashboard.php
include __DIR__ . '/header.php';

$studentId = $studentData['student_db_id'];
$today = date('Y-m-d');

// Fetch Attendance Summary metrics
$stmt = $db->prepare("SELECT 
    COUNT(*) as total_classes,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'Excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance WHERE student_id = ?");
$stmt->execute([$studentId]);
$summary = $stmt->fetch();

$totalClasses = intval($summary['total_classes']);
$presentCount = intval($summary['present_count']);
$lateCount = intval($summary['late_count']);
$absentCount = intval($summary['absent_count']);
$excusedCount = intval($summary['excused_count']);

$attendancePercentage = $totalClasses > 0 ? round((($presentCount + $lateCount) / $totalClasses) * 100, 1) : 100;

// Fetch Today's Attendance Sessions
$stmt = $db->prepare("SELECT a.*, s.subject_code, s.subject_name, s.start_time, s.end_time, s.room
    FROM attendance a 
    JOIN subjects s ON a.subject_id = s.id 
    WHERE a.student_id = ? AND a.attendance_date = ?");
$stmt->execute([$studentId, $today]);
$todayAttendance = $stmt->fetchAll();

// Fetch Enrolled Subjects & Subject-wise attendance rates
$stmt = $db->prepare("SELECT s.*,
    (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = ss.student_id) as total_subject_classes,
    (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = ss.student_id AND a.status IN ('Present', 'Late')) as attended_subject_classes
    FROM subjects s
    JOIN student_subjects ss ON s.id = ss.subject_id
    WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled' AND s.status = 'active'");
$stmt->execute([$studentId]);
$enrolledSubjects = $stmt->fetchAll();
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card bg-primary text-white border-0 rounded-4 shadow-sm">
            <div class="card-body p-4 d-flex flex-column flex-md-row align-items-center justify-content-between">
                <div>
                    <h3 class="fw-bold mb-1">Welcome, <?= htmlspecialchars($studentData['first_name'] . ' ' . $studentData['last_name']) ?>!</h3>
                    <p class="mb-0 text-white-50">
                        Student ID: <strong><?= htmlspecialchars($studentData['student_id']) ?></strong> | 
                        <?= htmlspecialchars($studentData['course']) ?> - <?= htmlspecialchars($studentData['year_level']) ?> (<?= htmlspecialchars($studentData['section']) ?>)
                    </p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2">
                    <a href="<?= baseUrl('student/face_attendance.php') ?>" class="btn btn-light fw-semibold rounded-3 text-primary">
                        <i class="fa-solid fa-camera me-1"></i> Sign In with Face
                    </a>
                    <a href="<?= baseUrl('student/signout.php') ?>" class="btn btn-outline-light fw-semibold rounded-3">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$studentData['face_registered']): ?>
    <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
            <strong>Biometric Face Not Registered!</strong> Please register your face to use the automatic face attendance scanner.
        </div>
        <a href="<?= baseUrl('student/profile.php#face-registration') ?>" class="btn btn-warning btn-sm fw-bold">Register Face Now</a>
    </div>
<?php endif; ?>

<!-- Metrics Row -->
<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-primary text-white me-3">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Total Classes</div>
                    <h4 class="fw-bold mb-0 text-dark"><?= $totalClasses ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-success text-white me-3">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Present</div>
                    <h4 class="fw-bold mb-0 text-dark"><?= $presentCount ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-warning text-dark me-3">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Late</div>
                    <h4 class="fw-bold mb-0 text-dark"><?= $lateCount ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card h-100 border-0 p-3 shadow-sm">
            <div class="d-flex align-items-center h-100">
                <div class="stat-icon bg-info text-white me-3">
                    <i class="fa-solid fa-percent"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="stat-label">Attendance Rate</div>
                    <h4 class="fw-bold mb-0 text-dark"><?= $attendancePercentage ?>%</h4>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Today's Attendance -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calendar-day text-primary me-2"></i> Today's Attendance</h5>
            </div>
            <div class="card-body">
                <div class="text-center p-3 bg-light rounded-3 mb-3">
                    <div id="liveClock" class="clock-display">00:00:00 AM</div>
                    <div id="liveDate" class="text-muted small font-weight-bold">Loading date...</div>
                </div>

                <?php if (empty($todayAttendance)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-clipboard-check fs-1 mb-2 text-secondary"></i>
                        <p class="mb-0">No attendance recorded yet today.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($todayAttendance as $att): ?>
                            <div class="list-group-item px-0 py-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($att['subject_code']) ?> - <?= htmlspecialchars($att['subject_name']) ?></h6>
                                    <span class="badge <?= $att['status'] === 'Present' ? 'badge-present' : ($att['status'] === 'Late' ? 'badge-late' : 'badge-absent') ?>">
                                        <?= htmlspecialchars($att['status']) ?>
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    <i class="fa-regular fa-clock me-1"></i> Time In: <strong><?= date('h:i A', strtotime($att['time_in'])) ?></strong> | 
                                    Time Out: <strong><?= $att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : 'Active Session' ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enrolled Subjects Table -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-open text-primary me-2"></i> Enrolled Subjects</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Subject Code</th>
                                <th>Subject Name</th>
                                <th>Schedule</th>
                                <th class="text-center">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrolledSubjects)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No enrolled subjects assigned yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($enrolledSubjects as $subj): 
                                    $subjTot = intval($subj['total_subject_classes']);
                                    $subjAtt = intval($subj['attended_subject_classes']);
                                    $rate = $subjTot > 0 ? round(($subjAtt / $subjTot) * 100) : 100;
                                ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($subj['subject_code']) ?></td>
                                        <td><?= htmlspecialchars($subj['subject_name']) ?></td>
                                        <td>
                                            <small class="d-block text-dark fw-semibold"><?= htmlspecialchars($subj['day']) ?></small>
                                            <small class="text-muted"><?= date('h:i A', strtotime($subj['start_time'])) ?> - <?= date('h:i A', strtotime($subj['end_time'])) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress me-2 d-inline-block align-middle" style="width: 60px; height: 8px;">
                                                <div class="progress-bar bg-<?= $rate >= 80 ? 'success' : ($rate >= 75 ? 'warning' : 'danger') ?>" style="width: <?= $rate ?>%"></div>
                                            </div>
                                            <span class="fw-bold small"><?= $rate ?>%</span>
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

<?php include __DIR__ . '/footer.php'; ?>
