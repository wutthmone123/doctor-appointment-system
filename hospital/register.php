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
        redirect(url('register.php'));
    }

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
        flash('error', 'All fields are required.');
        redirect(url('register.php'));
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email format.');
        redirect(url('register.php'));
    }

    $strongPasswordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{5,16}$/';

    if (!preg_match($strongPasswordPattern, $password)) {
        flash('error', 'Password must be 5-16 characters and include uppercase, lowercase, number, and symbol.');
        redirect(url('register.php'));
    }

    if ($password !== $confirmPassword) {
        flash('error', 'Passwords do not match.');
        redirect(url('register.php'));
    }

    if (jsondb_user_email_exists($email)) {
        flash('error', 'Email is already registered.');
        redirect(url('register.php'));
    }

    try {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        jsondb_transaction(function () use ($name, $email, $phone, $hashed): void {
            jsondb_create_user($name, $email, $hashed, 'patient');
            jsondb_create_patient([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ]);
        });
        flash('success', 'Registration successful. Please login.');
        redirect(url('login.php'));
    } catch (Throwable $e) {
        flash('error', 'Registration failed. Please try again.');
        redirect(url('register.php'));
    }
}

$csrf = generate_csrf_token();
render_guest_header('Patient Registration');
?>

<div class="guest-topbar">
    <div class="container d-flex align-items-center">
        <a class="guest-logo" href="<?= e(url('index.php')) ?>">Doctor Appointment System</a>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card auth-card shadow-sm border-0">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="text-center mb-4">Patient Registration</h3>

                    <?php if ($error = flash('error')): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success = flash('success')): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    required
                                    minlength="5"
                                    maxlength="16"
                                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{5,16}"
                                    title="Password must be 5-16 characters and include uppercase, lowercase, number, and symbol."
                                >
                    </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input
                                    type="password"
                                    name="confirm_password"
                                    class="form-control"
                                    required
                                    minlength="5"
                                    maxlength="16"
                                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{5,16}"
                                    title="Password must be 5-16 characters and include uppercase, lowercase, number, and symbol."
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        Already have an account?
                        <a href="<?= e(url('login.php')) ?>">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_guest_footer(); ?>
