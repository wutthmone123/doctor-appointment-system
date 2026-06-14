<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('admin');


function normalizeScheduleTime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('H:i:s', $timestamp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('admin/doctors.php'));
    }

    $returnPage = max(1, (int) ($_POST['page'] ?? ($_GET['page'] ?? 1)));
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $specialty = trim($_POST['specialty'] ?? '');
            $password = $_POST['password'] ?? '';
            $scheduleMin = normalizeScheduleTime($_POST['schedule_min_time'] ?? '');
            $scheduleMax = normalizeScheduleTime($_POST['schedule_max_time'] ?? '');
            $minPatientsPerDay = (int) ($_POST['min_patients_per_day'] ?? 0);
            $maxPatientsPerDay = (int) ($_POST['max_patients_per_day'] ?? 0);

            if ($name === '' || $email === '' || $phone === '' || $address === '' || $specialty === '' || $password === '' || $scheduleMin === null || $scheduleMax === null) {
                throw new RuntimeException('All fields are required to add doctor.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid doctor email format.');
            }

            if (strlen($password) < 6) {
                throw new RuntimeException('Doctor password must be at least 6 characters.');
            }

            if ($scheduleMin >= $scheduleMax) {
                throw new RuntimeException('Doctor min schedule must be earlier than max schedule.');
            }

            if ($minPatientsPerDay <= 0 || $maxPatientsPerDay <= 0 || $minPatientsPerDay > $maxPatientsPerDay) {
                throw new RuntimeException('Patient limit must be valid and min cannot be greater than max.');
            }

            if (jsondb_user_email_exists($email)) {
                throw new RuntimeException('Email already exists.');
            }

            jsondb_transaction(function () use ($name, $email, $phone, $address, $specialty, $scheduleMin, $scheduleMax, $minPatientsPerDay, $maxPatientsPerDay, $password): void {
                jsondb_create_doctor([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'specialty' => $specialty,
                    'schedule_min_time' => $scheduleMin,
                    'schedule_max_time' => $scheduleMax,
                    'min_patients_per_day' => $minPatientsPerDay,
                    'max_patients_per_day' => $maxPatientsPerDay,
                ]);

                jsondb_create_user($name, $email, password_hash($password, PASSWORD_DEFAULT), 'doctor');
            });
            flash('success', 'Doctor added successfully.');
        } elseif ($action === 'edit') {
            $doctorId = (int) ($_POST['doctor_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $specialty = trim($_POST['specialty'] ?? '');
            $password = $_POST['password'] ?? '';
            $scheduleMin = normalizeScheduleTime($_POST['schedule_min_time'] ?? '');
            $scheduleMax = normalizeScheduleTime($_POST['schedule_max_time'] ?? '');
            $minPatientsPerDay = (int) ($_POST['min_patients_per_day'] ?? 0);
            $maxPatientsPerDay = (int) ($_POST['max_patients_per_day'] ?? 0);

            if ($doctorId <= 0 || $name === '' || $email === '' || $phone === '' || $address === '' || $specialty === '' || $scheduleMin === null || $scheduleMax === null) {
                throw new RuntimeException('Invalid doctor update payload.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid doctor email format.');
            }

            if ($scheduleMin >= $scheduleMax) {
                throw new RuntimeException('Doctor min schedule must be earlier than max schedule.');
            }

            if ($minPatientsPerDay <= 0 || $maxPatientsPerDay <= 0 || $minPatientsPerDay > $maxPatientsPerDay) {
                throw new RuntimeException('Patient limit must be valid and min cannot be greater than max.');
            }

            $oldDoctor = jsondb_doctor_by_id($doctorId);
            if (!$oldDoctor) {
                throw new RuntimeException('Doctor record not found.');
            }

            $oldEmail = strtolower($oldDoctor['email']);
            if ($email !== $oldEmail) {
                if (jsondb_user_email_exists($email)) {
                    throw new RuntimeException('New email is already in use.');
                }
            }

            jsondb_transaction(function () use ($doctorId, $name, $email, $phone, $address, $specialty, $scheduleMin, $scheduleMax, $minPatientsPerDay, $maxPatientsPerDay, $password, $oldEmail): void {
                jsondb_update_doctor($doctorId, [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'specialty' => $specialty,
                    'schedule_min_time' => $scheduleMin,
                    'schedule_max_time' => $scheduleMax,
                    'min_patients_per_day' => $minPatientsPerDay,
                    'max_patients_per_day' => $maxPatientsPerDay,
                ]);

                $user = jsondb_user_by_email($oldEmail);
                if ($user && (string) $user['role'] === 'doctor') {
                    $userData = [
                        'name' => $name,
                        'email' => $email,
                    ];
                    if ($password !== '') {
                        if (strlen($password) < 6) {
                            throw new RuntimeException('Doctor password must be at least 6 characters.');
                        }
                        $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    jsondb_update_user_by_id((int) $user['id'], $userData);
                }
            });
            flash('success', 'Doctor updated successfully.');
        } elseif ($action === 'delete') {
            $doctorId = (int) ($_POST['doctor_id'] ?? 0);
            if ($doctorId <= 0) {
                throw new RuntimeException('Invalid doctor id.');
            }

            $doctor = jsondb_doctor_by_id($doctorId);
            if (!$doctor) {
                throw new RuntimeException('Doctor record not found.');
            }

            jsondb_transaction(function () use ($doctorId, $doctor): void {
                jsondb_delete_appointments_by_doctor($doctorId);
                jsondb_delete_doctor($doctorId);
                jsondb_delete_user_by_email_role(strtolower((string) $doctor['email']), 'doctor');
            });
            flash('success', 'Doctor deleted successfully.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('admin/doctors.php?page=' . $returnPage));
}

$perPage = 4;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalDoctors = jsondb_doctors_count();
$totalPages = max(1, (int) ceil($totalDoctors / $perPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $perPage;
$doctors = jsondb_doctors_paginated($offset, $perPage);
$csrf = generate_csrf_token();

render_app_header('Manage Doctors');
?>
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Doctors</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
            <i class="bi bi-plus-circle"></i> Add Doctor
        </button>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Speciality</th>
                    <!-- <th>Schedule</th>
                    <th>Patient Limit</th>
                    <th>Created</th> -->
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                
                <?php if (!$doctors): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No doctors found.</td>
                    </tr>
                <?php endif; ?>
                <?php $index = 1; ?>
                <!-- <?php $index = $offset + 1; ?> -->
                <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td><?= $index++ ?></td>
                        <td><?= e($doctor['name']) ?></td>
                        <td><?= e($doctor['email']) ?></td>
                        <td><?= e($doctor['phone']) ?></td>
                        <td><?= e($doctor['address']) ?></td>
                        <td><?= e($doctor['specialty']) ?></td>
                        <!-- <td><?= e(formatTime12Hour((string) $doctor['schedule_min_time'])) ?> - <?= e(formatTime12Hour((string) $doctor['schedule_max_time'])) ?></td>
                        <td><?= e((string) $doctor['min_patients_per_day']) ?> - <?= e((string) $doctor['max_patients_per_day']) ?></td>
                        <td><?= e((string) $doctor['created_at']) ?></td> -->
                        <td>
                            <button
                                class="btn btn-sm btn-outline-primary edit-doctor-btn mb-2"
                                data-bs-toggle="modal"
                                data-bs-target="#editDoctorModal"
                                data-id="<?= e((string) $doctor['id']) ?>"
                                data-name="<?= e($doctor['name']) ?>"
                                data-email="<?= e($doctor['email']) ?>"
                                data-phone="<?= e($doctor['phone']) ?>"
                                data-address="<?= e($doctor['address']) ?>"
                                data-specialty="<?= e($doctor['specialty']) ?>">
                                
                                Edit
                            </button>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="doctor_id" value="<?= e((string) $doctor['id']) ?>">
                                <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this doctor?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <?= e((string) ($offset + 1)) ?> to <?= e((string) ($offset + count($doctors))) ?> of <?= e((string) $totalDoctors) ?> doctors
                </small>
                <nav aria-label="Doctors pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(url('admin/doctors.php?page=' . max(1, $currentPage - 1))) ?>">Previous</a>
                        </li>
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(url('admin/doctors.php?page=' . $p)) ?>"><?= e((string) $p) ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(url('admin/doctors.php?page=' . min($totalPages, $currentPage + 1))) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Doctor</h5>
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
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Specialty</label>
                        <input type="text" name="specialty" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Schedule Min</label>
                            <input type="time" name="schedule_min_time" class="form-control" value="09:00" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Schedule Max</label>
                            <input type="time" name="schedule_max_time" class="form-control" value="17:00" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Min Patients</label>
                            <input type="number" name="min_patients_per_day" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Max Patients</label>
                            <input type="number" name="max_patients_per_day" class="form-control" value="30" min="1" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Temporary Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="doctor_id" value="">
                <input type="hidden" name="page" value="<?= e((string) $currentPage) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Doctor</h5>
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
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Specialty</label>
                        <input type="text" name="specialty" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Schedule Min</label>
                            <input type="time" name="schedule_min_time" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Schedule Max</label>
                            <input type="time" name="schedule_max_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Min Patients</label>
                            <input type="number" name="min_patients_per_day" class="form-control" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Max Patients</label>
                            <input type="number" name="max_patients_per_day" class="form-control" min="1" required>
                        </div>
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
