<?php
// admin/students.php
include __DIR__ . '/header.php';

$error = '';
$success = '';

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_student'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security token invalid.';
    } else {
        $studentId = trim($_POST['student_id'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? 'student123';
        $course = trim($_POST['course'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');

        if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email) || empty($username)) {
            $error = 'Please complete all required fields.';
        } else {
            // Check duplicates
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR student_id = ?");
            $stmt->execute([$username, $email, $studentId]);
            if ($stmt->fetch()) {
                $error = 'Student ID, Email, or Username already exists.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                try {
                    $db->beginTransaction();
                    $stmt = $db->prepare("INSERT INTO users (student_id, first_name, middle_name, last_name, email, username, password, role, course, year_level, section, contact_number, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', ?, ?, ?, ?, 'active', NOW())");
                    $stmt->execute([$studentId, $firstName, $middleName, $lastName, $email, $username, $hashedPassword, $course, $yearLevel, $section, $contactNumber]);
                    $userId = $db->lastInsertId();

                    $stmt = $db->prepare("INSERT INTO students (user_id, student_id, course, year_level, section, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$userId, $studentId, $course, $yearLevel, $section]);

                    $db->commit();
                    logAudit('Admin Add Student', "Admin added new student: {$firstName} {$lastName} ({$studentId})");
                    setFlash('success', 'Student account created successfully!');
                    header('Location: ' . baseUrl('admin/students.php'));
                    exit();
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error creating student: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle Delete Student
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$deleteId]);
        logAudit('Admin Delete Student', "Admin deleted student user ID #{$deleteId}");
        setFlash('success', 'Student record deleted successfully.');
        header('Location: ' . baseUrl('admin/students.php'));
        exit();
    } catch (Exception $e) {
        $error = 'Failed to delete student: ' . $e->getMessage();
    }
}

// Handle Status Toggle (Activate/Deactivate)
if (isset($_GET['toggle_status_id'])) {
    $toggleId = intval($_GET['toggle_status_id']);
    $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$toggleId]);
    $u = $stmt->fetch();
    if ($u) {
        $newStatus = $u['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $toggleId]);
        logAudit('Admin Toggle Status', "Admin changed status of user #{$toggleId} to {$newStatus}");
        setFlash('success', "Student status updated to {$newStatus}.");
        header('Location: ' . baseUrl('admin/students.php'));
        exit();
    }
}

// Handle Reset Password
if (isset($_POST['action_reset_password'])) {
    $resetUserId = intval($_POST['reset_user_id']);
    $newPass = $_POST['new_password'];
    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $resetUserId]);
    logAudit('Admin Reset Password', "Admin reset password for user #{$resetUserId}");
    setFlash('success', 'Password reset successfully!');
    header('Location: ' . baseUrl('admin/students.php'));
    exit();
}

// Handle Quick Subject Enrollment from Student List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_quick_enroll'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security token invalid.';
    } else {
        $studentDbId = intval($_POST['enroll_student_id'] ?? 0);
        $subjectIds = $_POST['subject_ids'] ?? [];

        if ($studentDbId <= 0) {
            $error = 'Invalid student record.';
        } else {
            try {
                $db->beginTransaction();
                // Clear existing enrollments for clean update
                $stmtDel = $db->prepare("DELETE FROM student_subjects WHERE student_id = ?");
                $stmtDel->execute([$studentDbId]);

                if (!empty($subjectIds)) {
                    $stmtIns = $db->prepare("INSERT INTO student_subjects (student_id, subject_id, enrollment_status) VALUES (?, ?, 'enrolled')");
                    foreach ($subjectIds as $sId) {
                        $stmtIns->execute([$studentDbId, intval($sId)]);
                    }
                }
                $db->commit();
                logAudit('Admin Assign Subjects', "Updated enrolled subjects for student ID #{$studentDbId} (" . count($subjectIds) . " subjects)");
                setFlash('success', 'Enrolled subjects updated successfully for student!');
                header('Location: ' . baseUrl('admin/students.php'));
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to update subject enrollment: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all active subjects for quick assignment
$allSubjectsStmt = $db->query("SELECT id, subject_code, subject_name, day, start_time, end_time, room, instructor FROM subjects WHERE status = 'active' ORDER BY subject_code ASC");
$allActiveSubjects = $allSubjectsStmt->fetchAll();

// Map enrolled subjects per student
$studentEnrollments = [];
$enrStmt = $db->query("SELECT student_id, subject_id FROM student_subjects WHERE enrollment_status = 'enrolled'");
while ($enrRow = $enrStmt->fetch()) {
    $studentEnrollments[$enrRow['student_id']][] = intval($enrRow['subject_id']);
}


// Fetch Filters & Students List
$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');

$sql = "SELECT u.*, s.id as student_db_id, s.face_registered
        FROM users u 
        JOIN students s ON u.id = s.user_id 
        WHERE u.role = 'student'";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($courseFilter)) {
    $sql .= " AND u.course = ?";
    $params[] = $courseFilter;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-user-graduate text-primary me-2"></i> Student Management</h3>
        <p class="text-muted">Register, manage profiles, enrollments, and face biometric records</p>
    </div>
    <div class="col-md-4 text-md-end">
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fa-solid fa-plus me-1"></i> Add New Student
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search & Filter Form -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" placeholder="Search by Student ID, Name, or Email..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="course" class="form-control" placeholder="Filter by Course (e.g. BSIT)" value="<?= e($courseFilter) ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-search"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Student Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Student ID</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Year & Section</th>
                        <th>Face Registered</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No students found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $stu): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($stu['student_id']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= baseUrl('uploads/profiles/' . ($stu['profile_picture'] ?? 'default_avatar.png')) ?>" alt="Avatar" class="avatar-sm me-2" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($stu['first_name']) ?>&background=0D8ABC&color=fff'">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($stu['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($stu['course']) ?></span></td>
                                <td><?= htmlspecialchars($stu['year_level']) ?> - <?= htmlspecialchars($stu['section']) ?></td>
                                <td>
                                    <?php if ($stu['face_registered']): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Registered</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?toggle_status_id=<?= $stu['id'] ?>" class="badge text-decoration-none <?= $stu['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>" onclick="return confirm('Toggle status for this account?')">
                                        <?= ucfirst($stu['status']) ?>
                                    </a>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= baseUrl('admin/student_view.php?id=' . $stu['student_db_id']) ?>" class="btn btn-sm btn-outline-info" title="View Profile">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#enrollModal<?= $stu['student_db_id'] ?>" title="Assign Subjects">
                                        <i class="fa-solid fa-book-medical"></i>
                                    </button>
                                    <a href="<?= baseUrl('admin/face_registration.php?student_id=' . $stu['student_db_id']) ?>" class="btn btn-sm btn-outline-primary" title="Register Face">
                                        <i class="fa-solid fa-camera"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetModal<?= $stu['id'] ?>" title="Reset Password">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <a href="?delete_id=<?= $stu['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this student account? All logs and enrollment records will be removed.')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Quick Enroll Subjects Modal -->
                                    <div class="modal fade text-start" id="enrollModal<?= $stu['student_db_id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="action_quick_enroll" value="1">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="enroll_student_id" value="<?= $stu['student_db_id'] ?>">
                                                    
                                                    <div class="modal-header border-bottom-0 pb-0">
                                                        <h5 class="modal-title fw-bold text-dark">
                                                            <i class="fa-solid fa-book-medical text-success me-2"></i> Assign Subjects
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body pt-2">
                                                        <div class="bg-light p-3 rounded-3 mb-3">
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></div>
                                                            <div class="small text-muted">Student ID: <strong><?= htmlspecialchars($stu['student_id']) ?></strong> | <?= htmlspecialchars($stu['course']) ?> - <?= htmlspecialchars($stu['year_level']) ?> (<?= htmlspecialchars($stu['section']) ?>)</div>
                                                        </div>

                                                        <label class="form-label small fw-bold text-dark mb-2">Select Subjects to Enroll:</label>
                                                        <?php if (empty($allActiveSubjects)): ?>
                                                            <div class="alert alert-warning py-2 small">No active subjects available. Please create subjects first in Subject Management.</div>
                                                        <?php else: ?>
                                                            <div class="list-group list-group-flush border rounded-3 p-2" style="max-height: 240px; overflow-y: auto;">
                                                                <?php 
                                                                $currentlyEnrolled = $studentEnrollments[$stu['student_db_id']] ?? [];
                                                                foreach ($allActiveSubjects as $sub): 
                                                                    $isEnrolled = in_array(intval($sub['id']), $currentlyEnrolled);
                                                                ?>
                                                                    <label class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 rounded-2 py-2">
                                                                        <input class="form-check-input flex-shrink-0" type="checkbox" name="subject_ids[]" value="<?= $sub['id'] ?>" <?= $isEnrolled ? 'checked' : '' ?>>
                                                                        <div class="w-100">
                                                                            <div class="fw-bold text-dark small"><?= htmlspecialchars($sub['subject_code']) ?> - <?= htmlspecialchars($sub['subject_name']) ?></div>
                                                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                                                <i class="fa-solid fa-calendar-day me-1"></i> <?= htmlspecialchars($sub['day']) ?> 
                                                                                (<?= date('h:i A', strtotime($sub['start_time'])) ?> - <?= date('h:i A', strtotime($sub['end_time'])) ?>) 
                                                                                | Room: <?= htmlspecialchars($sub['room']) ?>
                                                                            </div>
                                                                        </div>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer border-top-0 pt-0">
                                                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success btn-sm fw-bold px-3">
                                                            <i class="fa-solid fa-check me-1"></i> Save Enrollment
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Reset Password Modal -->
                                    <div class="modal fade text-start" id="resetModal<?= $stu['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                            <div class="modal-content rounded-4 border-0">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="action_reset_password" value="1">
                                                    <input type="hidden" name="reset_user_id" value="<?= $stu['id'] ?>">
                                                    <div class="modal-header border-0">
                                                        <h6 class="modal-title fw-bold"><i class="fa-solid fa-key text-warning me-2"></i> Reset Password</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body py-0">
                                                        <p class="small text-muted mb-2">Reset password for <strong><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></strong>:</p>
                                                        <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="submit" class="btn btn-warning btn-sm fw-bold">Update Password</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
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

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action_add_student" value="1">

                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-user-plus me-2"></i> Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Student ID <span class="text-danger">*</span></label>
                            <input type="text" name="student_id" class="form-control" placeholder="2026-0000" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" placeholder="Middle Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="email@school.edu" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="09123456789">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course <span class="text-danger">*</span></label>
                            <input type="text" name="course" class="form-control" placeholder="BSIT, BSCS" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select name="year_level" class="form-select" required>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year" selected>3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Section <span class="text-danger">*</span></label>
                            <input type="text" name="section" class="form-control" placeholder="Section A" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="Username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default Password <span class="text-danger">*</span></label>
                            <input type="text" name="password" class="form-control" value="student123" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
