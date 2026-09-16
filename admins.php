<?php
// admin/admins.php
include __DIR__ . '/header.php';

$error = '';

// Handle Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_admin'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or Email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $names = explode(' ', $name, 2);
                $firstName = $names[0];
                $lastName = $names[1] ?? 'Admin';

                try {
                    $db->beginTransaction();
                    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'admin', 'active', NOW())");
                    $stmt->execute([$firstName, $lastName, $email, $username, $hashed]);
                    $userId = $db->lastInsertId();

                    $stmt = $db->prepare("INSERT INTO admins (user_id, name, email, username, password, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                    $stmt->execute([$userId, $name, $email, $username, $hashed]);

                    $db->commit();
                    logAudit('Add Admin', "Created new admin account: {$username}");
                    setFlash('success', 'Admin account created successfully!');
                    header('Location: ' . baseUrl('admin/admins.php'));
                    exit();
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Failed to create admin: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Admins List
$stmt = $db->query("SELECT u.* FROM users u WHERE u.role = 'admin' ORDER BY u.created_at ASC");
$adminList = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-user-gear text-primary me-2"></i> Admin Accounts</h3>
        <p class="text-muted">Manage system administrators and administrative rights</p>
    </div>
    <div class="col-md-4 text-md-end">
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fa-solid fa-user-plus me-1"></i> Add Administrator
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Admins Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminList as $adm): ?>
                        <tr>
                            <td class="ps-3 fw-bold">#<?= $adm['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($adm['first_name'] . ' ' . $adm['last_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($adm['username']) ?></span></td>
                            <td><?= htmlspecialchars($adm['email']) ?></td>
                            <td><span class="badge bg-success">Active</span></td>
                            <td><small class="text-muted"><?= date('M d, Y', strtotime($adm['created_at'])) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action_add_admin" value="1">

                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-user-shield me-2"></i> Add Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Admin Name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="admin@school.edu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="admin_user" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
