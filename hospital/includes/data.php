<?php
declare(strict_types=1);

function getDoctorIdByUserId(int $userId): ?int
{
    $user = jsondb_user_by_id($userId);
    if (!$user || (string) $user['role'] !== 'doctor') {
        return null;
    }

    $doctor = jsondb_doctor_by_email((string) $user['email']);
    return $doctor ? (int) $doctor['id'] : null;
}

function getPatientIdByUserId(int $userId): ?int
{
    $user = jsondb_user_by_id($userId);
    if (!$user || (string) $user['role'] !== 'patient') {
        return null;
    }

    $patient = jsondb_patient_by_email((string) $user['email']);
    return $patient ? (int) $patient['id'] : null;
}

function getDoctorScheduleById(int $doctorId): ?array
{
    $doctor = jsondb_doctor_by_id($doctorId);
    if (!$doctor) {
        return null;
    }

    return [
        'min' => (string) $doctor['schedule_min_time'],
        'max' => (string) $doctor['schedule_max_time'],
    ];
}

function isAppointmentWithinDoctorSchedule(int $doctorId, int $timestamp): bool
{
    $schedule = getDoctorScheduleById($doctorId);
    if ($schedule === null) {
        return false;
    }

    $appointmentTime = date('H:i:s', $timestamp);
    return $appointmentTime >= $schedule['min'] && $appointmentTime <= $schedule['max'];
}

function getDoctorPatientLimitsById(int $doctorId): ?array
{
    $doctor = jsondb_doctor_by_id($doctorId);
    if (!$doctor) {
        return null;
    }

    return [
        'min' => max(1, (int) $doctor['min_patients_per_day']),
        'max' => max(1, (int) $doctor['max_patients_per_day']),
    ];
}

function getDoctorDailyAppointmentCount(int $doctorId, string $dateYmd, array $statuses): int
{
    if ($statuses === []) {
        return 0;
    }

    $count = 0;
    foreach (jsondb()->table('appointments') as $appointment) {
        if ((int) $appointment['doctor_id'] !== $doctorId) {
            continue;
        }
        $status = (string) $appointment['status'];
        if (!in_array($status, $statuses, true)) {
            continue;
        }
        $date = date('Y-m-d', strtotime((string) $appointment['appointment_date']));
        if ($date === $dateYmd) {
            $count++;
        }
    }

    return $count;
}

function isDoctorWithinDailyPatientLimit(int $doctorId, int $timestamp, array $statuses): bool
{
    $limits = getDoctorPatientLimitsById($doctorId);
    if ($limits === null) {
        return false;
    }

    if ($limits['max'] < $limits['min']) {
        return false;
    }

    $appointmentDay = date('Y-m-d', $timestamp);
    $count = getDoctorDailyAppointmentCount($doctorId, $appointmentDay, $statuses);

    return $count < $limits['max'];
}

function getDoctorAvailabilityForDate(int $doctorId, string $dateYmd): ?array
{
    $limits = getDoctorPatientLimitsById($doctorId);
    if ($limits === null) {
        return null;
    }

    $bookedCount = getDoctorDailyAppointmentCount($doctorId, $dateYmd, ['pending', 'approved', 'accepted', 'completed']);
    $maxPatients = max(1, (int) $limits['max']);
    $isFull = $bookedCount >= $maxPatients;

    return [
        'booked' => $bookedCount,
        'min' => (int) $limits['min'],
        'max' => $maxPatients,
        'next_token' => $bookedCount + 1,
        'is_full' => $isFull,
        'status' => $isFull ? 'complete' : 'available',
    ];
}

// function getAppointmentTokenNo(int $appointmentId): ?int
// {
//     $appointment = jsondb_appointment_by_id($appointmentId);
//     if (!$appointment) {
//         return null;
//     }

//     $tokenStatuses = ['pending', 'approved', 'accepted', 'completed'];
//     $status = (string) $appointment['status'];
//     if (!in_array($status, $tokenStatuses, true)) {
//         return null;
//     }

//     $dateYmd = date('Y-m-d', strtotime((string) $appointment['appointment_date']));
//     $doctorId = (int) $appointment['doctor_id'];

//     $count = 0;
//     foreach (jsondb()->table('appointments') as $row) {
//         if ((int) $row['doctor_id'] !== $doctorId) {
//             continue;
//         }
//         $rowDate = date('Y-m-d', strtotime((string) $row['appointment_date']));
//         if ($rowDate !== $dateYmd) {
//             continue;
//         }
//         if ((int) $row['id'] > $appointmentId) {
//             continue;
//         }
//         if (!in_array((string) $row['status'], $tokenStatuses, true)) {
//             continue;
//         }
//         $count++;
//     }

//     return $count;
// }

function getAppointmentTokenNo(int $appointmentId): ?int
{
    $appointment = jsondb_appointment_by_id($appointmentId);
    if (!$appointment) {
        return null;
    }

    $tokenStatuses = ['pending', 'approved', 'accepted', 'completed'];
    $status = (string) $appointment['status'];
    if (!in_array($status, $tokenStatuses, true)) {
        return null;
    }

    $dateYmd = date('Y-m-d', strtotime((string) $appointment['appointment_date']));
    $doctorId = (int) $appointment['doctor_id'];

    $sameDayAppointments = [];
    foreach (jsondb()->table('appointments') as $row) {
        if ((int) $row['doctor_id'] !== $doctorId) {
            continue;
        }

        if (!in_array((string) $row['status'], $tokenStatuses, true)) {
            continue;
        }

        $rowDate = date('Y-m-d', strtotime((string) $row['appointment_date']));
        if ($rowDate !== $dateYmd) {
            continue;
        }

        $sameDayAppointments[] = $row;
    }

    usort($sameDayAppointments, static function (array $a, array $b): int {
        $dateCompare = strcmp((string) $a['appointment_date'], (string) $b['appointment_date']);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return (int) $a['id'] <=> (int) $b['id'];
    });

    foreach ($sameDayAppointments as $index => $row) {
        if ((int) $row['id'] === $appointmentId) {
            return $index + 1;
        }
    }

    return null;
}

function formatTime12Hour(?string $time): string
{
    if ($time === null || trim($time) === '') {
        return '';
    }

    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return (string) $time;
    }

    return date('g:i A', $timestamp);
}
