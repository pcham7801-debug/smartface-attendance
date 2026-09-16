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
        $schoolName = trim($_POST['school_name'] ?? '');
        $gracePeriod = intval($_POST['grace_period_minutes'] ?? 15);
        $schoolYear = trim($_POST['school_year'] ?? '');
        $semester = trim($_POST['semester'] ?? '');

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('school_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$schoolName]);

            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('grace_period_minutes', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$gracePeriod]);

            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('school_year', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$schoolYear]);

            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('semester', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$semester]);

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

$schoolName = getSetting('school_name', 'SmartFace Institute of Technology');
$gracePeriod = getSetting('grace_period_minutes', '15');
$schoolYear = getSetting('school_year', '2026-2027');
$semester = getSetting('semester', '1st Semester');
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
                            <label class="form-label font-weight-bold">Attendance Grace Period (Minutes)</label>
                            <div class="input-group">
                                <input type="number" name="grace_period_minutes" class="form-control" value="<?= e($gracePeriod) ?>" min="0" max="60" required>
                                <span class="input-group-text">mins</span>
                            </div>
                            <small class="text-muted d-block mt-1">Students signing in after class start time + grace period will be marked <strong>Late</strong>.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold">Academic School Year</label>
                            <input type="text" name="school_year" class="form-control" value="<?= e($schoolYear) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label font-weight-bold">Current Semester</label>
                        <select name="semester" class="form-select">
                            <option value="1st Semester" <?= $semester === '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2nd Semester" <?= $semester === '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                            <option value="Summer Term" <?= $semester === 'Summer Term' ? 'selected' : '' ?>>Summer Term</option>
                        </select>
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
