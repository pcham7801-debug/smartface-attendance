<?php
// admin/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$pendingSignoutsSidebar = 0;
try {
    if (isset($db)) {
        $pendingSignoutsSidebar = intval($db->query("SELECT COUNT(*) FROM attendance WHERE signout_status = 'pending'")->fetchColumn());
    }
} catch (Exception $e) {}
?>
<nav id="sidebar">
    <div class="sidebar-header text-center">
        <h3><i class="fa-solid fa-user-shield text-primary me-2"></i>SmartFace</h3>
        <small class="text-muted">Admin Control Panel</small>
    </div>

    <ul class="list-unstyled components">
        <li class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/dashboard.php') ?>" class="d-flex align-items-center justify-content-between">
                <span><i class="fa-solid fa-chart-pie me-2"></i> Dashboard</span>
                <?php if ($pendingSignoutsSidebar > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill"><?= $pendingSignoutsSidebar ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="<?= ($currentPage === 'students.php' || $currentPage === 'student_view.php') ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/students.php') ?>">
                <i class="fa-solid fa-user-graduate"></i> Students
            </a>
        </li>
        <li class="<?= $currentPage === 'subjects.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/subjects.php') ?>">
                <i class="fa-solid fa-book-open"></i> Subjects
            </a>
        </li>
        <li class="<?= $currentPage === 'enrollment.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/enrollment.php') ?>">
                <i class="fa-solid fa-id-card"></i> Student Enrollment
            </a>
        </li>
        <li class="<?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/attendance.php') ?>" class="d-flex align-items-center justify-content-between">
                <span><i class="fa-solid fa-clipboard-user me-2"></i> Attendance Logs</span>
                <?php if ($pendingSignoutsSidebar > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill"><?= $pendingSignoutsSidebar ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="<?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/reports.php') ?>">
                <i class="fa-solid fa-file-invoice"></i> Reports
            </a>
        </li>
        <li class="<?= $currentPage === 'face_registration.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/face_registration.php') ?>">
                <i class="fa-solid fa-camera"></i> Face Registration
            </a>
        </li>
        <li class="<?= $currentPage === 'admins.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/admins.php') ?>">
                <i class="fa-solid fa-user-gear"></i> Admin Accounts
            </a>
        </li>
        <li class="<?= $currentPage === 'audit_logs.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/audit_logs.php') ?>">
                <i class="fa-solid fa-list-check"></i> Audit Logs
            </a>
        </li>
        <li class="<?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('admin/settings.php') ?>">
                <i class="fa-solid fa-gear"></i> Settings
            </a>
        </li>
    </ul>

    <div class="p-3 mt-auto">
        <a href="<?= baseUrl('auth/logout.php') ?>" class="btn btn-outline-danger w-100 btn-sm font-weight-bold">
            <i class="fa-solid fa-power-off me-2"></i> Logout
        </a>
    </div>
</nav>
