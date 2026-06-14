<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('doctor');

$user = current_user();
$doctorId = getDoctorIdByUserId((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('doctor/appointments.php'));
    }

    if ($doctorId === null) {
        flash('error', 'Doctor profile not linked.');
        redirect(url('doctor/appointments.php'));
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_status') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $allowed = ['accepted', 'rejected', 'completed'];

            if ($appointmentId <= 0 || !in_array($status, $allowed, true)) {
                throw new RuntimeException('Invalid status request.');
            }

            $appointment = jsondb_appointment_by_id($appointmentId);
            if (!$appointment || (int) $appointment['doctor_id'] !== $doctorId) {
                throw new RuntimeException('Appointment not found.');
            }

            $currentStatus = (string) $appointment['status'];

            // final states => lock
            if (in_array($currentStatus, ['cancelled', 'rejected', 'completed'], true)) {
                throw new RuntimeException('This appointment can no longer be updated.');
            }

            // accept လုပ်မယ့်အချိန် daily limit စစ်
            if ($status === 'accepted') {
                if (!in_array($currentStatus, ['accepted', 'completed'], true)) {
                    $timestamp = strtotime((string) $appointment['appointment_date']);
                    if ($timestamp === false) {
                        throw new RuntimeException('Invalid appointment date.');
                    }

                    if (!isDoctorWithinDailyPatientLimit($doctorId, $timestamp, ['accepted', 'completed'])) {
                        throw new RuntimeException('Daily max patient limit reached. Cannot accept more patients.');
                    }
                }
            }

            jsondb_update_appointment($appointmentId, ['status' => $status]);
            flash('success', 'Appointment status updated.');
        } elseif ($action === 'save_prescription') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($appointmentId <= 0 || $description === '') {
                throw new RuntimeException('Prescription text is required.');
            }

            $appointment = jsondb_appointment_by_id($appointmentId);
            if (!$appointment || (int) $appointment['doctor_id'] !== $doctorId) {
                throw new RuntimeException('Appointment does not belong to this doctor.');
            }

            $currentStatus = (string) $appointment['status'];
            if (in_array($currentStatus, ['cancelled', 'rejected'], true)) {
                throw new RuntimeException('Cannot save prescription for cancelled or rejected appointment.');
            }

            jsondb_upsert_prescription($appointmentId, $description);
            flash('success', 'Prescription saved.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('doctor/appointments.php'));
}

$appointments = [];
if ($doctorId !== null) {
    $rows = jsondb_appointments_for_doctor($doctorId);

    foreach ($rows as $appointment) {
        $patient = jsondb_patient_by_id((int) $appointment['patient_id']);
        $prescription = jsondb_prescription_by_appointment((int) $appointment['id']);

        $appointments[] = [
            'id' => (int) $appointment['id'],
            'appointment_date' => $appointment['appointment_date'],
            'status' => $appointment['status'],
            'patient_name' => $patient['name'] ?? 'Unknown',
            'phone' => $patient['phone'] ?? '',
            'prescription' => $prescription['description'] ?? null,
        ];
    }
}

$csrf = generate_csrf_token();

render_app_header('My Appointments');
?>

<?php if ($doctorId === null): ?>
    <div class="alert alert-warning">
        Your doctor profile is not linked yet. Ask admin to create your doctor record with your login email.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$appointments): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No appointments assigned.</td>
                        </tr>
                    <?php endif; ?>

                    <?php $index = 1; ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $status = (string) $appointment['status'];
                        $locked = in_array($status, ['cancelled', 'rejected', 'completed'], true);
                        ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <td><?= e($appointment['patient_name']) ?></td>
                            <td><?= e($appointment['phone']) ?></td>
                            <td><?= e((string) $appointment['appointment_date']) ?></td>
                            <td>
                                <span class="badge badge-status">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$locked): ?>
                                    <form method="post" class="d-inline-flex gap-1">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">

                                        <select name="status" class="form-select form-select-sm">
                                            <?php foreach (['accepted', 'rejected', 'completed'] as $optionStatus): ?>
                                                <option value="<?= e($optionStatus) ?>" <?= $status === $optionStatus ? 'selected' : '' ?>>
                                                    <?= e(ucfirst($optionStatus)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Locked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="prescriptionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save_prescription">
                    <input type="hidden" name="appointment_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Prescription / Notes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="description" class="form-control" rows="5" required placeholder="Prescription and notes"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php render_app_footer(); ?>