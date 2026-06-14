<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('admin');

$totalDoctors = jsondb_doctors_count();
$totalPatients = jsondb_patients_count();
$totalAppointments = jsondb_appointments_count();
$pendingAppointments = jsondb_appointments_count(fn(array $appointment): bool => (string) $appointment['status'] === 'pending');

render_app_header('Admin Dashboard');
?>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Total Doctors</div>
                <h3 class="mb-0"><?= e((string) $totalDoctors) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Total Patients</div>
                <h3 class="mb-0"><?= e((string) $totalPatients) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Total Appointments</div>
                <h3 class="mb-0"><?= e((string) $totalAppointments) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="text-muted mb-1">Pending Appointments</div>
                <h3 class="mb-0"><?= e((string) $pendingAppointments) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title">Quick Actions</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(url('admin/doctors.php')) ?>" class="btn btn-primary btn-sm ">Manage Doctors</a>
                    <a href="<?= e(url('admin/schedule.php')) ?>" class="btn btn-outline-info btn-sm">Manage Schedule</a>
                    <a href="<?= e(url('admin/patients.php')) ?>" class="btn btn-outline-primary btn-sm">Manage Patients</a>
                    <a href="<?= e(url('admin/appointments.php')) ?>" class="btn btn-outline-dark btn-sm">Review Appointments</a>
                    <a href="<?= e(url('admin/json_import.php')) ?>" class="btn btn-outline-success btn-sm">JSON Storage</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title">System Notes</h5>
                <ul class="mb-0">
                    <li>Doctors created here automatically get login access.</li>
                    <li>Patients can self-register from the registration page.</li>
                    <li>Approve or reject incoming appointments from the appointments module.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php render_app_footer(); ?>
