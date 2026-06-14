<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('patient');

$user = current_user();
$patientId = getPatientIdByUserId((int) $user['id']);

$totalAppointments = 0;
$pendingAppointments = 0;
$completedAppointments = 0;
$doctorCount = jsondb_doctors_count();

if ($patientId !== null) {
    $appointments = jsondb_appointments_for_patient($patientId);
    $totalAppointments = count($appointments);
    foreach ($appointments as $appointment) {
        $status = (string) $appointment['status'];
        if (in_array($status, ['pending', 'approved', 'accepted'], true)) {
            $pendingAppointments++;
        }
        if ($status === 'completed') {
            $completedAppointments++;
        }
    }
}

render_app_header('Patient Dashboard');
?>
<?php if ($patientId === null): ?>
    <div class="alert alert-warning">
        Your patient profile is not linked yet. Please contact admin.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Available Doctors</div>
                <h3 class="mb-0"><?= e((string) $doctorCount) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">My Appointments</div>
                <h3 class="mb-0"><?= e((string) $totalAppointments) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Active Requests</div>
                <h3 class="mb-0"><?= e((string) $pendingAppointments) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Completed Visits</div>
                <h3 class="mb-0"><?= e((string) $completedAppointments) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="card-title">Quick Actions</h5>
        <a href="<?= e(url('patient/book_appointment.php')) ?>" class="btn btn-primary btn-sm me-2">Book Appointment</a>
        <a href="<?= e(url('patient/appointments.php')) ?>" class="btn btn-outline-primary btn-sm">View Status</a>
        <!-- <a href="<?= e(url('patient/prescriptions.php')) ?>" class="btn btn-outline-dark btn-sm">View Prescriptions</a> -->
    </div>
</div>
<?php render_app_footer(); ?>
