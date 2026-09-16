<?php
// admin/settings.php
include __DIR__ . '/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token.';
    } else {
        $schoolName         = trim($_POST['school_name'] ?? '');
        $lateAfterMinutes   = max(0, intval($_POST['late_after_minutes'] ?? 10));
        $absentAfterMinutes = max($lateAfterMinutes + 1, intval($_POST['absent_after_minutes'] ?? 30));
        $schoolYear         = trim($_POST['school_year'] ?? '');
        $semester           = trim($_POST['semester'] ?? '');

        try {
            $db->beginTransaction();

            $upsert = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

            $db->prepare($upsert)->execute(['school_name',          $schoolName]);
            $db->prepare($upsert)->execute(['late_after_minutes',   $lateAfterMinutes]);
            $db->prepare($upsert)->execute(['absent_after_minutes', $absentAfterMinutes]);
            $db->prepare($upsert)->execute(['school_year',          $schoolYear]);
            $db->prepare($upsert)->execute(['semester',             $semester]);

            $db->commit();
            logAudit('Update Settings', "Updated system configuration settings.");
            setFlash('success', 'System settings saved successfully!');
            header('Location: ' . baseUrl('admin/settings.php'));
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error saving settings: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_password'])) {
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!password_verify($oldPass, $adminUser['password'])) {
        $error = 'Current administrator password is incorrect.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New password confirmation does not match.';
    } elseif (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['user_id']]);

        logAudit('Admin Password Change', "Administrator password updated.");
        setFlash('success', 'Admin password changed successfully!');
        header('Location: ' . baseUrl('admin/settings.php'));
        exit();
    }
}

$schoolName         = getSetting('school_name',         'SmartFace Institute of Technology');
$lateAfterMinutes   = getSetting('late_after_minutes',   '10');
$absentAfterMinutes = getSetting('absent_after_minutes', '30');
$schoolYear         = getSetting('school_year',          '2026-2027');
$semester           = getSetting('semester',             '1st Semester');
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-gear text-primary me-2"></i> System Settings</h3>
        <p class="text-muted">Configure attendance rules, grace periods, institution details, and account security</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i> General Configuration</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="save_settings" value="1">

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">School / College Name</label>
                        <input type="text" name="school_name" class="form-control" value="<?= e($schoolName) ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mark as <span class="text-warning">Late</span> After (Minutes)</label>
                            <div class="input-group">
                                <input type="number" name="late_after_minutes" class="form-control" value="<?= e($lateAfterMinutes) ?>" min="1" max="59" required>
                                <span class="input-group-text">mins</span>
                            </div>
                            <small class="text-muted d-block mt-1">Students who sign in more than <strong><?= e($lateAfterMinutes) ?></strong> minute(s) after class start will be marked <strong class="text-warning">Late</strong>.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mark as <span class="text-danger">Absent</span> After (Minutes)</label>
                            <div class="input-group">
                                <input type="number" name="absent_after_minutes" class="form-control" value="<?= e($absentAfterMinutes) ?>" min="2" max="120" required>
                                <span class="input-group-text">mins</span>
                            </div>
                            <small class="text-muted d-block mt-1">Students who sign in more than <strong><?= e($absentAfterMinutes) ?></strong> minute(s) after class start will be <strong class="text-danger">automatically marked Absent</strong> and blocked from signing in.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Academic School Year</label>
                        <input type="text" name="school_year" class="form-control" value="<?= e($schoolYear) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label font-weight-bold">Current Semester</label>
                        <select name="semester" class="form-select">
                            <option value="1st Semester" <?= $semester === '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2nd Semester" <?= $semester === '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                            <option value="Summer Term"  <?= $semester === 'Summer Term'  ? 'selected' : '' ?>>Summer Term</option>
                        </select>
                    </div>

                    <div class="alert alert-info border-0 py-2 mb-3 small">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        <strong>Attendance Time Rules:</strong>
                        On time (0–<?= e($lateAfterMinutes) ?> min) → <span class="badge bg-success">Present</span>&nbsp;
                        <?= e($lateAfterMinutes) ?>–<?= e($absentAfterMinutes) ?> min late → <span class="badge bg-warning text-dark">Late</span>&nbsp;
                        After <?= e($absentAfterMinutes) ?> min → <span class="badge bg-danger">Absent</span> (login blocked)
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Configuration</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-lock text-primary me-2"></i> Change Admin Password</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="change_admin_password" value="1">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-warning fw-bold text-dark w-100">Update Admin Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
