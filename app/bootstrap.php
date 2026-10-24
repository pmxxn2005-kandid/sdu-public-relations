<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Bangkok');
if (is_file(ROOT . '/.env')) {
    foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\"'"));
    }
}
function env(string $key, string $default = ''): string { $v = getenv($key); return $v === false ? $default : $v; }
foreach (['storage', 'storage/uploads', 'storage/sessions', 'storage/backups'] as $dir) {
    if (!is_dir(ROOT . '/' . $dir)) mkdir(ROOT . '/' . $dir, 0700, true);
}
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_save_path(ROOT . '/storage/sessions');
    session_name('sdu_pr_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => env('SESSION_SECURE') === '1']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; frame-src https://www.youtube-nocookie.com https://www.google.com; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}
require_once ROOT . '/app/database.php';
require_once ROOT . '/app/helpers.php';
require_once ROOT . '/app/auth.php';
require_once ROOT . '/app/content.php';
