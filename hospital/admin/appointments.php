<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('admin');

$tab = $_GET['tab'] ?? 'active';
if (!in_array($tab, ['active', 'closed'], true)) {
    $tab = 'active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('admin/appointments.php?tab=' . $tab));
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            $doctorId = (int) ($_POST['doctor_id'] ?? 0);
            $status = $_POST['status'] ?? 'approved';
            $dateRaw = trim($_POST['appointment_date'] ?? '');
            $allowed = ['pending', 'approved', 'accepted', 'rejected', 'completed', 'cancelled'];

            if ($patientId <= 0 || $doctorId <= 0 || $dateRaw === '' || !in_array($status, $allowed, true)) {
                throw new RuntimeException('Invalid appointment data.');
            }

            $timestamp = strtotime($dateRaw);
            if ($timestamp === false) {
                throw new RuntimeException('Invalid appointment date and time.');
            }

            if (!jsondb_patient_by_id($patientId)) {
                throw new RuntimeException('Selected patient does not exist.');
            }

            if (!jsondb_doctor_by_id($doctorId)) {
                throw new RuntimeException('Selected doctor does not exist.');
            }

            if (!isAppointmentWithinDoctorSchedule($doctorId, $timestamp)) {
                throw new RuntimeException('Appointment is outside doctor min/max schedule.');
            }

            if (!isDoctorWithinDailyPatientLimit($doctorId, $timestamp, ['pending', 'approved', 'accepted', 'completed'])) {
                throw new RuntimeException('Doctor has reached max patient limit for this day.');
            }

            $appointmentDate = date('Y-m-d H:i:s', $timestamp);

            jsondb_create_appointment([
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'appointment_date' => $appointmentDate,
                'status' => $status,
            ]);

            flash('success', 'Appointment added successfully.');
        } elseif ($action === 'update_status') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $allowed = ['pending', 'approved', 'rejected', 'accepted', 'completed', 'cancelled'];

            if ($appointmentId <= 0 || !in_array($status, $allowed, true)) {
                throw new RuntimeException('Invalid appointment status request.');
            }

            $appointment = jsondb_appointment_by_id($appointmentId);
            if (!$appointment) {
                throw new RuntimeException('Appointment not found.');
            }

            $currentStatus = (string) $appointment['status'];

            // final states ကို admin side မှာ lock
            if (in_array($currentStatus, ['cancelled', 'completed'], true)) {
                throw new RuntimeException('This appointment is locked.');
            }

            jsondb_update_appointment($appointmentId, ['status' => $status]);
            flash('success', 'Appointment status updated.');
        } elseif ($action === 'delete') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            if ($appointmentId <= 0) {
                throw new RuntimeException('Invalid appointment id.');
            }

            jsondb_delete_appointment($appointmentId);
            flash('success', 'Appointment deleted.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('admin/appointments.php?tab=' . $tab));
}

$appointments = jsondb_appointments_with_names();
$patients = jsondb_patients_sorted_by_name();
$doctors = jsondb_doctors_sorted_by_name();

$filteredAppointments = [];
foreach ($appointments as $appointment) {
    $status = (string) $appointment['status'];

    if ($tab === 'active' && !in_array($status, ['cancelled', 'completed'], true)) {
        $filteredAppointments[] = $appointment;
    }

    if ($tab === 'closed' && in_array($status, ['cancelled', 'completed'], true)) {
        $filteredAppointments[] = $appointment;
    }
}

$csrf = generate_csrf_token();

render_app_header('Manage Appointments');
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= e(url('admin/appointments.php?tab=active')) ?>"
               class="btn btn-sm <?= $tab === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Active Appointments
            </a>

            <a href="<?= e(url('admin/appointments.php?tab=closed')) ?>"
               class="btn btn-sm <?= $tab === 'closed' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Closed Appointments
            </a>
        </div>

        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
            <i class="bi bi-plus-circle"></i> Add Appointment
        </button>
    </div>

    <div class="card-body table-responsive">
        <h5 class="mb-3">
            <?= $tab === 'active' ? 'Active Appointments' : 'Closed Appointments' ?>
        </h5>

        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th style="min-width: <?= $tab === 'active' ? '260px' : '120px' ?>;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$filteredAppointments): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <?= $tab === 'active' ? 'No active appointments found.' : 'No closed appointments found.' ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php $index = 1; ?>
                <?php foreach ($filteredAppointments as $appointment): ?>
                    <?php
                    $status = (string) $appointment['status'];
                    $locked = in_array($status, ['cancelled', 'completed'], true);
                    $badgeClass = 'badge-status';
                    ?>
                    <tr>
                        <td><?= $index++ ?></td>
                        <td><?= e($appointment['patient_name']) ?></td>
                        <td><?= e($appointment['doctor_name']) ?></td>
                        <td><?= e((string) $appointment['appointment_date']) ?></td>
                        <td>
                            <span class="badge <?= e($badgeClass) ?>">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($tab === 'active' && !$locked): ?>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <form method="post" class="d-flex align-items-center gap-2 mb-0">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">

                                        <select name="status" class="form-select form-select-sm" style="min-width: 130px;">
                                            <?php foreach (['pending', 'rejected', 'accepted', 'completed', 'cancelled'] as $optionStatus): ?>
                                                <option value="<?= e($optionStatus) ?>" <?= $status === $optionStatus ? 'selected' : '' ?>>
                                                    <?= e(ucfirst($optionStatus)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>

                                    <form method="post" class="mb-0">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this appointment?')">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <form method="post" class="mb-0">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this appointment?')">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title">Add Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Choose patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= e((string) $patient['id']) ?>">
                                    <?= e($patient['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Doctor</label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">Choose doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= e((string) $doctor['id']) ?>">
                                    <?= e($doctor['name']) ?> (<?= e($doctor['specialty']) ?>,
                                    <?= e(formatTime12Hour((string) $doctor['schedule_min_time'])) ?>-<?= e(formatTime12Hour((string) $doctor['schedule_max_time'])) ?>,
                                    limit <?= e((string) $doctor['max_patients_per_day']) ?>/day)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Date & Time</label>
                        <input type="datetime-local" name="appointment_date" class="form-control" required>
                    </div>

                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['pending', 'accepted', 'rejected', 'completed', 'cancelled'] as $status): ?>
                                <option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php render_app_footer(); ?>