<?php
// admin/face_registration.php
include __DIR__ . '/header.php';

$selectedStudentId = intval($_GET['student_id'] ?? 0);

// Fetch Students
$stmt = $db->query("SELECT s.id, u.student_id, u.first_name, u.last_name, u.course, u.year_level, u.face_registered FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.first_name ASC");
$studentsList = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12 text-center">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-camera text-primary me-2"></i> Admin Face Registration Tool</h3>
        <p class="text-muted">Register or update student biometric facial templates via administrator webcam station</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="card-body">
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Select Student <span class="text-danger">*</span></label>
                    <select id="adminStudentSelect" class="form-select form-select-lg">
                        <option value="">-- Select Student to Register Face --</option>
                        <?php foreach ($studentsList as $stu): ?>
                            <option value="<?= $stu['id'] ?>" <?= $selectedStudentId == $stu['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stu['student_id']) ?> - <?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?> (<?= htmlspecialchars($stu['course']) ?>) <?= $stu['face_registered'] ? '[Face Registered]' : '[Needs Face]' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Camera Display -->
                <div class="camera-container mb-4" id="adminCamBox" style="display: none;">
                    <video id="adminVideo" autoplay playsinline muted></video>
                    <canvas id="adminCanvas"></canvas>
                    <div id="adminBadge" class="face-status-badge bg-info text-white">Initializing Camera...</div>
                </div>

                <div class="text-center">
                    <button type="button" id="btnAdminScanFace" class="btn btn-primary btn-lg fw-bold px-5 py-3 rounded-3 shadow">
                        <i class="fa-solid fa-camera me-2"></i> Start Face Scanner
                    </button>
                </div>

                <div id="adminResultAlert" class="mt-4" style="display: none;"></div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const studentSelect = document.getElementById('adminStudentSelect');
    const btnScan = document.getElementById('btnAdminScanFace');
    const cameraBox = document.getElementById('adminCamBox');
    const video = document.getElementById('adminVideo');
    const canvas = document.getElementById('adminCanvas');
    const badge = document.getElementById('adminBadge');
    const resultAlert = document.getElementById('adminResultAlert');

    let cameraHelper = null;
    let faceEngine = null;

    btnScan.addEventListener('click', async () => {
        const studentId = studentSelect.value;
        if (!studentId) {
            alert('Please select a student first!');
            return;
        }

        resultAlert.style.display = 'none';
        cameraBox.style.display = 'block';
        btnScan.disabled = true;
        btnScan.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Scanning Face...';

        try {
            cameraHelper = new CameraHelper(video, canvas);
            await cameraHelper.startCamera();

            faceEngine = new FaceRecognitionEngine(video, canvas, badge);
            const descriptor = await faceEngine.captureFaceDescriptor();

            // Save to API
            const response = await fetch('<?= baseUrl("api/face_register.php") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: studentId,
                    face_descriptor: descriptor
                })
            });

            const result = await response.json();
            if (result.success) {
                resultAlert.className = 'alert alert-success p-3 text-center rounded-3';
                resultAlert.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i> ${result.message}`;
                resultAlert.style.display = 'block';
                showToast(result.message, 'success');
            } else {
                resultAlert.className = 'alert alert-danger p-3 text-center rounded-3';
                resultAlert.innerHTML = `<i class="fa-solid fa-circle-exclamation me-2"></i> ${result.message}`;
                resultAlert.style.display = 'block';
            }
        } catch (err) {
            resultAlert.className = 'alert alert-danger p-3 text-center rounded-3';
            resultAlert.innerHTML = err.message || 'Face scan error.';
            resultAlert.style.display = 'block';
        } finally {
            if (cameraHelper) cameraHelper.stopCamera();
            cameraBox.style.display = 'none';
            btnScan.disabled = false;
            btnScan.innerHTML = '<i class="fa-solid fa-camera me-2"></i> Start Face Scanner';
        }
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
