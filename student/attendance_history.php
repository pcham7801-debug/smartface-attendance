<?php
// student/attendance_history.php
include __DIR__ . '/header.php';

$studentId = $studentData['student_db_id'];

$subjectFilter = $_GET['subject_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build Query
$sql = "SELECT a.*, s.subject_code, s.subject_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.student_id = ?";

$params = [$studentId];

if (!empty($subjectFilter)) {
    $sql .= " AND a.subject_id = ?";
    $params[] = $subjectFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFrom)) {
    $sql .= " AND a.attendance_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $sql .= " AND a.attendance_date <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY a.attendance_date DESC, a.time_in DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Fetch Enrolled Subjects for Filter Dropdown
$stmt = $db->prepare("SELECT s.id, s.subject_code, s.subject_name FROM subjects s JOIN student_subjects ss ON s.id = ss.subject_id WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled'");
$stmt->execute([$studentId]);
$enrolledSubjects = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Attendance History</h3>
        <p class="text-muted">Review your complete past attendance log</p>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($enrolledSubjects as $subj): ?>
                        <option value="<?= $subj['id'] ?>" <?= $subjectFilter == $subj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_code']) ?> - <?= htmlspecialchars($subj['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Present" <?= $statusFilter === 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Late" <?= $statusFilter === 'Late' ? 'selected' : '' ?>>Late</option>
                    <option value="Absent" <?= $statusFilter === 'Absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="Excused" <?= $statusFilter === 'Excused' ? 'selected' : '' ?>>Excused</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No attendance records found matching filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?= date('M d, Y', strtotime($row['attendance_date'])) ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['subject_code']) ?></td>
                                <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                <td><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                                <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '<span class="text-muted small">Active</span>' ?></td>
                                <td>
                                    <span class="badge <?= $row['status'] === 'Present' ? 'badge-present' : ($row['status'] === 'Late' ? 'badge-late' : 'badge-absent') ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><i class="fa-solid fa-camera me-1"></i> <?= htmlspecialchars($row['verification_method']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
