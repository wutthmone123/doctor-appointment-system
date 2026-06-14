<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('patient');

$user = current_user();
$patientId = getPatientIdByUserId((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('patient/appointments.php'));
    }

    if ($patientId === null) {
        flash('error', 'Patient profile not linked.');
        redirect(url('patient/appointments.php'));
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'cancel') {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        if ($appointmentId > 0) {
            $appointment = jsondb_appointment_by_id($appointmentId);
            if ($appointment && (int) $appointment['patient_id'] === $patientId) {
                $status = (string) $appointment['status'];
                if (in_array($status, ['pending', 'approved', 'accepted'], true)) {
                    jsondb_update_appointment($appointmentId, ['status' => 'cancelled']);
                    flash('success', 'Appointment cancelled.');
                }
            }
        }
    }

    redirect(url('patient/appointments.php'));
}

$appointments = [];
if ($patientId !== null) {
    $rows = jsondb_appointments_for_patient($patientId);
    foreach ($rows as $appointment) {
        $doctor = jsondb_doctor_by_id((int) $appointment['doctor_id']);
        $prescription = jsondb_prescription_by_appointment((int) $appointment['id']);
        $appointments[] = [
            'id' => (int) $appointment['id'],
            'appointment_date' => $appointment['appointment_date'],
            'status' => $appointment['status'],
            'doctor_name' => $doctor['name'] ?? 'Unknown',
            'specialty' => $doctor['specialty'] ?? '',
            'prescription' => $prescription['description'] ?? null,
            'token_no' => getAppointmentTokenNo((int) $appointment['id']),
        ];
    }
}

$csrf = generate_csrf_token();

render_app_header('My Appointments');
?>
<?php if ($patientId === null): ?>
    <div class="alert alert-warning">
        Your patient profile is not linked yet. Please contact admin.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Date</th>
                        <th>Token</th>
                        <th>Status</th>
                        
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$appointments): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No appointments found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php $index = 1; ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <!-- <td><?= e((string) $appointment['id']) ?></td> -->
                            <td><?= e($appointment['doctor_name']) ?></td>
                            <td><?= e($appointment['specialty']) ?></td>
                            <td><?= e((string) $appointment['appointment_date']) ?></td>
                            <td><?= e((string) ($appointment['token_no'] ?? '-')) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e(ucfirst((string) $appointment['status'])) ?></span></td>
                            <td>
                                <?php if (in_array($appointment['status'], ['pending', 'approved', 'accepted'], true)): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this appointment?')">
                                            Cancel
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php render_app_footer(); ?>
