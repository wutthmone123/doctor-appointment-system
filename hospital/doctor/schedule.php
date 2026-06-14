<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('doctor');

$user = current_user();
$doctorId = getDoctorIdByUserId((int) $user['id']);
$doctorProfile = null;

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
        redirect(url('doctor/schedule.php'));
    }

    if ($doctorId === null) {
        flash('error', 'Doctor profile not linked.');
        redirect(url('doctor/schedule.php'));
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_schedule') {
            $scheduleMin = normalizeScheduleTime($_POST['schedule_min_time'] ?? '');
            $scheduleMax = normalizeScheduleTime($_POST['schedule_max_time'] ?? '');
            $minPatientsPerDay = (int) ($_POST['min_patients_per_day'] ?? 0);
            $maxPatientsPerDay = (int) ($_POST['max_patients_per_day'] ?? 0);

            if ($scheduleMin === null || $scheduleMax === null) {
                throw new RuntimeException('Schedule start/end time is required.');
            }

            if ($scheduleMin >= $scheduleMax) {
                throw new RuntimeException('Schedule start time must be earlier than end time.');
            }

            if ($minPatientsPerDay <= 0 || $maxPatientsPerDay <= 0 || $minPatientsPerDay > $maxPatientsPerDay) {
                throw new RuntimeException('Patient limit must be valid and min cannot be greater than max.');
            }

            jsondb_update_doctor($doctorId, [
                'schedule_min_time' => $scheduleMin,
                'schedule_max_time' => $scheduleMax,
                'min_patients_per_day' => $minPatientsPerDay,
                'max_patients_per_day' => $maxPatientsPerDay,
            ]);

            flash('success', 'Schedule and patient limit updated.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('doctor/schedule.php'));
}

$schedule = [];
$todayUsage = null;

if ($doctorId !== null) {
    $doctorProfile = jsondb_doctor_by_id($doctorId) ?: null;
    $appointments = jsondb_appointments_for_doctor($doctorId);
    foreach ($appointments as $appointment) {
        $status = (string) $appointment['status'];
        if (!in_array($status, ['pending', 'approved', 'accepted', 'completed'], true)) {
            continue;
        }
        $patient = jsondb_patient_by_id((int) $appointment['patient_id']);
        $schedule[] = [
            'id' => (int) $appointment['id'],
            'appointment_date' => $appointment['appointment_date'],
            'status' => $status,
            'patient_name' => $patient['name'] ?? 'Unknown',
            'phone' => $patient['phone'] ?? '',
            'token_no' => getAppointmentTokenNo((int) $appointment['id']),
        ];
    }

    $todayUsage = getDoctorAvailabilityForDate($doctorId, date('Y-m-d'));
}

$csrf = generate_csrf_token();

render_app_header('My Schedule');
?>
<?php if ($doctorId === null): ?>
    <div class="alert alert-warning">
        Your doctor profile is not linked yet. Ask admin to create your doctor record with your login email.
    </div>
<?php else: ?>
    <?php if ($doctorProfile): ?>
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Update Schedule</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="update_schedule">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Schedule Min</label>
                                    <input type="time" name="schedule_min_time" class="form-control" value="<?= e(substr((string) $doctorProfile['schedule_min_time'], 0, 5)) ?>" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Schedule Max</label>
                                    <input type="time" name="schedule_max_time" class="form-control" value="<?= e(substr((string) $doctorProfile['schedule_max_time'], 0, 5)) ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Min Patients</label>
                                    <input type="number" name="min_patients_per_day" class="form-control" min="1" value="<?= e((string) $doctorProfile['min_patients_per_day']) ?>" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Max Patients</label>
                                    <input type="number" name="max_patients_per_day" class="form-control" min="1" value="<?= e((string) $doctorProfile['max_patients_per_day']) ?>" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Schedule</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Today Token Status</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            Working schedule:
                            <strong><?= e(formatTime12Hour((string) $doctorProfile['schedule_min_time'])) ?> - <?= e(formatTime12Hour((string) $doctorProfile['schedule_max_time'])) ?></strong>
                        </p>
                        <?php if ($todayUsage): ?>
                            <p class="mb-2">Token usage: <strong><?= e((string) $todayUsage['booked']) ?> / <?= e((string) $todayUsage['max']) ?></strong></p>
                            <p class="mb-2">Next token: <strong><?= e((string) $todayUsage['next_token']) ?></strong></p>
                            <div>
                                <?php if ($todayUsage['is_full']): ?>
                                    <span class="badge text-bg-danger">Complete</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Available</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Appointment Queue</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Token</th>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$schedule): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No scheduled appointments.</td>
                        </tr>
                    <?php endif; ?>
                    <?php $index = 1; ?>
                    <?php foreach ($schedule as $item): ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <!-- <td><?= e((string) $item['id']) ?></td> -->
                            <td><?= e((string) ($item['token_no'] ?? '-')) ?></td>
                            <td><?= e($item['patient_name']) ?></td>
                            <td><?= e($item['phone']) ?></td>
                            <td><?= e((string) $item['appointment_date']) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e(ucfirst((string) $item['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php render_app_footer(); ?>
