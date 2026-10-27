<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

$config = require dirname(__DIR__) . '/config/config.php';

function config(string $group, string $key, mixed $default = null): mixed
{
    global $config;
    return $config[$group][$key] ?? $default;
}

function is_https(): bool
{
    // Nepaļaujamies uz klienta atsūtītiem proxy virsrakstiem.
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function cookie_path(): string
{
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return rtrim($directory, '/.') . '/';
}

function cookie_options(int $expires = 0): array
{
    return [
        'expires' => $expires,
        'path' => cookie_path(),
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

session_name(config('auth', 'session_name'));
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) config('auth', 'absolute_seconds'));
$sessionOptions = cookie_options();
unset($sessionOptions['expires']);
session_set_cookie_params($sessionOptions + ['lifetime' => 0]);
session_start();

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

require __DIR__ . '/database.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/view.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $page): never
{
    header('Location: ' . $page, true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(mixed $token): bool
{
    return is_string($token) && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function post_string(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}
