<?php
// admin/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireAdmin();

$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$adminUser = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - SmartFace Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= baseUrl('assets/css/style.css') ?>" rel="stylesheet">
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- face-api.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
</head>
<body>
<div class="wrapper">
    <!-- Admin Sidebar -->
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
                        <div class="fw-bold text-dark"><?= htmlspecialchars($adminUser['first_name'] . ' ' . $adminUser['last_name']) ?></div>
                        <small class="badge bg-primary">Administrator</small>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($adminUser['first_name'] . ' ' . $adminUser['last_name']) ?>&background=1E293B&color=fff" alt="Avatar" class="avatar-sm">
                </div>
            </div>
        </nav>

        <div class="main-content">
            <?php displayFlash(); ?>
