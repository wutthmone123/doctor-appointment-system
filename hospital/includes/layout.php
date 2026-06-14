<?php
declare(strict_types=1);

function nav_items_for_role(string $role): array
{
    return match ($role) {
        'admin' => [
            ['label' => 'Dashboard', 'path' => 'admin/dashboard.php', 'icon' => 'bi-speedometer2'],
            ['label' => 'Doctors', 'path' => 'admin/doctors.php', 'icon' => 'bi-person-badge'],
            ['label' => 'Schedule', 'path' => 'admin/schedule.php', 'icon' => 'bi-clock-history'],
            ['label' => 'Patients', 'path' => 'admin/patients.php', 'icon' => 'bi-people'],
            ['label' => 'Appointments', 'path' => 'admin/appointments.php', 'icon' => 'bi-calendar-check'],
           
        ],
        'doctor' => [
            ['label' => 'Dashboard', 'path' => 'doctor/dashboard.php', 'icon' => 'bi-speedometer2'],
            ['label' => 'Appointments', 'path' => 'doctor/appointments.php', 'icon' => 'bi-calendar-week'],
            ['label' => 'Schedule', 'path' => 'doctor/schedule.php', 'icon' => 'bi-clock-history'],
            ['label' => 'Settings', 'path' => 'doctor/settings.php', 'icon' => 'bi-gear'],
        ],
        'patient' => [
            ['label' => 'Dashboard', 'path' => 'patient/dashboard.php', 'icon' => 'bi-speedometer2'],
            ['label' => 'Book Appointment', 'path' => 'patient/book_appointment.php', 'icon' => 'bi-calendar-plus'],
            ['label' => 'My Appointments', 'path' => 'patient/appointments.php', 'icon' => 'bi-journal-check'],
            ['label' => 'Doctors', 'path' => 'patient/doctors.php', 'icon' => 'bi-person-lines-fill'],
            // ['label' => 'Prescriptions', 'path' => 'patient/prescriptions.php', 'icon' => 'bi-file-earmark-medical'],
            ['label' => 'Settings', 'path' => 'patient/settings.php', 'icon' => 'bi-gear'],
        ],
        default => [],
    };
}

function build_sidebar_links(array $items, string $activePath): string
{
    $links = '';
    foreach ($items as $item) {
        $itemPath = ltrim((string) ($item['path'] ?? ''), '/');
        $isActive = $itemPath !== '' && str_ends_with($activePath, $itemPath);
        $class = $isActive ? 'sidebar-link active' : 'sidebar-link';
        $activeAttr = $isActive ? ' aria-current="page"' : '';
        $href = url((string) ($item['path'] ?? ''));
        $label = e((string) ($item['label'] ?? 'Link'));
        $icon = e((string) ($item['icon'] ?? 'bi-circle'));
        $links .= '<a class="' . $class . '" href="' . $href . '"' . $activeAttr . '><i class="bi ' . $icon . '"></i><span>' . $label . '</span></a>';
    }

    return $links;
}

function render_guest_header(string $title): void
{
    $fullTitle = e($title) . ' | ' . APP_NAME;
    $bootstrapCssUrl = url('assets/vendor/bootstrap/css/bootstrap.min.css');
    $bootstrapIconsCssUrl = url('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css');
    $cssUrl = url('assets/css/style.css');
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$fullTitle}</title>
    <link href="{$bootstrapCssUrl}" rel="stylesheet">
    <link href="{$bootstrapIconsCssUrl}" rel="stylesheet">
    <link href="{$cssUrl}" rel="stylesheet">
</head>
<body class="auth-body">
<button type="button" class="theme-toggle-btn guest-theme-toggle" data-theme-toggle aria-label="Toggle dark mode">
    <i class="bi bi-moon-stars-fill" data-theme-icon></i>
    <span data-theme-label>Dark</span>
</button>
HTML;
}

function render_guest_footer(): void
{
    $bootstrapJsUrl = url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js');
    $jsUrl = url('assets/js/app.js');
    echo <<<HTML
    <script src="{$bootstrapJsUrl}"></script>
    <script src="{$jsUrl}"></script>
</body>
</html>
HTML;
}

function render_app_header(string $title): void
{
    $user = current_user();
    $fullTitle = e($title) . ' | ' . APP_NAME;
    $activePath = trim(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $userRole = (string) ($user['role'] ?? '');
    $items = $userRole !== '' ? nav_items_for_role($userRole) : [];
    $csrf = generate_csrf_token();
    $bootstrapCssUrl = url('assets/vendor/bootstrap/css/bootstrap.min.css');
    $bootstrapIconsCssUrl = url('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css');
    $cssUrl = url('assets/css/style.css');
    $logoutUrl = url('logout.php');
    $safeTitle = e($title);
    $safeRole = e($userRole !== '' ? ucfirst($userRole) : 'User');
    $userName = e($user['name'] ?? 'User');

    $sidebarLinks = build_sidebar_links($items, $activePath);
    $mobileLabel = 'Open navigation menu';

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$fullTitle}</title>
    <link href="{$bootstrapCssUrl}" rel="stylesheet">
    <link href="{$bootstrapIconsCssUrl}" rel="stylesheet">
    <link href="{$cssUrl}" rel="stylesheet">
</head>
<body class="app-body">
<div class="offcanvas offcanvas-start app-sidebar-offcanvas d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
        <div>
            <h5 class="mb-0" id="mobileSidebarLabel">Hospital HMS</h5>
            <small class="text-light-emphasis text-uppercase">{$safeRole}</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column justify-content-between">
        <nav class="nav flex-column gap-1 mb-3">
            {$sidebarLinks}
        </nav>
        <div class="sidebar-footer">
            <div class="small text-light-emphasis mb-2">Signed in as {$userName}</div>
            <form method="post" action="{$logoutUrl}">
                <input type="hidden" name="csrf_token" value="{$csrf}">
                <button type="submit" class="btn btn-outline-light btn-sm w-100">Logout</button>
            </form>
        </div>
    </div>
</div>
<div class="app-shell">
    <aside class="app-sidebar d-none d-lg-flex">
        <div class="sidebar-brand">
            <h5 class="mb-0">Doctor Appointment System</h5>
            <small class="text-light-emphasis text-uppercase">{$safeRole}</small>
        </div>
        <nav class="nav flex-column gap-1">
            {$sidebarLinks}
        </nav>
        <div class="sidebar-footer">
            <div class="small text-light-emphasis mb-2">Signed in as {$userName}</div>
            <form method="post" action="{$logoutUrl}">
                <input type="hidden" name="csrf_token" value="{$csrf}">
                <button type="submit" class="btn btn-outline-light btn-sm w-100">Logout</button>
            </form>
        </div>
    </aside>
    <main class="app-main">
        <header class="main-header">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="{$mobileLabel}">
                    <i class="bi bi-list"></i>
                </button>
                <h4 class="mb-0">{$safeTitle}</h4>
            </div>
            <button type="button" class="theme-toggle-btn app-theme-toggle" data-theme-toggle aria-label="Toggle dark mode">
                <i class="bi bi-moon-stars-fill" data-theme-icon></i>
                <span data-theme-label>Dark</span>
            </button>
        </header>
        <section class="main-content">
HTML;

    $success = flash('success');
    $error = flash('error');
    if ($success) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . e($success) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    if ($error) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . e($error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

function render_app_footer(): void
{
    $bootstrapJsUrl = url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js');
    $jsUrl = url('assets/js/app.js');
    echo <<<HTML
        </section>
    </main>
</div>
<script src="{$bootstrapJsUrl}"></script>
<script src="{$jsUrl}"></script>
</body>
</html>
HTML;
}
