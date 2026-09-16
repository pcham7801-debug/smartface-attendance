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

// Handle Add Subject to Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_subject_student'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $subjectId = intval($_POST['subject_id'] ?? 0);
        if ($subjectId > 0) {
            $stmt = $db->prepare("INSERT INTO student_subjects (student_id, subject_id, enrollment_status) VALUES (?, ?, 'enrolled') ON DUPLICATE KEY UPDATE enrollment_status = 'enrolled'");
            $stmt->execute([$studentDbId, $subjectId]);
            logAudit('Enroll Subject Profile', "Enrolled student ID #{$studentDbId} into subject ID #{$subjectId}");
            setFlash('success', 'Subject successfully enrolled for student!');
            header('Location: ' . baseUrl('admin/student_view.php?id=' . $studentDbId));
            exit();
        }
    }
}

// Handle Drop Subject from Student
if (isset($_GET['drop_enrollment_id'])) {
    $dropId = intval($_GET['drop_enrollment_id']);
    $stmt = $db->prepare("DELETE FROM student_subjects WHERE id = ? AND student_id = ?");
    $stmt->execute([$dropId, $studentDbId]);
    logAudit('Drop Subject Profile', "Removed enrollment #{$dropId} for student #{$studentDbId}");
    setFlash('success', 'Subject removed from student enrollment.');
    header('Location: ' . baseUrl('admin/student_view.php?id=' . $studentDbId));
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
$stmt = $db->prepare("SELECT s.*, ss.id as enrollment_id FROM subjects s JOIN student_subjects ss ON s.id = ss.subject_id WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled' ORDER BY s.subject_code ASC");
$stmt->execute([$studentDbId]);
$enrolledSubjects = $stmt->fetchAll();

// Fetch all active subjects available for enrollment
$availStmt = $db->query("SELECT id, subject_code, subject_name, day, start_time, end_time, room, instructor FROM subjects WHERE status = 'active' ORDER BY subject_code ASC");
$allAvailableSubjects = $availStmt->fetchAll();

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
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-open text-primary me-2"></i> Enrolled Subjects</h5>
                <button type="button" class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Subject
                </button>
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
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrolledSubjects)): ?>
                                <tr><td colspan="5" class="text-center py-3 text-muted">No enrolled subjects. Click "+ Add Subject" to assign a subject.</td></tr>
                            <?php else: ?>
                                <?php foreach ($enrolledSubjects as $es): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($es['subject_code']) ?></td>
                                        <td><?= htmlspecialchars($es['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($es['day']) ?> (<?= date('h:i A', strtotime($es['start_time'])) ?> - <?= date('h:i A', strtotime($es['end_time'])) ?>)</td>
                                        <td><?= htmlspecialchars($es['room']) ?></td>
                                        <td class="text-end pe-3">
                                            <a href="?id=<?= $studentDbId ?>&drop_enrollment_id=<?= $es['enrollment_id'] ?>" class="btn btn-sm btn-outline-danger" title="Remove Subject" onclick="return confirm('Remove student from <?= htmlspecialchars($es['subject_code']) ?>?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Subject Modal -->
        <div class="modal fade" id="addSubjectModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <form action="" method="POST">
                        <input type="hidden" name="action_add_subject_student" value="1">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-book-medical text-primary me-2"></i> Enroll in Subject</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body pt-2">
                            <div class="mb-3">
                                <label for="subject_id" class="form-label fw-bold small text-muted">Select Subject to Add</label>
                                <select name="subject_id" id="subject_id" class="form-select form-select-lg" required>
                                    <option value="">-- Choose Subject --</option>
                                    <?php 
                                    $enrolledIds = array_column($enrolledSubjects, 'id');
                                    foreach ($allAvailableSubjects as $asub): 
                                        $already = in_array(intval($asub['id']), $enrolledIds);
                                    ?>
                                        <option value="<?= $asub['id'] ?>" <?= $already ? 'disabled' : '' ?>>
                                            <?= htmlspecialchars($asub['subject_code']) ?> - <?= htmlspecialchars($asub['subject_name']) ?> 
                                            (<?= htmlspecialchars($asub['day']) ?> <?= date('h:i A', strtotime($asub['start_time'])) ?>)
                                            <?= $already ? ' [Already Enrolled]' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold px-3">
                                <i class="fa-solid fa-plus me-1"></i> Enroll Student
                            </button>
                        </div>
                    </form>
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
