<?php
// admin/subjects.php
include __DIR__ . '/header.php';

$error = '';

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_subject'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid CSRF token.';
    } else {
        $code = trim($_POST['subject_code'] ?? '');
        $name = trim($_POST['subject_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $instructor = trim($_POST['instructor'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $day = trim($_POST['day'] ?? '');
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $semester = $_POST['semester'] ?? '1st Semester';
        $schoolYear = $_POST['school_year'] ?? '2026-2027';

        if (empty($code) || empty($name) || empty($instructor) || empty($room) || empty($startTime) || empty($endTime)) {
            $error = 'Please fill in all required subject details.';
        } else {
            $stmt = $db->prepare("SELECT id FROM subjects WHERE subject_code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $error = 'Subject Code already exists.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO subjects (subject_code, subject_name, description, instructor, room, day, start_time, end_time, semester, school_year, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                    $stmt->execute([$code, $name, $desc, $instructor, $room, $day, $startTime, $endTime, $semester, $schoolYear]);

                    logAudit('Add Subject', "Created new subject: {$code} - {$name}");
                    setFlash('success', 'Subject created successfully!');
                    header('Location: ' . baseUrl('admin/subjects.php'));
                    exit();
                } catch (Exception $e) {
                    $error = 'Error adding subject: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle Edit Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_subject'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid token.';
    } else {
        $id = intval($_POST['subject_id']);
        $code = trim($_POST['subject_code']);
        $name = trim($_POST['subject_name']);
        $desc = trim($_POST['description']);
        $instructor = trim($_POST['instructor']);
        $room = trim($_POST['room']);
        $day = trim($_POST['day']);
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $semester = $_POST['semester'];
        $schoolYear = $_POST['school_year'];
        $status = $_POST['status'];

        try {
            $stmt = $db->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, description = ?, instructor = ?, room = ?, day = ?, start_time = ?, end_time = ?, semester = ?, school_year = ?, status = ? WHERE id = ?");
            $stmt->execute([$code, $name, $desc, $instructor, $room, $day, $startTime, $endTime, $semester, $schoolYear, $status, $id]);

            logAudit('Edit Subject', "Updated subject #{$id}: {$code}");
            setFlash('success', 'Subject updated successfully!');
            header('Location: ' . baseUrl('admin/subjects.php'));
            exit();
        } catch (Exception $e) {
            $error = 'Error updating subject: ' . $e->getMessage();
        }
    }
}

// Handle Delete Subject
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    try {
        $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$deleteId]);
        logAudit('Delete Subject', "Deleted subject ID #{$deleteId}");
        setFlash('success', 'Subject deleted successfully.');
        header('Location: ' . baseUrl('admin/subjects.php'));
        exit();
    } catch (Exception $e) {
        $error = 'Cannot delete subject: ' . $e->getMessage();
    }
}

// Fetch Subjects
$stmt = $db->query("SELECT s.*, (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = s.id AND ss.enrollment_status = 'enrolled') as enrolled_count FROM subjects s ORDER BY s.subject_code ASC");
$subjects = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-book-open text-primary me-2"></i> Subject Management</h3>
        <p class="text-muted">Create subjects, assign instructors, set class schedules and rooms</p>
    </div>
    <div class="col-md-4 text-md-end">
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="fa-solid fa-plus me-1"></i> Add Subject
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Subjects Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Subject Code</th>
                        <th>Subject Name</th>
                        <th>Instructor</th>
                        <th>Room</th>
                        <th>Schedule</th>
                        <th>Enrolled</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No subjects added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $subj): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($subj['subject_code']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($subj['subject_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($subj['semester']) ?> (<?= htmlspecialchars($subj['school_year']) ?>)</small>
                                </td>
                                <td><?= htmlspecialchars($subj['instructor']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($subj['room']) ?></span></td>
                                <td>
                                    <small class="d-block fw-semibold"><?= htmlspecialchars($subj['day']) ?></small>
                                    <small class="text-muted"><?= date('h:i A', strtotime($subj['start_time'])) ?> - <?= date('h:i A', strtotime($subj['end_time'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info text-white"><i class="fa-solid fa-users me-1"></i> <?= $subj['enrolled_count'] ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $subj['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($subj['status']) ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $subj['id'] ?>" title="Edit Subject">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="?delete_id=<?= $subj['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this subject and all its attendance records?')" title="Delete Subject">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Subject Modal -->
                                    <div class="modal fade text-start" id="editModal<?= $subj['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content border-0 rounded-4">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="action_edit_subject" value="1">
                                                    <input type="hidden" name="subject_id" value="<?= $subj['id'] ?>">

                                                    <div class="modal-header border-0">
                                                        <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Subject</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Subject Code</label>
                                                                <input type="text" name="subject_code" class="form-control" value="<?= e($subj['subject_code']) ?>" required>
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label">Subject Name</label>
                                                                <input type="text" name="subject_name" class="form-control" value="<?= e($subj['subject_name']) ?>" required>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label">Description</label>
                                                                <textarea name="description" class="form-control" rows="2"><?= e($subj['description']) ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Instructor</label>
                                                                <input type="text" name="instructor" class="form-control" value="<?= e($subj['instructor']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Room</label>
                                                                <input type="text" name="room" class="form-control" value="<?= e($subj['room']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Day</label>
                                                                <select name="day" class="form-select">
                                                                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $d): ?>
                                                                        <option value="<?= $d ?>" <?= $subj['day'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Start Time</label>
                                                                <input type="time" name="start_time" class="form-control" value="<?= $subj['start_time'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">End Time</label>
                                                                <input type="time" name="end_time" class="form-control" value="<?= $subj['end_time'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Semester</label>
                                                                <input type="text" name="semester" class="form-control" value="<?= e($subj['semester']) ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">School Year</label>
                                                                <input type="text" name="school_year" class="form-control" value="<?= e($subj['school_year']) ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="active" <?= $subj['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="inactive" <?= $subj['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary fw-bold px-4">Update Subject</button>
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

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action_add_subject" value="1">

                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-plus me-2"></i> Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" name="subject_code" class="form-control" placeholder="e.g. IT101" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                            <input type="text" name="subject_name" class="form-control" placeholder="Intro to Information Technology" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Course overview..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instructor <span class="text-danger">*</span></label>
                            <input type="text" name="instructor" class="form-control" placeholder="Prof. Juan Instructor" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room <span class="text-danger">*</span></label>
                            <input type="text" name="room" class="form-control" placeholder="Laboratory 1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Day <span class="text-danger">*</span></label>
                            <select name="day" class="form-select" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" value="08:00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" value="10:00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Semester</label>
                            <input type="text" name="semester" class="form-control" value="1st Semester">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">School Year</label>
                            <input type="text" name="school_year" class="form-control" value="2026-2027">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
