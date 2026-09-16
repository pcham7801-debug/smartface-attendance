<?php
// student/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireStudent();

$db = getDB();
$stmt = $db->prepare("SELECT u.*, s.id as student_db_id FROM users u JOIN students s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$studentData = $stmt->fetch();
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
    <meta name="apple-mobile-web-app-title" content="SmartFace Student">
    <link rel="manifest" href="<?= baseUrl('manifest.json') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= baseUrl('assets/icons/icon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= baseUrl('assets/icons/icon.svg') ?>">
    <title>Student Portal - SmartFace Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= baseUrl('assets/css/style.css') ?>" rel="stylesheet">
    <!-- face-api.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
</head>
<body>
<div class="wrapper">
    <!-- Student Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div id="content">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg top-navbar">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-light border-0">
                    <i class="fa-solid fa-bars fs-5"></i>
                </button>

                <div class="d-flex align-items-center ms-auto">
                    <div class="me-3 text-end d-none d-md-block">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($studentData['first_name'] . ' ' . $studentData['last_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($studentData['student_id']) ?> | <?= htmlspecialchars($studentData['course']) ?></small>
                    </div>
                    <?php 
                        $avatarPath = baseUrl('uploads/profiles/' . ($studentData['profile_picture'] ?? 'default_avatar.png'));
                    ?>
                    <img src="<?= $avatarPath ?>" alt="Avatar" class="avatar-sm" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($studentData['first_name']) ?>&background=0D8ABC&color=fff'">
                </div>
            </div>
        </nav>

        <div class="main-content">
            <?php displayFlash(); ?>
