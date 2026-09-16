<?php
// auth/forgot_password.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $error = 'Please enter your registered email address.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $message = "Password reset link has been generated. Please contact your system administrator or check your email inbox to reset your password.";
        } else {
            $error = "No registered account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SmartFace Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= baseUrl('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center py-5">

<div class="container" style="max-width: 450px;">
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <div class="card-body">
            <h4 class="fw-bold mb-3 text-center text-primary"><i class="fa-solid fa-key me-2"></i> Reset Password</h4>
            <p class="text-muted text-center mb-4">Enter your registered email address and we will provide instructions to reset your account password.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="student@school.edu" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold rounded-3 mb-3">Request Reset</button>
            </form>

            <div class="text-center">
                <a href="<?= baseUrl('auth/login.php') ?>" class="text-decoration-none text-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
