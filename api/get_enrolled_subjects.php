<?php
// api/get_enrolled_subjects.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();

if (isStudent()) {
    $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $studentRow = $stmt->fetch();
    $studentId = $studentRow['id'] ?? null;
} else {
    $studentId = intval($_GET['student_id'] ?? 0);
}

if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit();
}

$today = date('Y-m-d');

$query = "SELECT s.id, s.subject_code, s.subject_name, s.day, s.start_time, s.end_time, s.room, s.instructor,
          a.time_in, a.time_out, a.status as attendance_status
          FROM subjects s
          JOIN student_subjects ss ON s.id = ss.subject_id
          LEFT JOIN attendance a ON a.subject_id = s.id AND a.student_id = ss.student_id AND a.attendance_date = ?
          WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled' AND s.status = 'active'
          ORDER BY s.subject_code ASC";

$stmt = $db->prepare($query);
$stmt->execute([$today, $studentId]);
$subjects = $stmt->fetchAll();

foreach ($subjects as &$subj) {
    $subj['formatted_start'] = date('h:i A', strtotime($subj['start_time']));
    $subj['formatted_end'] = date('h:i A', strtotime($subj['end_time']));
    $subj['formatted_time_in'] = $subj['time_in'] ? date('h:i A', strtotime($subj['time_in'])) : null;
    $subj['formatted_time_out'] = $subj['time_out'] ? date('h:i A', strtotime($subj['time_out'])) : null;
}

echo json_encode(['success' => true, 'data' => $subjects]);
?>
