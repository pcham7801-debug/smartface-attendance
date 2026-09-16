<?php
// student/profile.php
include __DIR__ . '/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Session expired. Please refresh and try again.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');

        if (empty($firstName) || empty($lastName)) {
            $error = 'First name and Last name are required.';
        } else {
            $profilePic = $studentData['profile_picture'];

            if (!empty($_FILES['profile_picture']['name'])) {
                $fileName = $_FILES['profile_picture']['name'];
                $fileTmp = $_FILES['profile_picture']['tmp_name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($fileExt, $allowed)) {
                    $newFileName = 'profile_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                    $uploadDir = __DIR__ . '/../uploads/profiles/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                        $profilePic = $newFileName;
                    }
                }
            }

            try {
                $stmt = $db->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, profile_picture = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$firstName, $middleName, $lastName, $contactNumber, $profilePic, $_SESSION['user_id']]);

                setFlash('success', 'Profile updated successfully!');
                header('Location: ' . baseUrl('student/profile.php'));
                exit();
            } catch (Exception $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Session expired. Please try again.';
    } else {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($oldPassword, $studentData['password'])) {
            $error = 'Incorrect current password.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed, $_SESSION['user_id']]);

            logAudit('Password Changed', "Student {$studentData['username']} changed their password.");
            setFlash('success', 'Password updated successfully!');
            header('Location: ' . baseUrl('student/profile.php'));
            exit();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-user-gear text-primary me-2"></i> My Profile</h3>
        <p class="text-muted">Manage your personal details and facial recognition data</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Avatar & Overview -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 text-center p-4">
            <div class="position-relative d-inline-block mx-auto mb-3">
                <?php $avatarUrl = baseUrl('uploads/profiles/' . ($studentData['profile_picture'] ?? 'default_avatar.png')); ?>
                <img src="<?= $avatarUrl ?>" alt="Avatar" class="avatar-lg" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($studentData['first_name']) ?>&background=0D8ABC&color=fff'">
            </div>
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($studentData['first_name'] . ' ' . $studentData['last_name']) ?></h5>
            <p class="text-muted mb-3"><?= htmlspecialchars($studentData['student_id']) ?></p>

            <ul class="list-group list-group-flush text-start small mb-3">
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Course:</span>
                    <span class="fw-bold"><?= htmlspecialchars($studentData['course']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Year & Section:</span>
                    <span class="fw-bold"><?= htmlspecialchars($studentData['year_level']) ?> - <?= htmlspecialchars($studentData['section']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Email:</span>
                    <span class="fw-bold"><?= htmlspecialchars($studentData['email']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Face Registration:</span>
                    <?php if ($studentData['face_registered']): ?>
                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Registered</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Not Registered</span>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>

    <!-- Right Column: Profile Edit & Face Camera -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Personal Details</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= e($studentData['first_name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" value="<?= e($studentData['middle_name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= e($studentData['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" value="<?= e($studentData['contact_number']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Change Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Profile Changes</button>
                </form>
            </div>
        </div>

        <!-- Face Registration Section -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" id="face-registration">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-camera text-primary me-2"></i> Biometric Face Registration</h5>
            </div>
            <div class="card-body text-center p-4">
                <p class="text-muted">Position your face in front of the camera to update your biometric recognition template.</p>
                
                <div class="camera-container mb-3" style="max-width: 480px; display: none;" id="profCameraBox">
                    <video id="profVideo" autoplay playsinline muted></video>
                    <canvas id="profCanvas"></canvas>
                    <div id="profFaceBadge" class="face-status-badge bg-info text-white">Initializing Camera...</div>
                </div>

                <div>
                    <button type="button" class="btn btn-outline-primary fw-semibold px-4 py-2" id="btnUpdateFace">
                        <i class="fa-solid fa-camera me-2"></i> <?= $studentData['face_registered'] ? 'Re-register / Update Face' : 'Register My Face' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-key text-primary me-2"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="change_password" value="1">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="old_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-secondary fw-semibold">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnUpdateFace = document.getElementById('btnUpdateFace');
    const cameraBox = document.getElementById('profCameraBox');
    const video = document.getElementById('profVideo');
    const canvas = document.getElementById('profCanvas');
    const badge = document.getElementById('profFaceBadge');

    let cameraHelper = null;
    let faceEngine = null;

    btnUpdateFace.addEventListener('click', async () => {
        cameraBox.style.display = 'block';
        btnUpdateFace.disabled = true;
        btnUpdateFace.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Scanning Face...';

        try {
            cameraHelper = new CameraHelper(video, canvas);
            await cameraHelper.startCamera();

            faceEngine = new FaceRecognitionEngine(video, canvas, badge);
            const descriptor = await faceEngine.captureFaceDescriptor();

            // Submit descriptor via API
            const response = await fetch('<?= baseUrl("api/face_register.php") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: <?= $studentData['student_db_id'] ?>,
                    face_descriptor: descriptor
                })
            });

            const result = await response.json();
            if (result.success) {
                alert('Face data registered successfully!');
                window.location.reload();
            } else {
                alert(result.message || 'Face registration failed.');
            }

        } catch (err) {
            alert(err.message || 'Face scan error.');
        } finally {
            if (cameraHelper) cameraHelper.stopCamera();
            cameraBox.style.display = 'none';
            btnUpdateFace.disabled = false;
            btnUpdateFace.innerHTML = '<i class="fa-solid fa-camera me-2"></i> Re-register Face';
        }
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
