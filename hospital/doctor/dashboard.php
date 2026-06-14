<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('doctor');

$user = current_user();
$doctorId = getDoctorIdByUserId((int) $user['id']);

$totalAssigned = 0;
$pendingAssigned = 0;
$completedAssigned = 0;

if ($doctorId !== null) {
    $appointments = jsondb_appointments_for_doctor($doctorId);
    $totalAssigned = count($appointments);
    foreach ($appointments as $appointment) {
        $status = (string) $appointment['status'];
        if (in_array($status, ['pending', 'approved'], true)) {
            $pendingAssigned++;
        }
        if ($status === 'completed') {
            $completedAssigned++;
        }
    }
}

render_app_header('Doctor Dashboard');
?>
<?php if ($doctorId === null): ?>
    <div class="alert alert-warning">
        Your doctor profile is not linked yet. Ask an admin to add your doctor record with your login email.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Assigned Appointments</div>
                <h3 class="mb-0"><?= e((string) $totalAssigned) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Pending</div>
                <h3 class="mb-0"><?= e((string) $pendingAssigned) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Completed</div>
                <h3 class="mb-0"><?= e((string) $completedAssigned) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="card-title">Quick Actions</h5>
        <a href="<?= e(url('doctor/appointments.php')) ?>" class="btn btn-primary btn-sm me-2">View Appointments</a>
        <a href="<?= e(url('doctor/schedule.php')) ?>" class="btn btn-outline-primary btn-sm">View Schedule</a>
    </div>
</div>
<?php render_app_footer(); ?>
