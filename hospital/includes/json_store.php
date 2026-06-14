<?php
declare(strict_types=1);

final class JsonStore
{
    private string $path;
    private array $data;
    private bool $dirty = false;
    private bool $inTransaction = false;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->data = $this->loadOrSeed();
        $this->ensureDefaults();
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->inTransaction) {
            return $callback($this);
        }

        $this->inTransaction = true;
        $snapshot = $this->data;
        $dirtySnapshot = $this->dirty;

        try {
            $result = $callback($this);
            $this->inTransaction = false;
            $this->save();
            return $result;
        } catch (Throwable $e) {
            $this->data = $snapshot;
            $this->dirty = $dirtySnapshot;
            $this->inTransaction = false;
            throw $e;
        }
    }

    public function &table(string $name): array
    {
        if (!isset($this->data['tables'][$name]) || !is_array($this->data['tables'][$name])) {
            $this->data['tables'][$name] = [];
        }

        return $this->data['tables'][$name];
    }

    public function nextId(string $table): int
    {
        if (!isset($this->data['counters'][$table])) {
            $this->data['counters'][$table] = 0;
        }

        $this->data['counters'][$table] = (int) $this->data['counters'][$table] + 1;
        $this->dirty = true;
        return (int) $this->data['counters'][$table];
    }

    public function markDirty(): void
    {
        $this->dirty = true;
    }

    public function save(): void
    {
        if (!$this->dirty) {
            return;
        }

        $this->data['meta']['updated_at'] = date('Y-m-d H:i:s');

        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create storage directory.');
        }

        $fp = fopen($this->path, 'c+');
        if ($fp === false) {
            throw new RuntimeException('Unable to open storage file.');
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new RuntimeException('Unable to lock storage file.');
        }

        ftruncate($fp, 0);
        rewind($fp);
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $this->dirty = false;
    }

    private function loadOrSeed(): array
    {
        if (!is_file($this->path)) {
            return $this->seed();
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            return $this->seed();
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $this->seed();
        }

        return $this->normalize($data);
    }

    private function seed(): array
    {
        $now = date('Y-m-d H:i:s');
        return [
            'meta' => [
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'counters' => [
                'users' => 0,
                'doctors' => 0,
                'patients' => 0,
                'appointments' => 0,
                'prescriptions' => 0,
            ],
            'tables' => [
                'users' => [],
                'doctors' => [],
                'patients' => [],
                'appointments' => [],
                'prescriptions' => [],
            ],
        ];
    }

    private function normalize(array $data): array
    {
        $tables = $data['tables'] ?? [];
        if (!is_array($tables)) {
            $tables = [];
        }

        $requiredTables = ['users', 'doctors', 'patients', 'appointments', 'prescriptions'];
        foreach ($requiredTables as $table) {
            if (!isset($tables[$table]) || !is_array($tables[$table])) {
                $tables[$table] = [];
            }
        }

        $counters = $data['counters'] ?? [];
        if (!is_array($counters)) {
            $counters = [];
        }

        foreach ($requiredTables as $table) {
            $maxId = 0;
            foreach ($tables[$table] as $row) {
                if (isset($row['id'])) {
                    $maxId = max($maxId, (int) $row['id']);
                }
            }
            $current = (int) ($counters[$table] ?? 0);
            $counters[$table] = max($current, $maxId);
        }

        $meta = $data['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $now = date('Y-m-d H:i:s');
        $meta['version'] = (int) ($meta['version'] ?? 1);
        $meta['created_at'] = (string) ($meta['created_at'] ?? $now);
        $meta['updated_at'] = (string) ($meta['updated_at'] ?? $now);

        return [
            'meta' => $meta,
            'counters' => $counters,
            'tables' => $tables,
        ];
    }

    private function ensureDefaults(): void
    {
        $adminEmail = 'admin@hospital.com';
        $users = $this->table('users');
        foreach ($users as $user) {
            if (strtolower((string) ($user['email'] ?? '')) === $adminEmail) {
                return;
            }
        }

        $this->table('users')[] = [
            'id' => $this->nextId('users'),
            'name' => 'System Admin',
            'email' => $adminEmail,
            'password' => password_hash('Admin@123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->markDirty();
        $this->save();
    }
}

function jsondb_path(): string
{
    return __DIR__ . '/../storage/jsondb/hospital.json';
}

function jsondb(): JsonStore
{
    static $store = null;
    if (!$store instanceof JsonStore) {
        $store = new JsonStore(jsondb_path());
    }
    return $store;
}

function jsondb_transaction(callable $callback): mixed
{
    return jsondb()->transaction($callback);
}

function jsondb_commit_if_needed(): void
{
    $store = jsondb();
    if (!$store->inTransaction()) {
        $store->save();
    }
}

function jsondb_now(): string
{
    return date('Y-m-d H:i:s');
}

function jsondb_user_by_email(string $email): ?array
{
    $email = strtolower($email);
    foreach (jsondb()->table('users') as $user) {
        if (strtolower((string) $user['email']) === $email) {
            return $user;
        }
    }
    return null;
}

function jsondb_user_by_id(int $id): ?array
{
    foreach (jsondb()->table('users') as $user) {
        if ((int) $user['id'] === $id) {
            return $user;
        }
    }
    return null;
}

function jsondb_user_email_exists(string $email, ?int $excludeUserId = null): bool
{
    $email = strtolower($email);
    foreach (jsondb()->table('users') as $user) {
        if (strtolower((string) $user['email']) === $email) {
            if ($excludeUserId === null || (int) $user['id'] !== $excludeUserId) {
                return true;
            }
        }
    }
    return false;
}

function jsondb_create_user(string $name, string $email, string $passwordHash, string $role): array
{
    $store = jsondb();
    $users =& $store->table('users');
    $row = [
        'id' => $store->nextId('users'),
        'name' => $name,
        'email' => strtolower($email),
        'password' => $passwordHash,
        'role' => $role,
        'created_at' => jsondb_now(),
    ];
    $users[] = $row;
    $store->markDirty();
    jsondb_commit_if_needed();
    return $row;
}

function jsondb_update_user_by_email(string $email, array $data): bool
{
    $email = strtolower($email);
    $store = jsondb();
    $users =& $store->table('users');
    foreach ($users as &$user) {
        if (strtolower((string) $user['email']) === $email) {
            $user = array_merge($user, $data);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_update_user_by_id(int $id, array $data): bool
{
    $store = jsondb();
    $users =& $store->table('users');
    foreach ($users as &$user) {
        if ((int) $user['id'] === $id) {
            $user = array_merge($user, $data);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_delete_user_by_email_role(string $email, string $role): bool
{
    $email = strtolower($email);
    $store = jsondb();
    $users =& $store->table('users');
    foreach ($users as $index => $user) {
        if (strtolower((string) $user['email']) === $email && (string) $user['role'] === $role) {
            array_splice($users, $index, 1);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_delete_user_by_id_and_role(int $id, string $role): bool
{
    $store = jsondb();
    $users =& $store->table('users');
    foreach ($users as $index => $user) {
        if ((int) $user['id'] === $id && (string) $user['role'] === $role) {
            array_splice($users, $index, 1);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_doctor_by_id(int $id): ?array
{
    foreach (jsondb()->table('doctors') as $doctor) {
        if ((int) $doctor['id'] === $id) {
            return $doctor;
        }
    }
    return null;
}

function jsondb_doctor_by_email(string $email): ?array
{
    $email = strtolower($email);
    foreach (jsondb()->table('doctors') as $doctor) {
        if (strtolower((string) $doctor['email']) === $email) {
            return $doctor;
        }
    }
    return null;
}

function jsondb_doctors_count(): int
{
    return count(jsondb()->table('doctors'));
}

function jsondb_doctors_paginated(int $offset, int $limit): array
{
    $doctors = jsondb()->table('doctors');
    usort($doctors, fn(array $a, array $b) => (int) $a['id'] <=> (int) $b['id']);
    return array_slice($doctors, $offset, $limit);
}

function jsondb_doctors_sorted_by_name(): array
{
    $doctors = jsondb()->table('doctors');
    usort($doctors, fn(array $a, array $b) => strcasecmp((string) $a['name'], (string) $b['name']));
    return $doctors;
}

function jsondb_create_doctor(array $data): array
{
    $store = jsondb();
    $doctors =& $store->table('doctors');
    $row = [
        'id' => $store->nextId('doctors'),
        'name' => $data['name'],
        'email' => strtolower((string) $data['email']),
        'phone' => (string) $data['phone'],
        'address' => (string) $data['address'],
        'specialty' => (string) $data['specialty'],
        'schedule_min_time' => (string) ($data['schedule_min_time'] ?? '09:00:00'),
        'schedule_max_time' => (string) ($data['schedule_max_time'] ?? '17:00:00'),
        'min_patients_per_day' => (int) ($data['min_patients_per_day'] ?? 1),
        'max_patients_per_day' => (int) ($data['max_patients_per_day'] ?? 30),
        'created_at' => jsondb_now(),
    ];
    $doctors[] = $row;
    $store->markDirty();
    jsondb_commit_if_needed();
    return $row;
}

function jsondb_update_doctor(int $id, array $data): bool
{
    $store = jsondb();
    $doctors =& $store->table('doctors');
    foreach ($doctors as &$doctor) {
        if ((int) $doctor['id'] === $id) {
            $doctor = array_merge($doctor, $data);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_delete_doctor(int $id): bool
{
    $store = jsondb();
    $doctors =& $store->table('doctors');
    foreach ($doctors as $index => $doctor) {
        if ((int) $doctor['id'] === $id) {
            array_splice($doctors, $index, 1);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_patient_by_id(int $id): ?array
{
    foreach (jsondb()->table('patients') as $patient) {
        if ((int) $patient['id'] === $id) {
            return $patient;
        }
    }
    return null;
}

function jsondb_patient_by_email(string $email): ?array
{
    $email = strtolower($email);
    foreach (jsondb()->table('patients') as $patient) {
        if (strtolower((string) $patient['email']) === $email) {
            return $patient;
        }
    }
    return null;
}

function jsondb_patients_count(): int
{
    return count(jsondb()->table('patients'));
}

function jsondb_patients_paginated(int $offset, int $limit): array
{
    $patients = jsondb()->table('patients');
    usort($patients, fn(array $a, array $b) => (int) $a['id'] <=> (int) $b['id']);
    return array_slice($patients, $offset, $limit);
}

function jsondb_patients_sorted_by_name(): array
{
    $patients = jsondb()->table('patients');
    usort($patients, fn(array $a, array $b) => strcasecmp((string) $a['name'], (string) $b['name']));
    return $patients;
}

function jsondb_create_patient(array $data): array
{
    $store = jsondb();
    $patients =& $store->table('patients');
    $row = [
        'id' => $store->nextId('patients'),
        'name' => (string) $data['name'],
        'email' => strtolower((string) $data['email']),
        'phone' => (string) $data['phone'],
        'created_at' => jsondb_now(),
    ];
    $patients[] = $row;
    $store->markDirty();
    jsondb_commit_if_needed();
    return $row;
}

function jsondb_update_patient(int $id, array $data): bool
{
    $store = jsondb();
    $patients =& $store->table('patients');
    foreach ($patients as &$patient) {
        if ((int) $patient['id'] === $id) {
            $patient = array_merge($patient, $data);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_delete_patient(int $id): bool
{
    $store = jsondb();
    $patients =& $store->table('patients');
    foreach ($patients as $index => $patient) {
        if ((int) $patient['id'] === $id) {
            array_splice($patients, $index, 1);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_appointment_by_id(int $id): ?array
{
    foreach (jsondb()->table('appointments') as $appointment) {
        if ((int) $appointment['id'] === $id) {
            return $appointment;
        }
    }
    return null;
}

function jsondb_appointments_count(?callable $filter = null): int
{
    $count = 0;
    foreach (jsondb()->table('appointments') as $appointment) {
        if ($filter && !$filter($appointment)) {
            continue;
        }
        $count++;
    }
    return $count;
}

function jsondb_appointments_all_sorted(): array
{
    $appointments = jsondb()->table('appointments');
    usort($appointments, fn(array $a, array $b) => strcmp((string) $a['appointment_date'], (string) $b['appointment_date']));
    return $appointments;
}

// function jsondb_appointments_for_doctor(int $doctorId): array
// {
//     $appointments = [];
//     foreach (jsondb()->table('appointments') as $appointment) {
//         if ((int) $appointment['doctor_id'] === $doctorId) {
//             $appointments[] = $appointment;
//         }
//     }
//     usort($appointments, fn(array $a, array $b) => strcmp((string) $a['appointment_date'], (string) $b['appointment_date']));
//     return $appointments;
// }

function jsondb_appointments_for_doctor(int $doctorId): array
{
    $appointments = [];
    foreach (jsondb()->table('appointments') as $appointment) {
        if ((int) $appointment['doctor_id'] !== $doctorId) {
            continue;
        }

        if (in_array((string) $appointment['status'], ['cancelled'], true)) {
            continue;
        }

        $appointments[] = $appointment;
    }

    usort($appointments, fn(array $a, array $b) => strcmp((string) $a['appointment_date'], (string) $b['appointment_date']));
    return $appointments;
}


function jsondb_appointments_for_patient(int $patientId): array
{
    $appointments = [];
    foreach (jsondb()->table('appointments') as $appointment) {
        if ((int) $appointment['patient_id'] === $patientId) {
            $appointments[] = $appointment;
        }
    }
    usort($appointments, fn(array $a, array $b) => strcmp((string) $a['appointment_date'], (string) $b['appointment_date']));
    return $appointments;
}

function jsondb_create_appointment(array $data): array
{
    $store = jsondb();
    $appointments =& $store->table('appointments');
    $row = [
        'id' => $store->nextId('appointments'),
        'patient_id' => (int) $data['patient_id'],
        'doctor_id' => (int) $data['doctor_id'],
        'appointment_date' => (string) $data['appointment_date'],
        'status' => (string) ($data['status'] ?? 'pending'),
        'created_at' => jsondb_now(),
    ];
    $appointments[] = $row;
    $store->markDirty();
    jsondb_commit_if_needed();
    return $row;
}

function jsondb_update_appointment(int $id, array $data): bool
{
    $store = jsondb();
    $appointments =& $store->table('appointments');
    foreach ($appointments as &$appointment) {
        if ((int) $appointment['id'] === $id) {
            $appointment = array_merge($appointment, $data);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_delete_appointment(int $id): bool
{
    $store = jsondb();
    $appointments =& $store->table('appointments');
    foreach ($appointments as $index => $appointment) {
        if ((int) $appointment['id'] === $id) {
            array_splice($appointments, $index, 1);
            jsondb_delete_prescriptions_by_appointment_ids([$id]);
            $store->markDirty();
            jsondb_commit_if_needed();
            return true;
        }
    }
    return false;
}

function jsondb_delete_appointments_by_doctor(int $doctorId): void
{
    $store = jsondb();
    $appointments =& $store->table('appointments');
    $remaining = [];
    $deletedIds = [];

    foreach ($appointments as $appointment) {
        if ((int) $appointment['doctor_id'] === $doctorId) {
            $deletedIds[] = (int) $appointment['id'];
            continue;
        }
        $remaining[] = $appointment;
    }

    if ($deletedIds !== []) {
        $appointments = $remaining;
        $store->markDirty();
        jsondb_delete_prescriptions_by_appointment_ids($deletedIds);
        jsondb_commit_if_needed();
    }
}

function jsondb_delete_appointments_by_patient(int $patientId): void
{
    $store = jsondb();
    $appointments =& $store->table('appointments');
    $remaining = [];
    $deletedIds = [];

    foreach ($appointments as $appointment) {
        if ((int) $appointment['patient_id'] === $patientId) {
            $deletedIds[] = (int) $appointment['id'];
            continue;
        }
        $remaining[] = $appointment;
    }

    if ($deletedIds !== []) {
        $appointments = $remaining;
        $store->markDirty();
        jsondb_delete_prescriptions_by_appointment_ids($deletedIds);
        jsondb_commit_if_needed();
    }
}

function jsondb_prescription_by_appointment(int $appointmentId): ?array
{
    foreach (jsondb()->table('prescriptions') as $prescription) {
        if ((int) $prescription['appointment_id'] === $appointmentId) {
            return $prescription;
        }
    }
    return null;
}

function jsondb_delete_prescriptions_by_appointment_ids(array $appointmentIds): void
{
    if ($appointmentIds === []) {
        return;
    }

    $appointmentIds = array_flip(array_map('intval', $appointmentIds));
    $store = jsondb();
    $prescriptions =& $store->table('prescriptions');
    $remaining = [];

    foreach ($prescriptions as $prescription) {
        $appointmentId = (int) $prescription['appointment_id'];
        if (isset($appointmentIds[$appointmentId])) {
            continue;
        }
        $remaining[] = $prescription;
    }

    if (count($remaining) !== count($prescriptions)) {
        $prescriptions = $remaining;
        $store->markDirty();
        jsondb_commit_if_needed();
    }
}

function jsondb_upsert_prescription(int $appointmentId, string $description): array
{
    $store = jsondb();
    $prescriptions =& $store->table('prescriptions');
    foreach ($prescriptions as &$prescription) {
        if ((int) $prescription['appointment_id'] === $appointmentId) {
            $prescription['description'] = $description;
            $prescription['updated_at'] = jsondb_now();
            $store->markDirty();
            jsondb_commit_if_needed();
            return $prescription;
        }
    }

    $row = [
        'id' => $store->nextId('prescriptions'),
        'appointment_id' => $appointmentId,
        'description' => $description,
        'created_at' => jsondb_now(),
        'updated_at' => jsondb_now(),
    ];
    $prescriptions[] = $row;
    $store->markDirty();
    jsondb_commit_if_needed();
    return $row;
}

function jsondb_appointments_with_names(): array
{
    $appointments = jsondb_appointments_all_sorted();
    $patients = [];
    foreach (jsondb()->table('patients') as $patient) {
        $patients[(int) $patient['id']] = $patient;
    }
    $doctors = [];
    foreach (jsondb()->table('doctors') as $doctor) {
        $doctors[(int) $doctor['id']] = $doctor;
    }

    $rows = [];
    foreach ($appointments as $appointment) {
        $patient = $patients[(int) $appointment['patient_id']] ?? null;
        $doctor = $doctors[(int) $appointment['doctor_id']] ?? null;
        $prescription = jsondb_prescription_by_appointment((int) $appointment['id']);
        $rows[] = [
            'id' => (int) $appointment['id'],
            'appointment_date' => $appointment['appointment_date'],
            'status' => $appointment['status'],
            'patient_name' => $patient['name'] ?? 'Unknown',
            'doctor_name' => $doctor['name'] ?? 'Unknown',
            'prescription' => $prescription['description'] ?? null,
        ];
    }
    return $rows;
}

function jsondb_doctors_with_today_usage(string $todayYmd): array
{
    $doctors = jsondb()->table('doctors');
    usort($doctors, fn(array $a, array $b) => (int) $a['id'] <=> (int) $b['id']);

    $usage = [];
    foreach (jsondb()->table('appointments') as $appointment) {
        $status = (string) $appointment['status'];
        if (!in_array($status, ['pending', 'approved', 'accepted', 'completed'], true)) {
            continue;
        }
        $date = date('Y-m-d', strtotime((string) $appointment['appointment_date']));
        if ($date !== $todayYmd) {
            continue;
        }
        $doctorId = (int) $appointment['doctor_id'];
        if (!isset($usage[$doctorId])) {
            $usage[$doctorId] = 0;
        }
        $usage[$doctorId]++;
    }

    $rows = [];
    foreach ($doctors as $doctor) {
        $doctorId = (int) $doctor['id'];
        $rows[] = array_merge($doctor, [
            'booked_count' => $usage[$doctorId] ?? 0,
        ]);
    }

    return $rows;
}
