<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('patient');

$perPage = 5;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalDoctors = jsondb_doctors_count();
$totalPages = max(1, (int) ceil($totalDoctors / $perPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $perPage;
$today = date('Y-m-d');
$doctors = jsondb_doctors_with_today_usage($today);
$doctors = array_slice($doctors, $offset, $perPage);

render_app_header('Doctor List');
?>
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Doctors and Schedule</h5>
        <small class="text-muted">Token date: <?= e($today) ?></small>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Specialty</th>
                    <th>Schedule</th>
                    <!-- <th>Token</th> -->
                    <th>Status</th>
                    <!-- <th>Patient Limit</th> -->
                </tr>
            </thead>
            <tbody>
                <?php if (!$doctors): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No doctors available.</td>
                    </tr>
                <?php endif; ?>
                <?php $index = $offset + 1; ?>
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
                        <td><?= e($doctor['email']) ?></td>
                        <td><?= e($doctor['phone']) ?></td>
                        <td><?= e($doctor['address']) ?></td>
                        <td><?= e($doctor['specialty']) ?></td>
                        <td><?= e(formatTime12Hour((string) $doctor['schedule_min_time'])) ?> - <?= e(formatTime12Hour((string) $doctor['schedule_max_time'])) ?></td>
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
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <?= e((string) ($offset + 1)) ?> to <?= e((string) ($offset + count($doctors))) ?> of <?= e((string) $totalDoctors) ?> doctors
                </small>
                <nav aria-label="Doctors pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(url('patient/doctors.php?page=' . max(1, $currentPage - 1))) ?>">Previous</a>
                        </li>
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(url('patient/doctors.php?page=' . $p)) ?>"><?= e((string) $p) ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(url('patient/doctors.php?page=' . min($totalPages, $currentPage + 1))) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php render_app_footer(); ?>
