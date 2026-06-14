<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('patient');

$user = current_user();
$patientId = getPatientIdByUserId((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('patient/book_appointment.php'));
    }

    if ($patientId === null) {
        flash('error', 'Patient profile not linked.');
        redirect(url('patient/book_appointment.php'));
    }

    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $dateRaw = trim($_POST['appointment_date'] ?? '');

    if ($doctorId <= 0 || $dateRaw === '') {
        flash('error', 'Doctor and appointment date are required.');
        redirect(url('patient/book_appointment.php'));
    }

    $timestamp = strtotime($dateRaw);
    if ($timestamp === false || $timestamp < time()) {
        flash('error', 'Please choose a valid future date and time.');
        redirect(url('patient/book_appointment.php'));
    }

    if (!jsondb_doctor_by_id($doctorId)) {
        flash('error', 'Selected doctor does not exist.');
        redirect(url('patient/book_appointment.php'));
    }

    if (!isAppointmentWithinDoctorSchedule($doctorId, $timestamp)) {
        flash('error', 'Selected time is outside doctor schedule range.');
        redirect(url('patient/book_appointment.php'));
    }

    if (!isDoctorWithinDailyPatientLimit($doctorId, $timestamp, ['pending', 'approved', 'accepted', 'completed'])) {
        flash('error', 'Doctor has reached max patient limit for this day. Please choose another date.');
        redirect(url('patient/book_appointment.php'));
    }

    $appointmentDate = date('Y-m-d H:i:s', $timestamp);
    $tokenDate = date('Y-m-d', $timestamp);
    $tokenNo = getDoctorDailyAppointmentCount($doctorId, $tokenDate, ['pending', 'approved', 'accepted', 'completed']) + 1;

    jsondb_create_appointment([
        'patient_id' => $patientId,
        'doctor_id' => $doctorId,
        'appointment_date' => $appointmentDate,
        'status' => 'pending',
    ]);

    flash('success', 'Appointment booked successfully. Token No: ' . $tokenNo . '. Waiting for approval.');
    redirect(url('patient/appointments.php'));
}

$today = date('Y-m-d');
$doctors = jsondb_doctors_with_today_usage($today);

$csrf = generate_csrf_token();

render_app_header('Book Appointment');
?>
<?php if ($patientId === null): ?>
    <div class="alert alert-warning">
        Your patient profile is not linked yet. Please contact admin.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label">Select Doctor</label>
                    <select name="doctor_id" class="form-select" required>
                        <option value="">Choose doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <?php
                            $bookedCount = (int) $doctor['booked_count'];
                            $maxPatients = max(1, (int) $doctor['max_patients_per_day']);
                            $isFull = $bookedCount >= $maxPatients;
                            $availabilityLabel = $isFull ? 'complete' : 'available';
                            ?>
                            <option value="<?= e((string) $doctor['id']) ?>">
                                <?= e($doctor['name']) ?> (<?= e($doctor['specialty']) ?>, <?= e(formatTime12Hour((string) $doctor['schedule_min_time'])) ?>-<?= e(formatTime12Hour((string) $doctor['schedule_max_time'])) ?>, token <?= e((string) $bookedCount) ?>/<?= e((string) $maxPatients) ?>, <?= e($availabilityLabel) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Doctor status in the dropdown is based on today (<?= e($today) ?>).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Appointment Date & Time</label>
                    <input type="datetime-local" name="appointment_date" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Book Appointment</button>
                            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Today Doctor Availability</h5>
            <small class="text-muted"><?= e($today) ?></small>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Schedule</th>
                        <th>Token</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$doctors): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No doctors available.</td>
                        </tr>
                    <?php endif; ?>
                    <?php $index = 1; ?>
                    <?php foreach ($doctors as $doctor): ?>
                        <?php
                        $bookedCount = (int) $doctor['booked_count'];
                        $maxPatients = max(1, (int) $doctor['max_patients_per_day']);
                        $isFull = $bookedCount >= $maxPatients;
                        ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <!-- <td><?= e((string) $doctor['id']) ?></td> -->
                            <td><?= e($doctor['name']) ?></td>
                            <td><?= e($doctor['specialty']) ?></td>
                            <td><?= e(formatTime12Hour((string) $doctor['schedule_min_time'])) ?> - <?= e(formatTime12Hour((string) $doctor['schedule_max_time'])) ?></td>
                            <td><?= e((string) $bookedCount) ?> / <?= e((string) $maxPatients) ?> (Next <?= e((string) ($bookedCount + 1)) ?>)</td>
                            <td>
                                <?php if ($isFull): ?>
                                    <span class="badge text-bg-danger">Complete</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Available</span>
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
