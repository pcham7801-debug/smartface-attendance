<?php
// admin/login.php
// Dedicated Administrator Access Portal

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . baseUrl('admin/dashboard.php'));
        exit();
    } else {
        header('Location: ' . baseUrl('student/dashboard.php'));
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please provide both admin username and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check if user has admin privileges
                if ($user['role'] !== 'admin') {
                    $error = 'Access Denied: This portal is restricted to system administrators only.';
                    logAudit('Admin Portal Unauthorized Access', "Non-admin user {$user['username']} attempted admin login.");
                } else {
                    // Successful admin login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = 'admin';
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['must_change_password'] = $user['must_change_password'];

                    logAudit('Admin Login', "Administrator {$user['username']} signed in via Admin Portal.");

                    if ($user['must_change_password']) {
                        setFlash('warning', 'Security Alert: You are using the default admin password. Please update it in Settings.');
                    } else {
                        setFlash('success', 'Welcome to the Administrator Dashboard, ' . htmlspecialchars($user['first_name']) . '!');
                    }

                    header('Location: ' . baseUrl('admin/dashboard.php'));
                    exit();
                }
            } else {
                $error = 'Invalid administrator credentials.';
                logAudit('Failed Admin Login Attempt', "Failed admin sign-in attempt for input: {$username}");
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
    <title>Admin Portal - SmartFace Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= baseUrl('assets/css/style.css') ?>" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
        }
        .admin-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        .admin-badge-icon {
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 10px 20px rgba(13, 110, 253, 0.3);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">

<div class="container" style="max-width: 440px;">
    <!-- Branding Header -->
    <div class="text-center mb-4">
        <div class="admin-badge-icon text-white mb-3">
            <i class="fa-solid fa-shield-halved fa-2x"></i>
        </div>
        <h3 class="fw-bold text-white mb-1">Admin Control Portal</h3>
        <p class="text-secondary small">SmartFace Biometric Attendance Management</p>
    </div>

    <!-- Login Card -->
    <div class="admin-card p-4">
        <div class="card-body p-2">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h5 class="fw-bold text-dark mb-0">Administrator Sign In</h5>
                <span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="fa-solid fa-lock me-1"></i> Secure</span>
            </div>

            <?php displayFlash(); ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="mb-3">
                    <label for="username" class="form-label small fw-bold text-dark">Admin Username / Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-user-shield"></i></span>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Enter admin username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-bold text-dark">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-key"></i></span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter admin password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold rounded-3 mb-3 shadow-sm">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to Dashboard
                </button>
            </form>

            <hr class="my-3 text-muted opacity-25">

            <div class="text-center">
                <a href="<?= baseUrl('auth/login.php') ?>" class="text-decoration-none small text-muted">
                    <i class="fa-solid fa-arrow-left me-1"></i> Switch to Student Sign In
                </a>
            </div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="text-center mt-4 text-secondary small">
        &copy; <?= date('Y') ?> SmartFace Attendance System &bull; Restricted Area
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
