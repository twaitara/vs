<?php
/**
 * Kennet Valuation — configuration TEMPLATE.
 * Copy this file to `config.php` and fill in your real values.
 * (config.php is gitignored so secrets are never committed.)
 */

// ---- Database (set these in cPanel → MySQL Databases) ----
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cpaneluser_valuation');   // your DB name
define('DB_USER', 'cpaneluser_admin');       // your DB user
define('DB_PASS', 'CHANGE_ME');              // your DB password

// ---- App ----
define('APP_NAME', 'Kennet Valuation');

// Base URL path the app is served from (no trailing slash).
// Domain root → ''.  Subfolder e.g. /apexsuite/vs → '/apexsuite/vs'.
define('BASE_URL', '');

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('CURRENCY', 'KES');

date_default_timezone_set('Africa/Nairobi');

ini_set('display_errors', '0');   // set to '1' while debugging
error_reporting(E_ALL);
