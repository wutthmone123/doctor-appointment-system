<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

render_guest_header('Unauthorized');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-5">
                    <h3 class="mb-3">Access Denied</h3>
                    <p class="text-muted mb-4">You do not have permission to view this page.</p>
                    <a href="<?= e(is_logged_in() ? role_home(current_user()['role']) : url('login.php')) ?>" class="btn btn-primary">
                        Go Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_guest_footer(); ?>
