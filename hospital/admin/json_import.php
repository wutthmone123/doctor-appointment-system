<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('admin');

function import_records_from_payload(array $payload): array
{
    if (isset($payload['tables']) && is_array($payload['tables'])) {
        return $payload['tables'];
    }

    if (isset($payload['records']) && is_array($payload['records'])) {
        return $payload['records'];
    }

    return $payload;
}

function normalize_rows(array $rows): array
{
    $normalized = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $normalized[] = $row;
        }
    }
    return $normalized;
}

function normalize_email_field(array $rows): array
{
    foreach ($rows as &$row) {
        if (isset($row['email'])) {
            $row['email'] = strtolower((string) $row['email']);
        }
    }
    return $rows;
}

$defaultPath = 'c:\\Users\\Window10\\Downloads\\hospital_data (2).json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('admin/json_import.php'));
    }

    $filePath = trim((string) ($_POST['file_path'] ?? ''));
    if ($filePath === '') {
        $filePath = $defaultPath;
    }

    try {
        if (!is_file($filePath)) {
            throw new RuntimeException('JSON file not found.');
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new RuntimeException('Unable to read JSON file.');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JSON format.');
        }

        $records = import_records_from_payload($payload);

        $tables = [
            'users' => normalize_email_field(normalize_rows($records['users'] ?? [])),
            'doctors' => normalize_email_field(normalize_rows($records['doctors'] ?? [])),
            'patients' => normalize_email_field(normalize_rows($records['patients'] ?? [])),
            'appointments' => normalize_rows($records['appointments'] ?? []),
            'prescriptions' => normalize_rows($records['prescriptions'] ?? []),
        ];

        $now = date('Y-m-d H:i:s');
        $counters = [];
        foreach ($tables as $table => $rows) {
            $maxId = 0;
            foreach ($rows as $row) {
                if (isset($row['id'])) {
                    $maxId = max($maxId, (int) $row['id']);
                }
            }
            $counters[$table] = $maxId;
        }

        $adminEmail = 'admin@hospital.com';
        $hasAdmin = false;
        foreach ($tables['users'] as $user) {
            if (strtolower((string) ($user['email'] ?? '')) === $adminEmail) {
                $hasAdmin = true;
                break;
            }
        }
        if (!$hasAdmin) {
            $counters['users'] = ($counters['users'] ?? 0) + 1;
            $tables['users'][] = [
                'id' => $counters['users'],
                'name' => 'System Admin',
                'email' => $adminEmail,
                'password' => password_hash('Admin@123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'created_at' => $now,
            ];
        }

        $data = [
            'meta' => [
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'imported_at' => $now,
                'source_file' => $filePath,
            ],
            'counters' => $counters,
            'tables' => $tables,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON database.');
        }

        $dbPath = jsondb_path();
        $dir = dirname($dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create storage directory.');
        }

        if (file_put_contents($dbPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write JSON database.');
        }

        flash('success', 'Import completed. Records: users=' . count($tables['users']) . ', doctors=' . count($tables['doctors']) . ', patients=' . count($tables['patients']) . ', appointments=' . count($tables['appointments']) . '.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect(url('admin/json_import.php'));
}

$csrf = generate_csrf_token();
$jsonPath = jsondb_path();

render_app_header('JSON Import');
?>
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Import JSON Data</h5>
        <small class="text-muted">Target: <?= e($jsonPath) ?></small>
    </div>
    <div class="card-body">
        <?php if ($success = flash('success')): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error = flash('error')): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <div class="mb-3">
                <label class="form-label">JSON File Path</label>
                <input type="text" name="file_path" class="form-control" value="<?= e($defaultPath) ?>" required>
                <div class="form-text">Server path (example: <?= e($defaultPath) ?>)</div>
            </div>
            <button type="submit" class="btn btn-primary">Import Into JSON Database</button>
        </form>
    </div>
</div>
<?php render_app_footer(); ?>
