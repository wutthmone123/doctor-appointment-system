<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

http_response_code(404);

render_guest_header('404 Not Found');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card auth-card shadow-sm border-0 text-center">
                <div class="card-body p-4 p-lg-5">
                    <h1 class="display-4 fw-bold mb-3">404</h1>
                    <p class="mb-4">Page not found. The URL you entered does not exist.</p>
                    <a href="<?= e(url('index.php')) ?>" class="btn btn-primary">Go to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_guest_footer(); ?>
