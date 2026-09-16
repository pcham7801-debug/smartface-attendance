<?php
// api/attendance_out.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$subjectId = intval($input['subject_id'] ?? 0);
$reason = trim($input['reason'] ?? '');

if ($subjectId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid subject.']);
    exit();
}

$db = getDB();

if (isStudent()) {
    $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $studentRow = $stmt->fetch();
    $studentId = $studentRow['id'] ?? null;
} else {
    $studentId = intval($input['student_id'] ?? 0);
}

if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Student ID not found.']);
    exit();
}

$today = date('Y-m-d');

// Check active session today
$stmt = $db->prepare("SELECT a.id, a.time_in, a.time_out, a.signout_status, s.subject_code, s.subject_name 
                      FROM attendance a 
                      JOIN subjects s ON a.subject_id = s.id 
                      WHERE a.student_id = ? AND a.subject_id = ? AND a.attendance_date = ?");
$stmt->execute([$studentId, $subjectId, $today]);
$attendanceRecord = $stmt->fetch();

if (!$attendanceRecord) {
    echo json_encode(['success' => false, 'message' => 'No active sign-in record found for this subject today. Please sign in first.']);
    exit();
}

if ($attendanceRecord['signout_status'] === 'approved') {
    echo json_encode(['success' => false, 'message' => 'Your sign-out has already been approved and completed for this session.']);
    exit();
}

if ($attendanceRecord['signout_status'] === 'pending') {
    echo json_encode(['success' => false, 'message' => 'Your sign-out request is already pending approval from the administrator/instructor.']);
    exit();
}

$nowTimeStr = date('H:i:s');
$signoutStatus = isAdmin() ? 'approved' : 'pending';

try {
    $stmt = $db->prepare("UPDATE attendance SET time_out = ?, signout_status = ?, signout_reason = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$nowTimeStr, $signoutStatus, $reason, $attendanceRecord['id']]);

    if (isAdmin()) {
        logAudit('Admin Sign Out', "Admin signed out Student #{$studentId} in {$attendanceRecord['subject_code']} (Approved).");
        $msg = "Sign out successfully recorded for {$attendanceRecord['subject_code']}!";
    } else {
        logAudit('Sign Out Requested', "Student #{$studentId} requested Sign Out for {$attendanceRecord['subject_code']} (Pending Admin Approval).");
        $msg = "Sign out request submitted for {$attendanceRecord['subject_code']}! Awaiting administrator approval.";
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'signout_status' => $signoutStatus,
        'time_out' => date('h:i A', strtotime($nowTimeStr)),
        'subject' => $attendanceRecord['subject_name']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to process sign out: ' . $e->getMessage()]);
}
?>
