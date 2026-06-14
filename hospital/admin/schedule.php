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
        redirect(url('admin/schedule.php'));
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update') {
            $doctorId = (int) ($_POST['doctor_id'] ?? 0);
            $scheduleMin = normalizeScheduleTime($_POST['schedule_min_time'] ?? '');
            $scheduleMax = normalizeScheduleTime($_POST['schedule_max_time'] ?? '');
            $minPatientsPerDay = (int) ($_POST['min_patients_per_day'] ?? 0);
            $maxPatientsPerDay = (int) ($_POST['max_patients_per_day'] ?? 0);

            if ($doctorId <= 0 || $scheduleMin === null || $scheduleMax === null) {
                throw new RuntimeException('Invalid schedule update payload.');
            }

            if ($scheduleMin >= $scheduleMax) {
                throw new RuntimeException('Schedule start time must be earlier than end time.');
            }

            if ($minPatientsPerDay <= 0 || $maxPatientsPerDay <= 0 || $minPatientsPerDay > $maxPatientsPerDay) {
                throw new RuntimeException('Patient limit must be valid and min cannot be greater than max.');
            }

            if (!jsondb_doctor_by_id($doctorId)) {
                throw new RuntimeException('Selected doctor does not exist.');
            }

            jsondb_update_doctor($doctorId, [
                'schedule_min_time' => $scheduleMin,
                'schedule_max_time' => $scheduleMax,
                'min_patients_per_day' => $minPatientsPerDay,
                'max_patients_per_day' => $maxPatientsPerDay,
            ]);

            flash('success', 'Doctor schedule updated successfully.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('admin/schedule.php'));
}

$today = date('Y-m-d');
$doctors = jsondb_doctors_with_today_usage($today);

$editDoctor = null;
$editDoctorId = max(0, (int) ($_GET['edit_id'] ?? 0));
if ($editDoctorId > 0) {
    $editDoctor = jsondb_doctor_by_id($editDoctorId) ?: null;

    if ($editDoctor === null) {
        flash('error', 'Doctor not found for schedule edit.');
        redirect(url('admin/schedule.php'));
    }
}

$csrf = generate_csrf_token();

render_app_header('Manage Schedule');
?>
<?php if ($editDoctor): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Update Doctor Schedule: <?= e($editDoctor['name']) ?></h5>
            <a href="<?= e(url('admin/schedule.php')) ?>" class="btn btn-sm btn-light">Cancel</a>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="doctor_id" value="<?= e((string) $editDoctor['id']) ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Schedule Start</label>
                        <input type="time" name="schedule_min_time" class="form-control" value="<?= e(substr((string) $editDoctor['schedule_min_time'], 0, 5)) ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Schedule End</label>
                        <input type="time" name="schedule_max_time" class="form-control" value="<?= e(substr((string) $editDoctor['schedule_max_time'], 0, 5)) ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Min Patients</label>
                        <input type="number" name="min_patients_per_day" class="form-control" min="1" value="<?= e((string) $editDoctor['min_patients_per_day']) ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Max Patients</label>
                        <input type="number" name="max_patients_per_day" class="form-control" min="1" value="<?= e((string) $editDoctor['max_patients_per_day']) ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Update Schedule</button>
            </form>
        </div>
    </div>
<?php endif; ?>
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Doctor Schedule</h5>
        <small class="text-muted">Date: <?= e($today) ?></small>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Doctor</th>
                    <th>Specialty</th>
                    <th>Schedule</th>
                    <th>Patient Limit</th>
                    <th>Today Token</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$doctors): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No doctors found.</td>
                    </tr>
                <?php endif; ?>
                <?php $index = 1; ?>
                <?php foreach ($doctors as $doctor): ?>
                    <?php
                    $bookedCount = (int) $doctor['booked_count'];
                    $maxPatients = (int) $doctor['max_patients_per_day'];
                    $isFull = $bookedCount >= $maxPatients;
                    ?>
                    <tr>
                        <td><?= $index++ ?></td>
                        <!-- <td><?= e((string) $doctor['id']) ?></td> -->
                        <td><?= e($doctor['name']) ?></td>
                        <td><?= e($doctor['specialty']) ?></td>
                        <td><?= e(formatTime12Hour((string) $doctor['schedule_min_time'])) ?> - <?= e(formatTime12Hour((string) $doctor['schedule_max_time'])) ?></td>
                        <td><?= e((string) $doctor['min_patients_per_day']) ?> - <?= e((string) $doctor['max_patients_per_day']) ?></td>
                        <td><?= e((string) $bookedCount) ?> / <?= e((string) $maxPatients) ?></td>
                        <td>
                            <?php if ($isFull): ?>
                                <span class="badge text-bg-danger">Complete</span>
                            <?php else: ?>
                                <span class="badge text-bg-success">Available</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e(url('admin/schedule.php?edit_id=' . (int) $doctor['id'])) ?>" class="btn btn-sm btn-outline-primary">
                                Edit Schedule
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_app_footer(); ?>
