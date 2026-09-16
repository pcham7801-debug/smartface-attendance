<?php
// auth/login.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . baseUrl('admin/dashboard.php'));
    } else {
        header('Location: ' . baseUrl('student/dashboard.php'));
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please try again.';
    } else {
        $loginInput = trim($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($loginInput) || empty($password)) {
            $error = 'Please enter your username/email and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['must_change_password'] = $user['must_change_password'];

                logAudit('User Login', "User {$user['username']} logged in successfully as {$user['role']}.");

                if ($user['role'] === 'admin') {
                    if ($user['must_change_password']) {
                        setFlash('warning', 'Security Alert: You are using a default password. Please update your password immediately in Settings.');
                    } else {
                        setFlash('success', 'Welcome back, Administrator!');
                    }
                    header('Location: ' . baseUrl('admin/dashboard.php'));
                } else {
                    setFlash('success', 'Welcome back, ' . htmlspecialchars($user['first_name']) . '!');
                    header('Location: ' . baseUrl('student/dashboard.php'));
                }
                exit();
            } else {
                $error = 'Invalid username/email or password.';
                logAudit('Failed Login Attempt', "Failed login attempt for input: {$loginInput}");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SmartFace">
    <link rel="manifest" href="<?= baseUrl('manifest.json') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= baseUrl('assets/icons/icon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= baseUrl('assets/icons/icon.svg') ?>">
    <title>Login - SmartFace Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= baseUrl('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center py-5">

<div class="container" style="max-width: 450px;">
    <div class="text-center mb-4">
        <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 70px; height: 70px;">
            <i class="fa-solid fa-user-check fa-2x"></i>
        </div>
        <h3 class="fw-bold text-dark">SmartFace Attendance</h3>
        <p class="text-muted">Biometric Attendance Monitoring System</p>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <div class="card-body">
            <h5 class="fw-bold mb-4 text-center">Account Sign In</h5>

            <?php displayFlash(); ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form id="loginForm" onsubmit="event.preventDefault(); const m = new bootstrap.Modal(document.getElementById('faceLoginModal')); m.show();">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="mb-3">
                    <label for="username_email" class="form-label fw-bold">Username or Student ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" name="username_email" id="username_email" class="form-control" placeholder="Enter username or student ID" required autofocus>
                    </div>
                    <div class="form-text small text-muted">Only the registered student of this account can be scanned and logged in.</div>
                </div>

                <!-- Face Login Button -->
                <button type="button" class="btn btn-primary w-100 py-2 fw-semibold rounded-3 mb-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#faceLoginModal">
                    <i class="fa-solid fa-camera me-2"></i> Scan Face to Login
                </button>
            </form>

                <!-- Face Login Modal -->
                <div class="modal fade" id="faceLoginModal" tabindex="-1" aria-labelledby="faceLoginModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content rounded-4 border-0 shadow">
                      <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold" id="faceLoginModalLabel"><i class="fa-solid fa-id-card-clip text-success me-2"></i> Instant Face Sign In</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body text-center pt-2">
                        <div class="mb-3 text-start">
                          <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fa-solid fa-user-check text-primary me-1"></i> Account (Username, Email, or Student ID):
                          </label>
                          <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" id="modal_username_email" class="form-control border-start-0 ps-1" placeholder="e.g. ewwin09 or 24-104174">
                          </div>
                          <div class="form-text small text-muted">Only the face registered to this account will be permitted to sign in.</div>
                        </div>
                        
                        <div class="position-relative d-inline-block w-100 rounded-3 overflow-hidden shadow-sm bg-dark" style="max-height: 320px;">
                          <video id="videoInput" autoplay muted playsinline style="width: 100%; height: 280px; object-fit: cover; transform: scaleX(-1);"></video>
                          
                          <!-- Biometric Scanner Overlay -->
                          <div id="scanOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center" style="pointer-events: none;">
                            <div id="scanTargetBox" class="border border-2 border-primary rounded-4" style="width: 180px; height: 210px; box-shadow: 0 0 15px rgba(13, 110, 253, 0.4); transition: all 0.3s ease;"></div>
                          </div>
                        </div>

                        <div id="faceLoginMessage" class="mt-3"></div>

                        <div class="mt-2 d-flex justify-content-center gap-2">
                          <button id="captureBtn" class="btn btn-sm btn-outline-primary px-3 rounded-pill">
                            <i class="fa-solid fa-camera me-1"></i> Manual Capture
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

            <hr class="my-4">

            <div class="text-center">
                <p class="mb-2 text-muted">New student? <a href="<?= baseUrl('auth/register.php') ?>" class="text-primary font-weight-bold text-decoration-none">Register Account</a></p>
                <a href="<?= baseUrl('admin/login.php') ?>" class="text-secondary small text-decoration-none">
                    <i class="fa-solid fa-shield-halved me-1"></i> Administrator Portal
                </a>
            </div>
        </div>
    </div>

    <div class="text-center mt-4 text-muted small">
        &copy; <?= date('Y') ?> SmartFace Attendance Monitoring System. All rights reserved.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
<script src="<?= baseUrl('assets/js/face_login.js') ?>?v=<?= time() ?>"></script>
<script src="<?= baseUrl('assets/js/app.js') ?>"></script>
</body>
</html>
