<?php
// auth/register.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl('student/dashboard.php'));
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please try again.';
    } else {
        $studentId = trim($_POST['student_id'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $course = trim($_POST['course'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $faceDescriptorRaw = $_POST['face_descriptor_raw'] ?? '';

        if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            $db = getDB();

            // Check if username, email, or student_id exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR student_id = ?");
            $stmt->execute([$username, $email, $studentId]);
            if ($stmt->fetch()) {
                $error = 'Student ID, Email, or Username already exists.';
            } else {
                // Process Profile Picture upload
                $profilePic = 'default_avatar.png';
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

                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $hasFace = !empty($faceDescriptorRaw) ? 1 : 0;

                try {
                    $db->beginTransaction();

                    // Insert into users
                    $stmt = $db->prepare("INSERT INTO users (student_id, first_name, middle_name, last_name, email, username, password, role, course, year_level, section, contact_number, profile_picture, face_registered, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, 'active', NOW())");
                    $stmt->execute([
                        $studentId, $firstName, $middleName, $lastName, $email, $username,
                        $hashedPassword, $course, $yearLevel, $section, $contactNumber, $profilePic, $hasFace
                    ]);
                    $userId = $db->lastInsertId();

                    // Insert into students
                    $stmt = $db->prepare("INSERT INTO students (user_id, student_id, course, year_level, section, face_registered, face_data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$userId, $studentId, $course, $yearLevel, $section, $hasFace, $faceDescriptorRaw]);
                    $studentTableId = $db->lastInsertId();

                    // Save face records if descriptor present
                    if ($hasFace && !empty($faceDescriptorRaw)) {
                        $stmt = $db->prepare("INSERT INTO face_records (student_id, face_encoding) VALUES (?, ?)");
                        $stmt->execute([$studentTableId, $faceDescriptorRaw]);
                    }

                    $db->commit();

                    logAudit('Student Registered', "New student registered: {$firstName} {$lastName} ({$studentId})");
                    setFlash('success', 'Registration successful! You can now log in to your account.');
                    header('Location: ' . baseUrl('auth/login.php'));
                    exit();

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - SmartFace Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= baseUrl('assets/css/style.css') ?>" rel="stylesheet">
    <!-- face-api.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
</head>
<body class="bg-light py-5">

<div class="container" style="max-width: 850px;">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Student Registration</h3>
        <p class="text-muted">Create your student profile and register your biometric face data</p>
    </div>

    <?php displayFlash(); ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data" id="registrationForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="face_descriptor_raw" id="face_descriptor_raw" value="">

                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-id-card me-2"></i> Personal Information</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Student ID <span class="text-danger">*</span></label>
                        <input type="text" name="student_id" class="form-control" placeholder="e.g. 2026-0001" required value="<?= e($_POST['student_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" placeholder="First Name" required value="<?= e($_POST['first_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" placeholder="Middle Name" value="<?= e($_POST['middle_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" placeholder="Last Name" required value="<?= e($_POST['last_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="student@school.edu" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" placeholder="09123456789" value="<?= e($_POST['contact_number'] ?? '') ?>">
                    </div>
                </div>

                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-graduation-cap me-2"></i> Academic Information</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Course / Program <span class="text-danger">*</span></label>
                        <input type="text" name="course" class="form-control" placeholder="e.g. BSIT, BSCS" required value="<?= e($_POST['course'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year Level <span class="text-danger">*</span></label>
                        <select name="year_level" class="form-select" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year" selected>3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Section <span class="text-danger">*</span></label>
                        <input type="text" name="section" class="form-control" placeholder="e.g. Section A" required value="<?= e($_POST['section'] ?? '') ?>">
                    </div>
                </div>

                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-lock me-2"></i> Account Credentials & Profile</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="Username" required value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_picture" class="form-control" accept="image/*">
                    </div>
                </div>

                <!-- Face Registration Section -->
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-camera me-2"></i> Biometric Face Registration</h5>
                <div class="card bg-light border-0 mb-4 p-3 text-center">
                    <p class="text-muted mb-2">Click <strong>"Register My Face"</strong> to initialize camera and scan face landmarks.</p>
                    
                    <div class="camera-container mb-3" style="max-width: 480px; display: none;" id="regCameraBox">
                        <video id="regVideo" autoplay playsinline muted></video>
                        <canvas id="regCanvas"></canvas>
                        <div id="regFaceBadge" class="face-status-badge bg-info text-white">Initializing Camera...</div>
                    </div>

                    <div>
                        <button type="button" class="btn btn-outline-primary fw-semibold" id="btnStartFaceScan">
                            <i class="fa-solid fa-camera me-2"></i> Register My Face
                        </button>
                        <span id="faceScanStatus" class="ms-2 badge bg-secondary">Face Not Captured</span>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= baseUrl('auth/login.php') ?>" class="text-secondary text-decoration-none">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold">
                        <i class="fa-solid fa-user-check me-2"></i> Complete Registration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= baseUrl('assets/js/camera.js') ?>"></script>
<script src="<?= baseUrl('assets/js/face-recognition.js') ?>"></script>
<script src="<?= baseUrl('assets/js/app.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnStart = document.getElementById('btnStartFaceScan');
    const cameraBox = document.getElementById('regCameraBox');
    const video = document.getElementById('regVideo');
    const canvas = document.getElementById('regCanvas');
    const badge = document.getElementById('regFaceBadge');
    const statusSpan = document.getElementById('faceScanStatus');
    const hiddenDescriptorInput = document.getElementById('face_descriptor_raw');

    let cameraHelper = null;
    let faceEngine = null;

    btnStart.addEventListener('click', async () => {
        cameraBox.style.display = 'block';
        btnStart.disabled = true;
        btnStart.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Capturing Face...';

        try {
            cameraHelper = new CameraHelper(video, canvas);
            await cameraHelper.startCamera();

            faceEngine = new FaceRecognitionEngine(video, canvas, badge);
            const descriptor = await faceEngine.captureFaceDescriptor();

            hiddenDescriptorInput.value = JSON.stringify(descriptor);
            statusSpan.className = 'ms-2 badge bg-success';
            statusSpan.innerHTML = '<i class="fa-solid fa-check me-1"></i> Face Captured Successfully!';
            btnStart.className = 'btn btn-success fw-semibold';
            btnStart.innerHTML = '<i class="fa-solid fa-check-circle me-2"></i> Face Captured';
            
            setTimeout(() => {
                cameraHelper.stopCamera();
                cameraBox.style.display = 'none';
            }, 2000);

        } catch (err) {
            alert(err.message || 'Face registration failed. Please try again.');
            btnStart.disabled = false;
            btnStart.innerHTML = '<i class="fa-solid fa-camera me-2"></i> Retry Register Face';
            if (cameraHelper) cameraHelper.stopCamera();
            cameraBox.style.display = 'none';
        }
    });
});
</script>
</body>
</html>
