<?php
// student/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar">
    <div class="sidebar-header text-center">
        <h3><i class="fa-solid fa-face-smile text-primary me-2"></i>SmartFace</h3>
        <small class="text-muted">Student Portal</small>
    </div>

    <ul class="list-unstyled components">
        <li class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('student/dashboard.php') ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>
        <li class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('student/profile.php') ?>">
                <i class="fa-solid fa-user-gear"></i> My Profile
            </a>
        </li>
        <li class="<?= $currentPage === 'subjects.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('student/subjects.php') ?>">
                <i class="fa-solid fa-book-bookmark"></i> My Subjects
            </a>
        </li>
        <li class="<?= ($currentPage === 'face_attendance.php' || $currentPage === 'attendance.php') ? 'active' : '' ?>">
            <a href="<?= baseUrl('student/face_attendance.php') ?>">
                <i class="fa-solid fa-camera"></i> Sign In with Face
            </a>
        </li>
        <li class="<?= $currentPage === 'signout.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('student/signout.php') ?>">
                <i class="fa-solid fa-right-from-bracket"></i> Sign Out
            </a>
        </li>
        <li class="<?= $currentPage === 'attendance_history.php' ? 'active' : '' ?>">
            <a href="<?= baseUrl('student/attendance_history.php') ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Attendance History
            </a>
        </li>
    </ul>

    <div class="p-3 mt-auto">
        <a href="<?= baseUrl('auth/logout.php') ?>" class="btn btn-outline-danger w-100 btn-sm font-weight-bold">
            <i class="fa-solid fa-power-off me-2"></i> Logout
        </a>
    </div>
</nav>
