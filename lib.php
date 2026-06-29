<?php
/**
 * Shared helpers: DB, auth, roles, CSRF, settings, audit, formatting.
 */
require_once __DIR__ . '/config.php';

// ---------------- Error handling / logging ----------------
$LOG_DIR = __DIR__ . '/storage';
if (!is_dir($LOG_DIR)) @mkdir($LOG_DIR, 0775, true);
ini_set('log_errors', '1');
ini_set('error_log', $LOG_DIR . '/app.log');
// Only show errors on screen when APP_DEBUG is explicitly true.
$__debug = defined('APP_DEBUG') && APP_DEBUG;
ini_set('display_errors', $__debug ? '1' : '0');
error_reporting(E_ALL);

// ---------------- Hardened session ----------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('KENNETSESS');
    session_start();
}
// Idle timeout: 8 hours.
$IDLE = 8 * 3600;
if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $IDLE)) {
    $_SESSION = []; session_destroy(); session_start();
}
$_SESSION['last_activity'] = time();

/** Singleton PDO connection. */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** HTML-escape. */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Build an app URL. */
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

/** Redirect helper. */
function redirect(string $path): void { header('Location: ' . url($path)); exit; }

/** Flash messages (one-shot). Optional type: 'ok' | 'err'. */
function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

// ---------------- CSRF ----------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
/** Verify CSRF on POST; aborts with 403 on mismatch. Call at the top of POST handlers. */
function csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $sent = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);
        exit('Invalid or expired form token. Go back, refresh, and try again.');
    }
}

// ---------------- Auth & roles ----------------
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function user_role(): string { return current_user()['role'] ?? 'guest'; }
function is_admin(): bool { return user_role() === 'admin'; }
/** Viewers can read/print but not create/edit/delete. */
function can_edit(): bool { return in_array(user_role(), ['admin', 'valuer'], true); }

function require_login(): void { if (!current_user()) redirect('login.php'); }
function require_admin(): void {
    require_login();
    if (!is_admin()) { http_response_code(403); exit('Admins only.'); }
}
function require_can_edit(): void {
    require_login();
    if (!can_edit()) { http_response_code(403); exit('You have view-only access.'); }
}

/** Recent failed-login count for throttling. */
function recent_failed_logins(string $email, string $ip): int {
    try {
        $st = db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE success = 0 AND (email = ? OR ip = ?) AND created_at > (NOW() - INTERVAL 15 MINUTE)");
        $st->execute([$email, $ip]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
function record_login_attempt(string $email, string $ip, bool $ok): void {
    try {
        $st = db()->prepare("INSERT INTO login_attempts (email, ip, success, created_at) VALUES (?,?,?,NOW())");
        $st->execute([$email, $ip, $ok ? 1 : 0]);
    } catch (Throwable $e) { /* table may not exist yet */ }
}

/** Attempt login. Returns: true | 'locked' | false. */
function attempt_login(string $email, string $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (recent_failed_logins($email, $ip) >= 8) return 'locked';

    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();

    $ok = $u && password_verify($password, $u['password']);
    // Respect active flag if the column exists.
    if ($ok && array_key_exists('active', $u) && (int)$u['active'] !== 1) {
        record_login_attempt($email, $ip, false);
        return false;
    }
    record_login_attempt($email, $ip, $ok);
    if ($ok) {
        unset($u['password']);
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        audit('login', 'user', $u['id']);
        return true;
    }
    return false;
}

function logout(): void {
    if ($u = current_user()) audit('logout', 'user', $u['id']);
    $_SESSION = []; session_destroy();
}

// ---------------- Client portal auth (separate from staff) ----------------
function current_client(): ?array { return $_SESSION['client_user'] ?? null; }
function require_client(): void { if (!current_client()) redirect('portal_login.php'); }

function attempt_client_login(string $email, string $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (recent_failed_logins($email, $ip) >= 8) return 'locked';
    $st = db()->prepare('SELECT * FROM client_users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();
    $ok = $u && password_verify($password, $u['password']) && (int)($u['active'] ?? 1) === 1;
    record_login_attempt($email, $ip, (bool)$ok);
    if ($ok) {
        unset($u['password']);
        session_regenerate_id(true);
        $_SESSION['client_user'] = $u;
        audit('portal_login', 'client_user', $u['id'], $email);
        return true;
    }
    return false;
}
function client_logout(): void {
    if ($c = current_client()) audit('portal_logout', 'client_user', $c['id']);
    unset($_SESSION['client_user']);
}

// ---------------- Settings ----------------
function settings_all(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        foreach (db()->query("SELECT `key`,`value` FROM settings")->fetchAll() as $r) {
            $cache[$r['key']] = $r['value'];
        }
    } catch (Throwable $e) { /* settings table may not exist yet */ }
    return $cache;
}
function setting(string $key, string $default = ''): string {
    $all = settings_all();
    return $all[$key] ?? $default;
}
function set_setting(string $key, string $value): void {
    $st = db()->prepare(
        "INSERT INTO settings (`key`,`value`,updated_at) VALUES (?,?,NOW())
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()");
    $st->execute([$key, $value]);
}

// ---------------- Status workflow & report numbers ----------------
function valuation_statuses(): array {
    return ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved'];
}
function status_badge(?string $s): string {
    $cls = ['draft' => 'b-grey', 'submitted' => 'b-amber', 'approved' => 'b-green'][$s] ?? 'b-grey';
    $lbl = valuation_statuses()[$s] ?? ucfirst((string)($s ?: 'draft'));
    return '<span class="badge ' . $cls . '">' . e($lbl) . '</span>';
}
/** Generate next sequential report number, e.g. KEN/2026/0007. */
function next_report_no(string $table): string {
    $prefix = setting('report_prefix', 'KEN');
    $year = date('Y');
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE YEAR(created_at) = ?");
        $st->execute([$year]);
        $seq = (int)$st->fetchColumn() + 1;
    } catch (Throwable $e) { $seq = 1; }
    return sprintf('%s/%s/%04d', $prefix, $year, $seq);
}

// ---------------- Audit ----------------
function audit(string $action, string $entity = '', $entityId = null, ?string $details = null): void {
    try {
        $u = current_user();
        $st = db()->prepare(
            "INSERT INTO audit_log (user_id,user_name,action,entity,entity_id,details,ip,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())");
        $st->execute([
            $u['id'] ?? null, $u['name'] ?? null, $action, $entity,
            $entityId !== null ? (string)$entityId : null, $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { /* audit_log may not exist yet */ }
}

// ---------------- Lookups ----------------
function lookup(string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $rows = db()->query("SELECT id, name FROM `$table` ORDER BY name")->fetchAll();
    $map = [];
    foreach ($rows as $r) $map[$r['id']] = $r['name'];
    return $cache[$table] = $map;
}
function lookup_name(string $table, $id): string { $m = lookup($table); return $m[$id] ?? ''; }

/** True if a column exists on a table (cached). Lets the app work pre/post migration. */
function column_exists(string $table, string $col): bool {
    static $cache = [];
    $key = "$table.$col";
    if (isset($cache[$key])) return $cache[$key];
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$table, $col]);
        return $cache[$key] = ((int)$st->fetchColumn() > 0);
    } catch (Throwable $e) { return $cache[$key] = false; }
}

/** " WHERE deleted_at IS NULL" if the table supports soft-delete, else "". */
function not_deleted_sql(string $table, string $prefix = ' WHERE '): string {
    return column_exists($table, 'deleted_at') ? $prefix . 'deleted_at IS NULL' : '';
}
function options(string $table, $selected = null): string {
    $out = '<option value="">-- select --</option>';
    foreach (lookup($table) as $id => $name) {
        $sel = ((string)$id === (string)$selected) ? ' selected' : '';
        $out .= '<option value="' . e($id) . '"' . $sel . '>' . e($name) . '</option>';
    }
    return $out;
}

// ---------------- Images (GD) ----------------
function gd_available(): bool { return function_exists('imagecreatefromstring') && function_exists('imagejpeg'); }

/** Load image bytes into a white-flattened, downscaled GD resource. Returns [resource|false]. */
function gd_load_scaled(string $bytes, int $maxDim) {
    $img = @imagecreatefromstring($bytes);
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    $scale = min(1.0, $maxDim / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($dst, 255, 255, 255);   // flatten transparency for JPEG
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    return $dst;
}

/** Save an uploaded image as a downscaled JPEG (clear but not big). Falls back to a raw copy. */
function save_resized_jpeg(string $tmp, string $dest, int $maxDim = 1600, int $quality = 82): bool {
    $bytes = @file_get_contents($tmp);
    if ($bytes === false) return false;
    if (gd_available()) {
        $img = gd_load_scaled($bytes, $maxDim);
        if ($img) { $ok = @imagejpeg($img, $dest, $quality); imagedestroy($img); if ($ok) return true; }
    }
    return (bool)@copy($tmp, $dest);
}

/** Return a downscaled JPEG data URI for embedding in PDFs (space-efficient). */
function gd_jpeg_data_uri(string $absPath, int $maxDim = 1000, int $quality = 62): ?string {
    $bytes = @file_get_contents($absPath);
    if ($bytes === false) return null;
    if (gd_available()) {
        $img = gd_load_scaled($bytes, $maxDim);
        if ($img) {
            ob_start(); @imagejpeg($img, null, $quality); $out = ob_get_clean(); imagedestroy($img);
            if ($out !== '') return 'data:image/jpeg;base64,' . base64_encode($out);
        }
    }
    // Fallback: embed original bytes.
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

// ---------------- Formatting ----------------
function money($v): string { return number_format((float)$v, 2); }
function ddate($v): string {
    if (!$v || $v === '0000-00-00' || $v === '0001-01-01') return '';
    $t = strtotime($v); return $t ? date('d M Y', $t) : (string)$v;
}

// ---------------- Number to words (KES) ----------------
function number_to_words($number): string {
    $number = (float)$number;
    $int = (int)floor($number);
    $cents = (int)round(($number - $int) * 100);
    $words = ucwords(int_to_words($int)) . ' Shillings';
    if ($cents > 0) $words .= ' and ' . ucwords(int_to_words($cents)) . ' Cents';
    return $words . ' Only';
}
function int_to_words(int $n): string {
    if ($n === 0) return 'zero';
    if ($n < 0) return 'negative ' . int_to_words(-$n);
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $scales = ['', ' thousand', ' million', ' billion', ' trillion'];
    $groups = [];
    while ($n > 0) { $groups[] = $n % 1000; $n = intdiv($n, 1000); }
    $parts = [];
    for ($i = count($groups) - 1; $i >= 0; $i--) {
        $g = $groups[$i];
        if ($g === 0) continue;
        $chunk = '';
        $h = intdiv($g, 100); $rem = $g % 100;
        if ($h) $chunk .= $ones[$h] . ' hundred';
        if ($rem) {
            if ($chunk) $chunk .= ' ';
            if ($rem < 20) $chunk .= $ones[$rem];
            else { $chunk .= $tens[intdiv($rem, 10)]; if ($rem % 10) $chunk .= '-' . $ones[$rem % 10]; }
        }
        $parts[] = $chunk . $scales[$i];
    }
    return implode(' ', $parts);
}
