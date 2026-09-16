<?php
// api/attendance_in.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isStudent() && !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$subjectId = intval($input['subject_id'] ?? 0);
$verificationMethod = $input['verification_method'] ?? 'Face Recognition';

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

$stmt = $db->prepare("SELECT id FROM student_subjects WHERE student_id = ? AND subject_id = ? AND enrollment_status = 'enrolled'");
$stmt->execute([$studentId, $subjectId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You are not enrolled in this subject.']);
    exit();
}

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT id, time_in, time_out, status FROM attendance WHERE student_id = ? AND subject_id = ? AND attendance_date = ?");
$stmt->execute([$studentId, $subjectId, $today]);
$existingAtt = $stmt->fetch();

if ($existingAtt) {
    if ($existingAtt['status'] === 'Absent') {
        echo json_encode(['success' => false, 'status' => 'Absent', 'message' => 'You have been marked Absent for this subject today. The sign-in window has already closed.']);
        exit();
    } elseif (!empty($existingAtt['time_out'])) {
        echo json_encode(['success' => false, 'message' => "Today's attendance session for this subject has already been completed."]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'You have already signed in for this subject today.']);
        exit();
    }
}

$stmt = $db->prepare("SELECT subject_code, subject_name, start_time, end_time FROM subjects WHERE id = ?");
$stmt->execute([$subjectId]);
$subject = $stmt->fetch();

if (!$subject) {
    echo json_encode(['success' => false, 'message' => 'Subject details not found.']);
    exit();
}

// Configurable thresholds
// late_after_minutes   = mins after class start -> Late   (default: 10)
// absent_after_minutes = mins after class start -> Absent (default: 30)
$lateAfterMinutes   = intval(getSetting('late_after_minutes',   10));
$absentAfterMinutes = intval(getSetting('absent_after_minutes', 30));

$nowTimeStr      = date('H:i:s');
$startTimeStr    = $subject['start_time'];

$startTimeObj    = new DateTime($today . ' ' . $startTimeStr);
$lateThreshold   = (clone $startTimeObj)->modify("+{$lateAfterMinutes} minutes");
$absentThreshold = (clone $startTimeObj)->modify("+{$absentAfterMinutes} minutes");
$currentTimeObj  = new DateTime();

// Admin manual override
if (isAdmin() && !empty($input['status'])) {
    $status = $input['status'];
    $stmt = $db->prepare("INSERT INTO attendance (student_id, subject_id, attendance_date, time_in, status, verification_method, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$studentId, $subjectId, $today, $nowTimeStr, $status, $verificationMethod]);
    logAudit('Sign In Recorded', "Admin manual attendance for Student #{$studentId} in {$subject['subject_code']} - Status: {$status}");
    echo json_encode(['success' => true, 'message' => "Attendance Recorded! Marked {$status} for {$subject['subject_code']}.", 'status' => $status, 'time_in' => date('h:i A', strtotime($nowTimeStr)), 'subject' => $subject['subject_name']]);
    exit();
}

// Student time window check
if ($currentTimeObj >= $absentThreshold) {
    $minutesLate = (int) round(($currentTimeObj->getTimestamp() - $startTimeObj->getTimestamp()) / 60);
    $stmt = $db->prepare("INSERT INTO attendance (student_id, subject_id, attendance_date, time_in, status, verification_method, created_at) VALUES (?, ?, ?, ?, 'Absent', 'Auto-System', NOW())");
    $stmt->execute([$studentId, $subjectId, $today, $nowTimeStr]);
    logAudit('Auto Absent Recorded', "Student #{$studentId} arrived {$minutesLate} min late (>{$absentAfterMinutes} min cutoff) for {$subject['subject_code']}. Auto-marked Absent.");
    echo json_encode(['success' => false, 'status' => 'Absent', 'message' => "You arrived {$minutesLate} minutes after class started — past the {$absentAfterMinutes}-minute sign-in window. You have been automatically marked Absent for {$subject['subject_code']} today."]);
    exit();
} elseif ($currentTimeObj >= $lateThreshold) {
    $status = 'Late';
    $minutesLate = (int) round(($currentTimeObj->getTimestamp() - $startTimeObj->getTimestamp()) / 60);
    $statusMsg = "You are {$minutesLate} minutes late. Marked Late for {$subject['subject_code']}.";
} else {
    $status = 'Present';
    $statusMsg = "You are on time! Welcome to {$subject['subject_code']}.";
}

try {
    $stmt = $db->prepare("INSERT INTO attendance (student_id, subject_id, attendance_date, time_in, status, verification_method, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$studentId, $subjectId, $today, $nowTimeStr, $status, $verificationMethod]);
    logAudit('Sign In Recorded', "Attendance Sign In for Student #{$studentId} in {$subject['subject_code']} - Status: {$status}");
    echo json_encode(['success' => true, 'message' => "Attendance Recorded! {$statusMsg}", 'status' => $status, 'time_in' => date('h:i A', strtotime($nowTimeStr)), 'subject' => $subject['subject_name']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to record attendance: ' . $e->getMessage()]);
}
?>