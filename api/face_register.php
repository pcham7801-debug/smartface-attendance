<?php
// api/face_register.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['face_descriptor'])) {
    echo json_encode(['success' => false, 'message' => 'No face descriptor data received.']);
    exit();
}

$targetStudentId = $input['student_id'] ?? null;

$db = getDB();

// If current user is student, enforce self-registration
if (isStudent()) {
    $stmt = $db->prepare("SELECT id, student_id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $studentRow = $stmt->fetch();
    if (!$studentRow) {
        echo json_encode(['success' => false, 'message' => 'Student record not found.']);
        exit();
    }
    $studentDbId = $studentRow['id'];
} else if (isAdmin()) {
    if (!$targetStudentId) {
        echo json_encode(['success' => false, 'message' => 'Target student ID is required for admin face registration.']);
        exit();
    }
    $stmt = $db->prepare("SELECT id FROM students WHERE student_id = ? OR id = ?");
    $stmt->execute([$targetStudentId, $targetStudentId]);
    $studentRow = $stmt->fetch();
    if (!$studentRow) {
        echo json_encode(['success' => false, 'message' => 'Target student not found.']);
        exit();
    }
    $studentDbId = $studentRow['id'];
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized role.']);
    exit();
}

$descriptorJson = json_encode($input['face_descriptor']);

try {
    $db->beginTransaction();

    // Check if face record exists
    $stmt = $db->prepare("SELECT id FROM face_records WHERE student_id = ?");
    $stmt->execute([$studentDbId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("UPDATE face_records SET face_encoding = ?, updated_at = NOW() WHERE student_id = ?");
        $stmt->execute([$descriptorJson, $studentDbId]);
    } else {
        $stmt = $db->prepare("INSERT INTO face_records (student_id, face_encoding) VALUES (?, ?)");
        $stmt->execute([$studentDbId, $descriptorJson]);
    }

    // Update face_registered status in students and users tables
    $stmt = $db->prepare("UPDATE students SET face_registered = 1, face_data = ? WHERE id = ?");
    $stmt->execute([$descriptorJson, $studentDbId]);

    $stmt = $db->prepare("UPDATE users u JOIN students s ON u.id = s.user_id SET u.face_registered = 1 WHERE s.id = ?");
    $stmt->execute([$studentDbId]);

    $db->commit();

    logAudit('Face Registered', 'Biometric face descriptor registered for Student ID internal #' . $studentDbId);

    echo json_encode([
        'success' => true,
        'message' => 'Face registered successfully!'
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to save face data: ' . $e->getMessage()]);
}
?>
