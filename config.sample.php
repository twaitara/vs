<?php
/**
 * Kennet Valuation — configuration TEMPLATE.
 * Copy this file to `config.php` and either fill in the values below, OR
 * create a `.env` file (see .env.example) and the values are read from there.
 * (config.php and .env are gitignored so secrets are never committed.)
 */

// --- Optional .env loader ---------------------------------
$__env = [];
$__envFile = __DIR__ . '/.env';
if (is_file($__envFile)) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $__env[trim($k)] = trim($v, " \t\"'");
    }
}
$env = fn(string $k, string $default = '') => $__env[$k] ?? $default;

// ---- Database --------------------------------------------
define('DB_HOST', $env('DB_HOST', 'localhost'));
define('DB_PORT', $env('DB_PORT', '3306'));
define('DB_NAME', $env('DB_NAME', 'cpaneluser_valuation'));
define('DB_USER', $env('DB_USER', 'cpaneluser_admin'));
define('DB_PASS', $env('DB_PASS', 'CHANGE_ME'));

// ---- App -------------------------------------------------
define('APP_NAME', $env('APP_NAME', 'Kennet Valuation'));
define('BASE_URL', $env('BASE_URL', ''));      // e.g. '/vs' for a subfolder
define('CURRENCY', $env('CURRENCY', 'KES'));

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Show errors only when debugging.
if (strtolower($env('APP_DEBUG', 'false')) === 'true') define('APP_DEBUG', true);

date_default_timezone_set($env('TZ', 'Africa/Nairobi'));
