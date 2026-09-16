<?php
// api/approve_signout.php
header("Content-Type: application/json");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/auth.php";

if (!isAdmin()) {
    echo json_encode(["success" => false, "message" => "Unauthorized access. Administrator privileges required."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$attendanceId = intval($input["attendance_id"] ?? 0);
$action = strtolower(trim($input["action"] ?? ""));

if ($attendanceId <= 0 || !in_array($action, ["approve", "reject"])) {
    echo json_encode(["success" => false, "message" => "Invalid request parameters."]);
    exit();
}

$db = getDB();

// Fetch attendance and student details
$stmt = $db->prepare("
    SELECT a.*, u.first_name, u.last_name, u.student_id as stu_code, s.subject_code, s.subject_name
    FROM attendance a
    JOIN students st ON a.student_id = st.id
    JOIN users u ON st.user_id = u.id
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.id = ?
");
$stmt->execute([$attendanceId]);
$record = $stmt->fetch();

if (!$record) {
    echo json_encode(["success" => false, "message" => "Attendance record not found."]);
    exit();
}

try {
    if ($action === "approve") {
        $stmt = $db->prepare("UPDATE attendance SET signout_status = \"approved\", updated_at = NOW() WHERE id = ?");
        $stmt->execute([$attendanceId]);

        $studentName = htmlspecialchars($record["first_name"] . " " . $record["last_name"]);
        $subjectCode = htmlspecialchars($record["subject_code"]);

        logAudit("Sign Out Approved", "Admin approved sign out for {$studentName} ({$record["stu_code"]}) in {$subjectCode}.");

        echo json_encode([
            "success" => true,
            "message" => "Sign-out approved for {$studentName} ({$subjectCode})!",
            "signout_status" => "approved",
            "attendance_id" => $attendanceId
        ]);
        exit();
    } else {
        // Reject sign-out: clear time_out and mark rejected so student session stays active
        $stmt = $db->prepare("UPDATE attendance SET signout_status = \"rejected\", time_out = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$attendanceId]);

        $studentName = htmlspecialchars($record["first_name"] . " " . $record["last_name"]);
        $subjectCode = htmlspecialchars($record["subject_code"]);

        logAudit("Sign Out Rejected", "Admin rejected sign out for {$studentName} ({$record["stu_code"]}) in {$subjectCode}.");

        echo json_encode([
            "success" => true,
            "message" => "Sign-out request rejected for {$studentName}. Student session remains active.",
            "signout_status" => "rejected",
            "attendance_id" => $attendanceId
        ]);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}

