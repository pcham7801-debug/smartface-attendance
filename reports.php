<?php
// admin/reports.php
include __DIR__ . '/header.php';

$reportType = $_GET['type'] ?? 'daily';
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$subjectFilter = intval($_GET['subject_id'] ?? 0);
$studentFilter = intval($_GET['student_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

// Build Query
$sql = "SELECT a.*, 
               u.student_id as stu_code, u.first_name, u.last_name, u.course, u.year_level, u.section,
               s.subject_code, s.subject_name
        FROM attendance a
        JOIN students st ON a.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN subjects s ON a.subject_id = s.id
        WHERE 1=1";

$params = [];

if ($reportType === 'daily' && !empty($dateFilter)) {
    $sql .= " AND a.attendance_date = ?";
    $params[] = $dateFilter;
} elseif ($reportType === 'weekly' && !empty($dateFilter)) {
    $sql .= " AND YEARWEEK(a.attendance_date, 1) = YEARWEEK(?, 1)";
    $params[] = $dateFilter;
} elseif ($reportType === 'monthly' && !empty($dateFilter)) {
    $sql .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
    $params[] = $dateFilter;
}

if ($subjectFilter > 0) {
    $sql .= " AND a.subject_id = ?";
    $params[] = $subjectFilter;
}

if ($studentFilter > 0) {
    $sql .= " AND a.student_id = ?";
    $params[] = $studentFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY a.attendance_date DESC, a.time_in DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reportData = $stmt->fetchAll();

// Dropdowns List
$studentsList = $db->query("SELECT s.id, u.student_id, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.first_name ASC")->fetchAll();
$subjectsList = $db->query("SELECT id, subject_code, subject_name FROM subjects WHERE status = 'active' ORDER BY subject_code ASC")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-file-invoice text-primary me-2"></i> Attendance Reports</h3>
        <p class="text-muted">Generate daily, weekly, monthly, subject-wise, or student-wise attendance reports</p>
    </div>
    <div class="col-md-4 text-md-end d-flex justify-content-end gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary fw-bold">
            <i class="fa-solid fa-print me-1"></i> Print / PDF
        </button>
        <a href="<?= baseUrl('api/export_report.php?') . http_build_query($_GET) ?>" class="btn btn-success fw-bold">
            <i class="fa-solid fa-file-csv me-1"></i> Export to CSV
        </a>
    </div>
</div>

<!-- Report Generator Form Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Report Type</label>
                <select name="type" class="form-select">
                    <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily Report</option>
                    <option value="weekly" <?= $reportType === 'weekly' ? 'selected' : '' ?>>Weekly Report</option>
                    <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Report</option>
                    <option value="subject" <?= $reportType === 'subject' ? 'selected' : '' ?>>Subject Report</option>
                    <option value="student" <?= $reportType === 'student' ? 'selected' : '' ?>>Student History Report</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Select Date</label>
                <input type="date" name="date" class="form-control" value="<?= e($dateFilter) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Filter Subject</label>
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
                <label class="form-label fw-bold">Filter Student</label>
                <select name="student_id" class="form-select">
                    <option value="0">All Students</option>
                    <?php foreach ($studentsList as $stu): ?>
                        <option value="<?= $stu['id'] ?>" <?= $studentFilter == $stu['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stu['student_id']) ?> - <?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-chart-line me-1"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Generated Report Table -->
<div class="card border-0 shadow-sm rounded-4" id="printableReport">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-table me-2 text-primary"></i> 
            Attendance Summary Report (<?= strtoupper($reportType) ?>)
        </h5>
        <span class="badge bg-light text-dark border">Total Records: <?= count($reportData) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Student ID</th>
                        <th>Student Name</th>
                        <th>Course & Section</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No attendance data found for the selected report filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($row['stu_code']) ?></td>
                                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['course']) ?> - <?= htmlspecialchars($row['year_level']) ?> (<?= htmlspecialchars($row['section']) ?>)</td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['subject_code']) ?></span></td>
                                <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($row['attendance_date'])) ?></td>
                                <td><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                                <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : 'N/A' ?></td>
                                <td>
                                    <span class="badge <?= $row['status'] === 'Present' ? 'badge-present' : ($row['status'] === 'Late' ? 'badge-late' : 'badge-absent') ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($row['verification_method']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    #sidebar, .top-navbar, .card:first-of-type, button, .btn {
        display: none !important;
    }
    #content {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    #printableReport {
        box-shadow: none !important;
        border: none !important;
    }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
