<?php
// admin/student_view.php
include __DIR__ . '/header.php';

$studentDbId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT u.*, s.id as student_db_id, s.face_registered, s.face_data 
                    FROM students s 
                    JOIN users u ON s.user_id = u.id 
                    WHERE s.id = ?");
$stmt->execute([$studentDbId]);
$student = $stmt->fetch();

if (!$student) {
    echo '<div class="alert alert-danger">Student not found.</div>';
    include __DIR__ . '/footer.php';
    exit();
}

// Attendance stats for student
$stmt = $db->prepare("SELECT 
    COUNT(*) as total_classes,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance WHERE student_id = ?");
$stmt->execute([$studentDbId]);
$attStats = $stmt->fetch();

$tot = intval($attStats['total_classes']);
$pres = intval($attStats['present_count']);
$late = intval($attStats['late_count']);
$abs = intval($attStats['absent_count']);
$attRate = $tot > 0 ? round((($pres + $late) / $tot) * 100, 1) : 100;

// Enrolled Subjects
$stmt = $db->prepare("SELECT s.* FROM subjects s JOIN student_subjects ss ON s.id = ss.subject_id WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled'");
$stmt->execute([$studentDbId]);
$enrolledSubjects = $stmt->fetchAll();

// Attendance Log
$stmt = $db->prepare("SELECT a.*, s.subject_code, s.subject_name FROM attendance a JOIN subjects s ON a.subject_id = s.id WHERE a.student_id = ? ORDER BY a.attendance_date DESC, a.time_in DESC");
$stmt->execute([$studentDbId]);
$attLogs = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> Student Profile</h3>
        <p class="text-muted">Viewing student details and attendance analytics for <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="<?= baseUrl('admin/students.php') ?>" class="btn btn-outline-secondary font-weight-bold">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Students
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 text-center p-4">
            <img src="<?= baseUrl('uploads/profiles/' . ($student['profile_picture'] ?? 'default_avatar.png')) ?>" alt="Avatar" class="avatar-lg mx-auto mb-3" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['first_name']) ?>&background=0D8ABC&color=fff'">
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h4>
            <span class="badge bg-primary fs-6 px-3 py-2 mb-3"><?= htmlspecialchars($student['student_id']) ?></span>

            <ul class="list-group list-group-flush text-start small mb-3">
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Course:</span>
                    <span class="fw-bold"><?= htmlspecialchars($student['course']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Year & Section:</span>
                    <span class="fw-bold"><?= htmlspecialchars($student['year_level']) ?> - <?= htmlspecialchars($student['section']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Email:</span>
                    <span class="fw-bold"><?= htmlspecialchars($student['email']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Face Biometric:</span>
                    <?php if ($student['face_registered']): ?>
                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Registered</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Not Registered</span>
                    <?php endif; ?>
                </li>
            </ul>

            <a href="<?= baseUrl('admin/face_registration.php?student_id=' . $student['student_db_id']) ?>" class="btn btn-outline-primary w-100 fw-bold">
                <i class="fa-solid fa-camera me-1"></i> <?= $student['face_registered'] ? 'Update Face Data' : 'Register Face Data' ?>
            </a>
        </div>
    </div>

    <!-- Stats & Enrolled Subjects -->
    <div class="col-md-8">
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-light">
                    <div class="text-muted small">Classes Attended</div>
                    <h3 class="fw-bold text-success mb-0"><?= $pres + $late ?> / <?= $tot ?></h3>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-light">
                    <div class="text-muted small">Absences</div>
                    <h3 class="fw-bold text-danger mb-0"><?= $abs ?></h3>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-light">
                    <div class="text-muted small">Attendance Rate</div>
                    <h3 class="fw-bold text-primary mb-0"><?= $attRate ?>%</h3>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-open text-primary me-2"></i> Enrolled Subjects</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Code</th>
                                <th>Subject Name</th>
                                <th>Schedule</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrolledSubjects)): ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">No enrolled subjects.</td></tr>
                            <?php else: ?>
                                <?php foreach ($enrolledSubjects as $es): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($es['subject_code']) ?></td>
                                        <td><?= htmlspecialchars($es['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($es['day']) ?> (<?= date('h:i A', strtotime($es['start_time'])) ?> - <?= date('h:i A', strtotime($es['end_time'])) ?>)</td>
                                        <td><?= htmlspecialchars($es['room']) ?></td>
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

<!-- Attendance History Log -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 py-3">
        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Student Attendance History</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Subject</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attLogs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No attendance logs available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attLogs as $log): ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?= date('M d, Y', strtotime($log['attendance_date'])) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['subject_code']) ?></span> <?= htmlspecialchars($log['subject_name']) ?></td>
                                <td><?= date('h:i A', strtotime($log['time_in'])) ?></td>
                                <td><?= $log['time_out'] ? date('h:i A', strtotime($log['time_out'])) : 'Active' ?></td>
                                <td>
                                    <span class="badge <?= $log['status'] === 'Present' ? 'badge-present' : ($log['status'] === 'Late' ? 'badge-late' : 'badge-absent') ?>">
                                        <?= htmlspecialchars($log['status']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($log['verification_method']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
