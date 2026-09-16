<?php
// api/face_verify.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['face_descriptor'])) {
    echo json_encode(['success' => false, 'message' => 'No live face descriptor provided.']);
    exit();
}

$db = getDB();

// Determine student record
if (isStudent()) {
    $stmt = $db->prepare("SELECT s.id, s.student_id, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();
} else {
    $studentIdParam = $input['student_id'] ?? null;
    $stmt = $db->prepare("SELECT s.id, s.student_id, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? OR s.student_id = ?");
    $stmt->execute([$studentIdParam, $studentIdParam]);
    $student = $stmt->fetch();
}

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    exit();
}

$stmt = $db->prepare("SELECT face_encoding FROM face_records WHERE student_id = ?");
$stmt->execute([$student['id']]);
$faceRecord = $stmt->fetch();

if (!$faceRecord || empty($faceRecord['face_encoding'])) {
    echo json_encode(['success' => false, 'message' => 'Please register your face first before signing in.']);
    exit();
}

$registeredDescriptor = json_decode($faceRecord['face_encoding'], true);
$liveDescriptor = $input['face_descriptor'];

if (!is_array($registeredDescriptor) || !is_array($liveDescriptor) || count($registeredDescriptor) !== count($liveDescriptor)) {
    echo json_encode(['success' => false, 'message' => 'Invalid biometric face format.']);
    exit();
}

// Calculate Euclidean Distance
$sumSquare = 0.0;
for ($i = 0; $i < count($registeredDescriptor); $i++) {
    $diff = floatval($registeredDescriptor[$i]) - floatval($liveDescriptor[$i]);
    $sumSquare += $diff * $diff;
}
$distance = sqrt($sumSquare);

// Threshold: standard face-api.js euclidean distance match threshold is <= 0.50
$threshold = 0.55;

if ($distance <= $threshold) {
    echo json_encode([
        'success' => true,
        'verified' => true,
        'distance' => round($distance, 4),
        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
        'student_id' => $student['id'],
        'message' => 'Face verified successfully!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'verified' => false,
        'distance' => round($distance, 4),
        'message' => 'Face not recognized. Please try again.'
    ]);
}
?>
