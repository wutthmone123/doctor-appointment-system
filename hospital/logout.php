<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('login.php'));
    }

    if (is_logged_in()) {
        logout_user();
    }
} elseif (is_logged_in()) {
    logout_user();
}

render_guest_header('Logged Out');
?>
<style>
    body.auth-body {
        background:
            linear-gradient(rgba(15, 23, 42, 0.3), rgba(15, 23, 42, 0.3)),
            url('https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=1600&q=80')
            center/cover no-repeat fixed;
    }

    [data-theme="dark"] body.auth-body {
        background:
            linear-gradient(rgba(17, 24, 39, 0.58), rgba(17, 24, 39, 0.58)),
            url('https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=1600&q=80')
            center/cover no-repeat fixed;
    }
</style>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card auth-card shadow-sm border-0">
                <div class="card-body text-center p-4 p-lg-5">
                    <h3 class="mb-3">You are logged out</h3>
                    <p class="text-muted mb-4">Your session has ended successfully.</p>
                    <a href="<?= e(url('login.php')) ?>" class="btn btn-primary w-100">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_guest_footer(); ?>
