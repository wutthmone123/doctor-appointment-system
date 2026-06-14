<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('patient');

$user = current_user();
$userId = (int) $user['id'];
$patientId = getPatientIdByUserId($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('patient/settings.php'));
    }

    if ($patientId === null) {
        flash('error', 'Patient profile not linked.');
        redirect(url('patient/settings.php'));
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update') {
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($name === '' || $email === '' || $phone === '') {
                throw new RuntimeException('Name, email, and phone are required.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid email format.');
            }

            if ($password !== '' && strlen($password) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }

            $current = jsondb_patient_by_id($patientId);
            if (!$current) {
                throw new RuntimeException('Patient profile not found.');
            }
            $oldEmail = strtolower((string) $current['email']);

            if (jsondb_user_email_exists($email, $userId)) {
                throw new RuntimeException('Email is already used by another account.');
            }

            jsondb_transaction(function () use ($patientId, $userId, $name, $email, $phone, $password): void {
                jsondb_update_patient($patientId, [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                ]);

                $userData = [
                    'name' => $name,
                    'email' => $email,
                ];
                if ($password !== '') {
                    $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                jsondb_update_user_by_id($userId, $userData);
            });

            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;

            flash('success', 'Account updated successfully.');
        } elseif ($action === 'delete') {
            jsondb_transaction(function () use ($patientId, $userId): void {
                jsondb_delete_appointments_by_patient($patientId);
                jsondb_delete_patient($patientId);
                jsondb_delete_user_by_id_and_role($userId, 'patient');
            });

            logout_user();
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            flash('success', 'Patient account deleted.');
            redirect(url('login.php'));
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('patient/settings.php'));
}

$patient = null;
if ($patientId !== null) {
    $patient = jsondb_patient_by_id($patientId);
}

$csrf = generate_csrf_token();

render_app_header('Account Settings');
?>
<?php if ($patientId === null || !$patient): ?>
    <div class="alert alert-warning">Your patient profile is not linked yet. Contact admin.</div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="update">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?= e($patient['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($patient['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($patient['phone']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password (optional)</label>
                            <input type="password"
                                name="password"
                                class="form-control"
                                minlength="6"
                                data_password_ready="1"
                                autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Account</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger-subtle">
                    <h5 class="mb-0 text-danger">Delete Account</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">This removes your patient account and all related appointments.</p>
                    <form method="post" onsubmit="return confirm('Delete your patient account permanently?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger">Delete My Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php render_app_footer(); ?>
