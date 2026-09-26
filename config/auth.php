<?php
// config/auth.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isAdmin() {
    return isLoggedIn() && getUserRole() === 'admin';
}

function isStudent() {
    return isLoggedIn() && getUserRole() === 'student';
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('danger', 'Please log in to access this page.');
        header('Location: ' . baseUrl('auth/login.php'));
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'Unauthorized access! Administrator rights required.');
        header('Location: ' . baseUrl('student/dashboard.php'));
        exit();
    }
}

function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        setFlash('danger', 'Unauthorized access! Student area only.');
        header('Location: ' . baseUrl('admin/dashboard.php'));
        exit();
    }
}

// Log actions to audit_logs
function logAudit($action, $description = '') {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $description, $ip]);
    } catch (Exception $e) {
        // Silently handle audit log errors
    }
}

/**
 * Automatically records attendance for a student upon login for their enrolled subject(s).
 * Guarantees existing attendance records for today are NEVER removed, deleted, or overwritten.
 * 
 * @param int $userId The users.id of the logged-in student
 * @param string $verificationMethod 'Face Login' | 'User Login' | 'Student Portal'
 * @return array Array of recorded attendance results
 */
function recordStudentLoginAttendance($userId, $verificationMethod = 'Face Login') {
    $results = [];
    try {
        $db = getDB();

        // 1. Get student profile ID
        $stmt = $db->prepare("SELECT id, student_id, user_id FROM students WHERE user_id = ?");
        $stmt->execute([$userId]);
        $student = $stmt->fetch();
        if (!$student) {
            return $results;
        }

        $studentId = $student['id'];
        $today = date('Y-m-d');
        $nowTimeStr = date('H:i:s');
        $currentDayName = date('l'); // e.g. "Monday", "Saturday"

        // 2. Fetch all active enrolled subjects for this student
        $stmt = $db->prepare("
            SELECT s.id as subject_id, s.subject_code, s.subject_name, s.day, s.start_time, s.end_time
            FROM student_subjects ss
            JOIN subjects s ON ss.subject_id = s.id
            WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled' AND s.status = 'active'
        ");
        $stmt->execute([$studentId]);
        $enrolledSubjects = $stmt->fetchAll();

        if (empty($enrolledSubjects)) {
            return $results;
        }

        $lateAfterMinutes = intval(getSetting('late_after_minutes', 10));

        // Filter subjects that match today's day of week, or fallback to all enrolled subjects
        $subjectsToRecord = [];
        foreach ($enrolledSubjects as $subj) {
            if (!empty($subj['day']) && stripos($subj['day'], $currentDayName) !== false) {
                $subjectsToRecord[] = $subj;
            }
        }
        // If no subjects specifically scheduled for today's day name, record for all enrolled subjects
        if (empty($subjectsToRecord)) {
            $subjectsToRecord = $enrolledSubjects;
        }

        foreach ($subjectsToRecord as $subj) {
            $subjectId = $subj['subject_id'];

            // 3. Check if an attendance record ALREADY exists for today
            $checkStmt = $db->prepare("SELECT id, status, time_in FROM attendance WHERE student_id = ? AND subject_id = ? AND attendance_date = ?");
            $checkStmt->execute([$studentId, $subjectId, $today]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                // An attendance record already exists for today!
                // CRUCIAL: NEVER remove, overwrite, or delete existing records.
                $results[] = [
                    'subject_code' => $subj['subject_code'],
                    'status' => $existing['status'],
                    'action' => 'preserved'
                ];
                continue;
            }

            // 4. Calculate on-time vs late status
            $status = 'Present';
            if (!empty($subj['start_time'])) {
                $startTimeObj = new DateTime($today . ' ' . $subj['start_time']);
                $lateThreshold = (clone $startTimeObj)->modify("+{$lateAfterMinutes} minutes");
                $nowObj = new DateTime();

                if ($nowObj > $lateThreshold && $nowObj > $startTimeObj) {
                    $status = 'Late';
                }
            }

            // 5. Insert new permanent attendance record
            $remarks = "Present via {$verificationMethod} on {$currentDayName}";
            $insStmt = $db->prepare("
                INSERT INTO attendance (student_id, subject_id, attendance_date, time_in, status, verification_method, remarks, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insStmt->execute([$studentId, $subjectId, $today, $nowTimeStr, $status, $verificationMethod, $remarks]);

            logAudit('Attendance Recorded', "Auto-recorded {$status} attendance for Student #{$student['student_id']} in {$subj['subject_code']} on {$currentDayName} ({$today}) via {$verificationMethod}.");

            $results[] = [
                'subject_code' => $subj['subject_code'],
                'status' => $status,
                'action' => 'recorded'
            ];
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error in recordStudentLoginAttendance: " . $e->getMessage());
        return $results;
    }
}
?>
