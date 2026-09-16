<?php
// admin/enrollment.php
include __DIR__ . '/header.php';

$error = '';

// Handle Enrollment Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_enroll_student'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token.';
    } else {
        $studentId = intval($_POST['student_id'] ?? 0);
        $subjectId = intval($_POST['subject_id'] ?? 0);

        if ($studentId <= 0 || $subjectId <= 0) {
            $error = 'Please select both a student and a subject.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO student_subjects (student_id, subject_id, enrollment_status) VALUES (?, ?, 'enrolled') ON DUPLICATE KEY UPDATE enrollment_status = 'enrolled'");
                $stmt->execute([$studentId, $subjectId]);

                logAudit('Enroll Student', "Enrolled student ID #{$studentId} in subject ID #{$subjectId}");
                setFlash('success', 'Student enrolled in subject successfully!');
                header('Location: ' . baseUrl('admin/enrollment.php'));
                exit();
            } catch (Exception $e) {
                $error = 'Failed to enroll student: ' . $e->getMessage();
            }
        }
    }
}

// Handle Remove Enrollment
if (isset($_GET['drop_id'])) {
    $dropId = intval($_GET['drop_id']);
    try {
        $stmt = $db->prepare("DELETE FROM student_subjects WHERE id = ?");
        $stmt->execute([$dropId]);
        logAudit('Drop Enrollment', "Removed enrollment record ID #{$dropId}");
        setFlash('success', 'Student dropped from subject.');
        header('Location: ' . baseUrl('admin/enrollment.php'));
        exit();
    } catch (Exception $e) {
        $error = 'Failed to drop enrollment: ' . $e->getMessage();
    }
}

// Fetch Students & Subjects for Dropdowns
$stmt = $db->query("SELECT s.id, u.student_id, u.first_name, u.last_name, u.course, u.year_level FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.first_name ASC");
$studentsList = $stmt->fetchAll();

$stmt = $db->query("SELECT id, subject_code, subject_name FROM subjects WHERE status = 'active' ORDER BY subject_code ASC");
$subjectsList = $stmt->fetchAll();

// Fetch Enrolled Mappings
$subjectFilter = intval($_GET['subject_id'] ?? 0);
$sql = "SELECT ss.id as enrollment_id, ss.created_at as enrolled_at,
               u.student_id as stu_code, u.first_name, u.last_name, u.course, u.year_level, u.section,
               s.subject_code, s.subject_name
        FROM student_subjects ss
        JOIN students st ON ss.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN subjects s ON ss.subject_id = s.id
        WHERE ss.enrollment_status = 'enrolled'";

$params = [];
if ($subjectFilter > 0) {
    $sql .= " AND ss.subject_id = ?";
    $params[] = $subjectFilter;
}

$sql .= " ORDER BY s.subject_code ASC, u.first_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> Student-Subject Enrollment</h3>
        <p class="text-muted">Assign students to specific subject classes for separate attendance monitoring</p>
    </div>
    <div class="col-md-4 text-md-end">
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#enrollModal">
            <i class="fa-solid fa-user-plus me-1"></i> Enroll Student
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter by Subject -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="" method="GET" class="row g-3 align-items-center">
            <div class="col-md-10">
                <select name="subject_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Subjects (Show All Enrollments)</option>
                    <?php foreach ($subjectsList as $subj): ?>
                        <option value="<?= $subj['id'] ?>" <?= $subjectFilter == $subj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_code']) ?> - <?= htmlspecialchars($subj['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <a href="<?= baseUrl('admin/enrollment.php') ?>" class="btn btn-outline-secondary w-100">Reset Filter</a>
            </div>
        </form>
    </div>
</div>

<!-- Enrollments Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Student ID</th>
                        <th>Student Name</th>
                        <th>Course & Level</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Enrolled Date</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enrollments)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No student enrollment records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($enrollments as $row): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($row['stu_code']) ?></td>
                                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['course']) ?> - <?= htmlspecialchars($row['year_level']) ?> (<?= htmlspecialchars($row['section']) ?>)</td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['subject_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                <td><small class="text-muted"><?= date('M d, Y', strtotime($row['enrolled_at'])) ?></small></td>
                                <td class="text-end pe-3">
                                    <a href="?drop_id=<?= $row['enrollment_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove student from this subject enrollment?')" title="Drop Enrollment">
                                        <i class="fa-solid fa-user-minus"></i> Remove
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

<!-- Enroll Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action_enroll_student" value="1">

                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-user-plus me-2"></i> Enroll Student to Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Select Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($studentsList as $stu): ?>
                                <option value="<?= $stu['id'] ?>">
                                    <?= htmlspecialchars($stu['student_id']) ?> - <?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?> (<?= htmlspecialchars($stu['course']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Select Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- Choose Subject --</option>
                            <?php foreach ($subjectsList as $subj): ?>
                                <option value="<?= $subj['id'] ?>">
                                    <?= htmlspecialchars($subj['subject_code']) ?> - <?= htmlspecialchars($subj['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Enrollment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
