<?php
// student/subjects.php
include __DIR__ . '/header.php';

$studentId = $studentData['student_db_id'];

$stmt = $db->prepare("SELECT s.*,
    (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = ss.student_id) as total_subject_classes,
    (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = s.id AND a.student_id = ss.student_id AND a.status IN ('Present', 'Late')) as attended_subject_classes
    FROM subjects s
    JOIN student_subjects ss ON s.id = ss.subject_id
    WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled' AND s.status = 'active'
    ORDER BY s.subject_code ASC");
$stmt->execute([$studentId]);
$subjects = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-book-bookmark text-primary me-2"></i> My Enrolled Subjects</h3>
        <p class="text-muted">List of all active subjects enrolled for the current semester</p>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($subjects)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="fa-solid fa-folder-open fs-1 mb-2"></i>
            <p>You are not currently enrolled in any subjects.</p>
        </div>
    <?php else: ?>
        <?php foreach ($subjects as $subj): 
            $tot = intval($subj['total_subject_classes']);
            $att = intval($subj['attended_subject_classes']);
            $rate = $tot > 0 ? round(($att / $tot) * 100) : 100;
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge bg-primary fs-6 px-3 py-2"><?= htmlspecialchars($subj['subject_code']) ?></span>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($subj['semester']) ?></span>
                        </div>
                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($subj['subject_name']) ?></h5>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($subj['description'] ?: 'No description provided.') ?></p>

                        <div class="bg-light p-3 rounded-3 mb-3 small">
                            <div class="mb-1"><i class="fa-solid fa-user-tie text-secondary me-2"></i> Instructor: <strong><?= htmlspecialchars($subj['instructor']) ?></strong></div>
                            <div class="mb-1"><i class="fa-solid fa-door-open text-secondary me-2"></i> Room: <strong><?= htmlspecialchars($subj['room']) ?></strong></div>
                            <div class="mb-1"><i class="fa-solid fa-calendar-days text-secondary me-2"></i> Day: <strong><?= htmlspecialchars($subj['day']) ?></strong></div>
                            <div><i class="fa-regular fa-clock text-secondary me-2"></i> Time: <strong><?= date('h:i A', strtotime($subj['start_time'])) ?> - <?= date('h:i A', strtotime($subj['end_time'])) ?></strong></div>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span class="text-muted">Attendance Rate</span>
                                <span class="fw-bold text-<?= $rate >= 80 ? 'success' : 'danger' ?>"><?= $rate ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-<?= $rate >= 80 ? 'success' : 'danger' ?>" style="width: <?= $rate ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
