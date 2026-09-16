<?php
// student/face_attendance.php
include __DIR__ . '/header.php';

$studentId = $studentData['student_db_id'];

// Get Enrolled Subjects
$stmt = $db->prepare("SELECT s.id, s.subject_code, s.subject_name, s.day, s.start_time, s.end_time, s.room
    FROM subjects s
    JOIN student_subjects ss ON s.id = ss.subject_id
    WHERE ss.student_id = ? AND ss.enrollment_status = 'enrolled' AND s.status = 'active'
    ORDER BY s.subject_code ASC");
$stmt->execute([$studentId]);
$subjects = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12 text-center">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-camera text-primary me-2"></i> Face Attendance Sign In</h3>
        <p class="text-muted">Select your subject and scan your face to record attendance</p>
    </div>
</div>

<?php if (!$studentData['face_registered']): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-danger shadow-sm text-center p-4">
                <i class="fa-solid fa-circle-exclamation text-danger fs-1 mb-3"></i>
                <h4 class="fw-bold text-danger">Face Not Registered!</h4>
                <p class="text-muted">You must register your face first before using facial recognition attendance.</p>
                <a href="<?= baseUrl('student/profile.php#face-registration') ?>" class="btn btn-primary fw-bold mx-auto px-4">Go to Face Registration</a>
            </div>
        </div>
    </div>
<?php else: ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="card-body">
                
                <!-- Subject Selection -->
                <div class="mb-4">
                    <label for="subjectSelect" class="form-label fw-bold">Select Subject <span class="text-danger">*</span></label>
                    <select id="subjectSelect" class="form-select form-select-lg">
                        <option value="">-- Choose Subject --</option>
                        <?php foreach ($subjects as $subj): ?>
                            <option value="<?= $subj['id'] ?>">
                                <?= htmlspecialchars($subj['subject_code']) ?> - <?= htmlspecialchars($subj['subject_name']) ?> (<?= htmlspecialchars($subj['day']) ?> <?= date('h:i A', strtotime($subj['start_time'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Camera Container -->
                <div class="camera-container mb-4" id="attCameraBox" style="display: none;">
                    <video id="attVideo" autoplay playsinline muted></video>
                    <canvas id="attCanvas"></canvas>
                    <div id="attFaceBadge" class="face-status-badge bg-info text-white">Initializing Camera...</div>
                </div>

                <div class="text-center">
                    <button type="button" id="btnStartSignIn" class="btn btn-primary btn-lg fw-bold px-5 py-3 rounded-3 shadow">
                        <i class="fa-solid fa-face-smile me-2"></i> Sign In with Face
                    </button>
                    <button type="button" id="btnStopCamera" class="btn btn-outline-secondary btn-lg fw-bold px-4 py-3 rounded-3 ms-2" style="display: none;">
                        <i class="fa-solid fa-video-slash me-2"></i> Stop Camera
                    </button>
                </div>

                <!-- Notification Result Box -->
                <div id="attendanceResultAlert" class="mt-4" style="display: none;"></div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const subjectSelect = document.getElementById('subjectSelect');
    const btnStart = document.getElementById('btnStartSignIn');
    const btnStop = document.getElementById('btnStopCamera');
    const cameraBox = document.getElementById('attCameraBox');
    const video = document.getElementById('attVideo');
    const canvas = document.getElementById('attCanvas');
    const badge = document.getElementById('attFaceBadge');
    const resultAlert = document.getElementById('attendanceResultAlert');

    let cameraHelper = null;
    let faceEngine = null;
    let isVerifying = false;

    btnStart.addEventListener('click', async () => {
        const subjectId = subjectSelect.value;
        if (!subjectId) {
            alert('Please select a subject first!');
            return;
        }

        resultAlert.style.display = 'none';
        cameraBox.style.display = 'block';
        btnStop.style.display = 'inline-block';
        btnStart.disabled = true;
        btnStart.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Scanning & Verifying...';

        try {
            cameraHelper = new CameraHelper(video, canvas);
            await cameraHelper.startCamera();

            faceEngine = new FaceRecognitionEngine(video, canvas, badge);
            await faceEngine.loadModels();

            // Perform face detection and capture descriptor
            const liveDescriptor = await faceEngine.captureFaceDescriptor();

            badge.className = 'face-status-badge bg-warning text-dark';
            badge.innerHTML = 'Verifying Face...';

            // 1. Verify face with server
            const verifyResp = await fetch('<?= baseUrl("api/face_verify.php") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    face_descriptor: liveDescriptor
                })
            });

            const verifyResult = await verifyResp.json();

            if (!verifyResult.success || !verifyResult.verified) {
                badge.className = 'face-status-badge bg-danger text-white';
                badge.innerHTML = 'Face Not Recognized';
                showResult('danger', verifyResult.message || 'Face not recognized. Please try again.');
                return;
            }

            badge.className = 'face-status-badge bg-success text-white';
            badge.innerHTML = 'Face Verified!';

            // 2. Submit Attendance Sign In
            const attResp = await fetch('<?= baseUrl("api/attendance_in.php") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subject_id: subjectId,
                    verification_method: 'Face Recognition'
                })
            });

            const attResult = await attResp.json();

            if (attResult.success) {
                showResult('success', `<i class="fa-solid fa-circle-check me-2 fs-5"></i> <strong>${attResult.message}</strong><br>Time In: ${attResult.time_in}`);
                showToast(attResult.message, 'success');
            } else {
                showResult('danger', `<i class="fa-solid fa-circle-exclamation me-2 fs-5"></i> ${attResult.message}`);
                showToast(attResult.message, 'danger');
            }

        } catch (err) {
            showResult('danger', err.message || 'Camera or Face Recognition Error.');
        } finally {
            btnStart.disabled = false;
            btnStart.innerHTML = '<i class="fa-solid fa-face-smile me-2"></i> Sign In with Face';
        }
    });

    btnStop.addEventListener('click', () => {
        if (cameraHelper) cameraHelper.stopCamera();
        cameraBox.style.display = 'none';
        btnStop.style.display = 'none';
        btnStart.disabled = false;
        btnStart.innerHTML = '<i class="fa-solid fa-face-smile me-2"></i> Sign In with Face';
    });

    function showResult(type, messageHtml) {
        resultAlert.className = `alert alert-${type} rounded-3 p-3 text-center`;
        resultAlert.innerHTML = messageHtml;
        resultAlert.style.display = 'block';
    }
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
