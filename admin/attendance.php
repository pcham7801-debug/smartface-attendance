<?php
// admin/attendance.php
include __DIR__ . '/header.php';

$error = '';

// Handle Manual Attendance Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_manual_attendance'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid token.';
    } else {
        $studentId = intval($_POST['student_id'] ?? 0);
        $subjectId = intval($_POST['subject_id'] ?? 0);
        $date = $_POST['attendance_date'] ?? date('Y-m-d');
        $timeIn = $_POST['time_in'] ?? '08:00';
        $timeOut = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
        $status = $_POST['status'] ?? 'Present';
        $reason = trim($_POST['reason'] ?? '');

        if ($studentId <= 0 || $subjectId <= 0 || empty($reason)) {
            $error = 'Please fill in all required fields and provide a reason for manual entry.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO attendance (student_id, subject_id, attendance_date, time_in, time_out, status, verification_method, remarks, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, 'Manual Admin', ?, NOW())
                                      ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), time_out = VALUES(time_out), status = VALUES(status), verification_method = 'Manual Admin', remarks = VALUES(remarks)");
                $stmt->execute([$studentId, $subjectId, $date, $timeIn, $timeOut, $status, $reason]);

                logAudit('Manual Attendance Override', "Admin manually recorded attendance for Student ID #{$studentId}, Subject #{$subjectId}, Date: {$date}, Status: {$status}. Reason: {$reason}");
                setFlash('success', 'Manual attendance record added successfully!');
                if (!headers_sent()) {
                    header('Location: ' . baseUrl('admin/attendance.php'));
                }
                echo "<script>window.location.href='" . baseUrl('admin/attendance.php') . "';</script>";
                exit();
            } catch (Exception $e) {
                $error = 'Error saving attendance: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete Record
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);
    try {
        $stmt = $db->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$delId]);
        logAudit('Delete Attendance Record', "Admin deleted attendance log ID #{$delId}");
        setFlash('success', 'Attendance record deleted.');
        if (!headers_sent()) {
            header('Location: ' . baseUrl('admin/attendance.php'));
        }
        echo "<script>window.location.href='" . baseUrl('admin/attendance.php') . "';</script>";
        exit();
    } catch (Exception $e) {
        $error = 'Failed to delete record: ' . $e->getMessage();
    }
}

// Filters & Data Retrieval
$dateFilter = $_GET['date'] ?? '';
$subjectFilter = intval($_GET['subject_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');

$sql = "SELECT a.*, 
               u.student_id as stu_code, u.first_name, u.last_name, u.course, u.section,
               s.subject_code, s.subject_name
        FROM attendance a
        JOIN students st ON a.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN subjects s ON a.subject_id = s.id
        WHERE 1=1";

$params = [];

if (!empty($dateFilter)) {
    $sql .= " AND a.attendance_date = ?";
    $params[] = $dateFilter;
}

if ($subjectFilter > 0) {
    $sql .= " AND a.subject_id = ?";
    $params[] = $subjectFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchFilter)) {
    $sql .= " AND (u.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $term = "%{$searchFilter}%";
    $params = array_merge($params, [$term, $term, $term]);
}

$sql .= " ORDER BY a.attendance_date DESC, a.time_in DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$attendanceLogs = $stmt->fetchAll();

// Dropdowns List
$studentsList = $db->query("SELECT s.id, u.student_id, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.first_name ASC")->fetchAll();
$subjectsList = $db->query("SELECT id, subject_code, subject_name FROM subjects WHERE status = 'active' ORDER BY subject_code ASC")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-clipboard-user text-primary me-2"></i> Attendance Logs & Override</h3>
        <p class="text-muted">Monitor, search, filter, and manually adjust student attendance records</p>
    </div>
    <div class="col-md-4 text-md-end">
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#manualAttendanceModal">
            <i class="fa-solid fa-pen me-1"></i> Add Manual Attendance
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search Student Name / ID..." value="<?= e($searchFilter) ?>">
            </div>
            <div class="col-md-3">
                <select name="subject_id" class="form-select">
                    <option value="0">All Subjects</option>
                    <?php foreach ($subjectsList as $subj): ?>
                        <option value="<?= $subj['id'] ?>" <?= $subjectFilter == $subj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_code']) ?> - <?= htmlspecialchars($subj['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date" class="form-control" value="<?= e($dateFilter) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Present" <?= $statusFilter === 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Late" <?= $statusFilter === 'Late' ? 'selected' : '' ?>>Late</option>
                    <option value="Absent" <?= $statusFilter === 'Absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="Excused" <?= $statusFilter === 'Excused' ? 'selected' : '' ?>>Excused</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="<?= baseUrl('admin/attendance.php') ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Logs Table -->
<div class="card border-0 shadow-sm rounded-4">
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
                        <th>Time Out</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceLogs)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No attendance logs found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendanceLogs as $log): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($log['stu_code']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($log['course']) ?> - <?= htmlspecialchars($log['section']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['subject_code']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($log['attendance_date'])) ?></td>
                                <td><?= date('h:i A', strtotime($log['time_in'])) ?></td>
                                <td>
                                    <?php if ($log['signout_status'] === 'pending'): ?>
                                        <div class="fw-bold text-dark"><?= date('h:i A', strtotime($log['time_out'])) ?></div>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i> Pending Approval</span>
                                    <?php elseif ($log['signout_status'] === 'approved'): ?>
                                        <div class="fw-semibold text-dark"><?= date('h:i A', strtotime($log['time_out'])) ?></div>
                                        <span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i> Approved</span>
                                    <?php elseif ($log['signout_status'] === 'rejected'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-ban me-1"></i> Sign-Out Rejected</span>
                                    <?php elseif ($log['time_out']): ?>
                                        <?= date('h:i A', strtotime($log['time_out'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="fa-solid fa-circle text-success me-1" style="font-size: 8px;"></i> Active (In Class)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $log['status'] === 'Present' ? 'badge-present' : ($log['status'] === 'Late' ? 'badge-late' : 'badge-absent') ?>">
                                        <?= htmlspecialchars($log['status']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><i class="fa-solid fa-camera me-1"></i> <?= htmlspecialchars($log['verification_method']) ?></small></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group">
                                        <?php if ($log['signout_status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success fw-bold btn-quick-signout" data-id="<?= $log['id'] ?>" data-action="approve" title="Approve Sign Out">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning fw-bold btn-quick-signout" data-id="<?= $log['id'] ?>" data-action="reject" title="Reject Sign Out">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="?delete_id=<?= $log['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this attendance record?')" title="Delete Record">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
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

<!-- Manual Attendance Modal -->
<div class="modal fade" id="manualAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action_manual_attendance" value="1">

                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-pen me-2"></i> Add Manual Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($studentsList as $stu): ?>
                                <option value="<?= $stu['id'] ?>">
                                    <?= htmlspecialchars($stu['student_id']) ?> - <?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- Choose Subject --</option>
                            <?php foreach ($subjectsList as $subj): ?>
                                <option value="<?= $subj['id'] ?>">
                                    <?= htmlspecialchars($subj['subject_code']) ?> - <?= htmlspecialchars($subj['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Excused">Excused</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Time In <span class="text-danger">*</span></label>
                            <input type="time" name="time_in" class="form-control" value="08:00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Time Out</label>
                            <input type="time" name="time_out" class="form-control" value="10:00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Manual Adjustment <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Excused absence due to medical note, manual check-in by admin..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-quick-signout').forEach(btn => {
        btn.addEventListener('click', async () => {
            const attendanceId = btn.dataset.id;
            const action = btn.dataset.action;
            const actionLabel = action === 'approve' ? 'APPROVE' : 'REJECT';

            if (!confirm(`Are you sure you want to ${actionLabel} this sign-out request?`)) return;

            btn.disabled = true;
            try {
                const response = await fetch('<?= baseUrl("api/approve_signout.php") ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ attendance_id: attendanceId, action: action })
                });

                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    window.location.reload();
                } else {
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Connection error. Please try again.');
                btn.disabled = false;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
