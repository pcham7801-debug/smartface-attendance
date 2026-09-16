<?php
// student/signout.php
include __DIR__ . '/header.php';

$studentId = $studentData['student_db_id'];
$today = date('Y-m-d');

// Fetch today's active sessions where signout_status is not approved
$stmt = $db->prepare("SELECT a.id, a.subject_id, a.time_in, a.time_out, a.status, a.signout_status, a.signout_reason, s.subject_code, s.subject_name, s.room
    FROM attendance a 
    JOIN subjects s ON a.subject_id = s.id 
    WHERE a.student_id = ? AND a.attendance_date = ? AND a.signout_status != 'approved' AND a.status != 'Absent'");
$stmt->execute([$studentId, $today]);
$activeSessions = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12 text-center">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-right-from-bracket text-primary me-2"></i> Attendance Sign Out</h3>
        <p class="text-muted">Sign out of your active subject attendance session</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Active Attendance Sessions Today</h5>

                <?php if (empty($activeSessions)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-check-double fs-1 text-success mb-3"></i>
                        <h5>No Active Sessions to Sign Out</h5>
                        <p class="small">You do not have any active sign-in sessions requiring sign-out today.</p>
                        <a href="<?= baseUrl('student/dashboard.php') ?>" class="btn btn-primary btn-sm fw-bold">Back to Dashboard</a>
                    </div>
                <?php else: ?>
                    <div class="list-group mb-4">
                        <?php foreach ($activeSessions as $session): ?>
                            <div class="list-group-item p-3 border rounded-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <h6 class="fw-bold mb-1 text-primary"><?= htmlspecialchars($session['subject_code']) ?> - <?= htmlspecialchars($session['subject_name']) ?></h6>
                                    <div class="text-muted small mb-1"><i class="fa-solid fa-door-open me-1"></i> <?= htmlspecialchars($session['room']) ?></div>
                                    <div class="text-muted small">
                                        <i class="fa-regular fa-clock me-1"></i> Time In: <strong><?= date('h:i A', strtotime($session['time_in'])) ?></strong>
                                    </div>
                                    <?php if ($session['signout_status'] === 'pending'): ?>
                                        <div class="small text-warning-emphasis fw-semibold mt-1">
                                            <i class="fa-solid fa-hourglass-half me-1"></i> Sign-out requested at <?= date('h:i A', strtotime($session['time_out'])) ?>
                                        </div>
                                    <?php elseif ($session['signout_status'] === 'rejected'): ?>
                                        <div class="small text-danger fw-semibold mt-1">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Previous sign-out was rejected by instructor/admin.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($session['signout_status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">
                                            <i class="fa-solid fa-hourglass-half me-1"></i> Pending Approval
                                        </span>
                                    <?php elseif ($session['signout_status'] === 'rejected'): ?>
                                        <button type="button" class="btn btn-warning btn-sm font-weight-bold btn-signOutSession" data-subject-id="<?= $session['subject_id'] ?>" data-subject-name="<?= htmlspecialchars($session['subject_code']) ?>">
                                            <i class="fa-solid fa-rotate-right me-1"></i> Re-request Sign Out
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-danger font-weight-bold btn-signOutSession" data-subject-id="<?= $session['subject_id'] ?>" data-subject-name="<?= htmlspecialchars($session['subject_code']) ?>">
                                            <i class="fa-solid fa-right-from-bracket me-1"></i> Request Sign Out
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.btn-signOutSession');
    buttons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const subjectId = btn.dataset.subjectId;
            const subjectName = btn.dataset.subjectName;

            if (!confirm(`Are you sure you want to sign out from ${subjectName}?`)) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Signing out...';

            try {
                const response = await fetch('<?= baseUrl("api/attendance_out.php") ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subject_id: subjectId })
                });

                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to sign out.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-right-from-bracket me-1"></i> Sign Out';
                }
            } catch (err) {
                alert('Connection error.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-right-from-bracket me-1"></i> Sign Out';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
