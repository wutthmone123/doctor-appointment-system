<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (is_logged_in()) {
    $user = current_user();
    redirect(role_home($user['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request token.');
        redirect(url('login.php'));
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        flash('error', 'Email and password are required.');
        redirect(url('login.php'));
    }

    $user = jsondb_user_by_email($email);

    if (!$user || !password_verify($password, $user['password'])) {
        flash('error', 'Invalid email or password.');
        redirect(url('login.php'));
    }

    login_user($user);
    flash('success', 'Welcome back, ' . $user['name'] . '!');
    redirect(role_home($user['role']));
}

$csrf = generate_csrf_token();
render_guest_header('Login');
?>
<div class="guest-topbar">
    <div class="container d-flex align-items-center">
        <a class="guest-logo" href="<?= e(url('index.php')) ?>">Doctor Appointment System</a>
    </div>
</div>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card auth-card shadow-sm border-0">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="text-center mb-4">Sign In</h3>

                    <?php if ($success = flash('success')): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>

                    <?php if ($error = flash('error')): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        New patient?
                        <a href="<?= e(url('register.php')) ?>">Create account</a>
                    </p>
                    
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_guest_footer(); ?>
