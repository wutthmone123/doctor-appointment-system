<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('admin');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('admin/patients.php'));
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'edit') {
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($patientId <= 0 || $name === '' || $email === '' || $phone === '') {
                throw new RuntimeException('Invalid patient update payload.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid patient email format.');
            }

            $oldPatient = jsondb_patient_by_id($patientId);
            if (!$oldPatient) {
                throw new RuntimeException('Patient record not found.');
            }

            $oldEmail = strtolower($oldPatient['email']);
            if ($email !== $oldEmail) {
                if (jsondb_user_email_exists($email)) {
                    throw new RuntimeException('New email is already in use.');
                }
            }

            jsondb_transaction(function () use ($patientId, $name, $email, $phone, $password, $oldEmail): void {
                jsondb_update_patient($patientId, [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                ]);

                $user = jsondb_user_by_email($oldEmail);
                if ($user && (string) $user['role'] === 'patient') {
                    $userData = [
                        'name' => $name,
                        'email' => $email,
                    ];
                    if ($password !== '') {
                        if (strlen($password) < 6) {
                            throw new RuntimeException('Patient password must be at least 6 characters.');
                        }
                        $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    jsondb_update_user_by_id((int) $user['id'], $userData);
                }
            });
            flash('success', 'Patient updated successfully.');
        } elseif ($action === 'delete') {
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            if ($patientId <= 0) {
                throw new RuntimeException('Invalid patient id.');
            }

            $patient = jsondb_patient_by_id($patientId);
            if (!$patient) {
                throw new RuntimeException('Patient record not found.');
            }

            jsondb_transaction(function () use ($patientId, $patient): void {
                jsondb_delete_appointments_by_patient($patientId);
                jsondb_delete_patient($patientId);
                jsondb_delete_user_by_email_role(strtolower((string) $patient['email']), 'patient');
            });
            flash('success', 'Patient deleted successfully.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('admin/patients.php'));
}

$patients = jsondb_patients_paginated(0, jsondb_patients_count());
$csrf = generate_csrf_token();

render_app_header('Manage Patients');
?>
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Patients</h5>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$patients): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No patients found.</td>
                    </tr>
                <?php endif; ?>
                <?php $index = 1; ?>
                <?php foreach ($patients as $patient): ?>
                    <tr>
                        <td><?= $index++ ?></td>
                        <!-- <td><?= e((string) $patient['id']) ?></td> -->
                        <td><?= e($patient['name']) ?></td>
                        <td><?= e($patient['email']) ?></td>
                        <td><?= e($patient['phone']) ?></td>
                        <td><?= e((string) $patient['created_at']) ?></td>
                        <td>
                            <!-- <button
                                class="btn btn-sm btn-outline-primary edit-patient-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#editPatientModal"
                                data-id="<?= e((string) $patient['id']) ?>"
                                data-name="<?= e($patient['name']) ?>"
                                data-email="<?= e($patient['email']) ?>"
                                data-phone="<?= e($patient['phone']) ?>">
                                Edit
                            </button> -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="patient_id" value="<?= e((string) $patient['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this patient?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="editPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="patient_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">New Password (optional)</label>
                        <input type="password"
                            name="password"
                            class="form-control"
                            minlength="6"
                            data_password_ready="1"
                            autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php render_app_footer(); ?>
