<?php
// api/export_report.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireAdmin();

$reportType = $_GET['type'] ?? 'daily';
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$subjectFilter = $_GET['subject_id'] ?? '';
$studentFilter = $_GET['student_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$db = getDB();

$sql = "SELECT 
            u.student_id,
            CONCAT(u.first_name, ' ', IFNULL(CONCAT(u.middle_name, ' '), ''), u.last_name) as student_name,
            u.course,
            u.year_level,
            u.section,
            s.subject_code,
            s.subject_name,
            a.attendance_date,
            a.time_in,
            a.time_out,
            a.status,
            a.verification_method
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

if (!empty($subjectFilter)) {
    $sql .= " AND a.subject_id = ?";
    $params[] = $subjectFilter;
}

if (!empty($studentFilter)) {
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
$records = $stmt->fetchAll();

$filename = "attendance_report_" . $reportType . "_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, [
    'Student ID',
    'Student Name',
    'Course',
    'Year & Section',
    'Subject Code',
    'Subject Name',
    'Date',
    'Time In',
    'Time Out',
    'Status',
    'Verification Method'
]);

foreach ($records as $row) {
    fputcsv($output, [
        $row['student_id'],
        $row['student_name'],
        $row['course'],
        $row['year_level'] . ' - ' . $row['section'],
        $row['subject_code'],
        $row['subject_name'],
        $row['attendance_date'],
        $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : 'N/A',
        $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : 'N/A',
        $row['status'],
        $row['verification_method']
    ]);
}

fclose($output);
exit();
?>
