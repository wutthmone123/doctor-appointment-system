<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Rangoon');

define('APP_NAME', 'Hospital Management System');

function detectBaseUrl(): string
{
    $appRoot = realpath(__DIR__ . '/..');
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($appRoot !== false && $docRoot !== false) {
        $normalizedAppRoot = str_replace('\\', '/', $appRoot);
        $normalizedDocRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        if (str_starts_with($normalizedAppRoot, $normalizedDocRoot)) {
            $relative = substr($normalizedAppRoot, strlen($normalizedDocRoot));
            $relative = $relative === false ? '' : trim($relative, '/');
            return $relative === '' ? '/' : '/' . $relative;
        }
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($basePath === '' || $basePath === '.') {
        return '/';
    }

    $parts = explode('/', trim($basePath, '/'));
    if (count($parts) > 0) {
        array_pop($parts);
    }

    $fallback = '/' . implode('/', $parts);
    return rtrim($fallback, '/') === '' ? '/' : rtrim($fallback, '/');
}

define('BASE_URL', detectBaseUrl());

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'hospital_db');
define('DB_USER', 'root');
define('DB_PASS', '');
