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

/** Token as a query-string fragment for state-changing GET links. */
function csrf_query(): string { return '_csrf=' . urlencode(csrf_token()); }
/** Verify CSRF on a GET link; aborts on mismatch. */
function csrf_verify_get(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['_csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid or expired link. Go back, refresh, and try again.');
    }
}

// ---------------- Auth & roles ----------------
if (!defined('SUPERADMIN_EMAIL')) define('SUPERADMIN_EMAIL', 'exyeez@gmail.com');
function current_user(): ?array { return $_SESSION['user'] ?? null; }
/** The single super-admin (system owner) identified by email. */
function is_superadmin(): bool {
    $u = current_user();
    return $u && strtolower(trim($u['email'] ?? '')) === strtolower(SUPERADMIN_EMAIL);
}
function user_role(): string { return current_user()['role'] ?? 'guest'; }
function is_admin(): bool { return user_role() === 'admin'; }
/** Viewers can read/print but not create/edit/delete. */
function can_edit(): bool { return in_array(user_role(), ['admin', 'valuer'], true); }
/** May sign/finalise reports: admins, or any user granted a signing mandate. */
function can_sign(): bool {
    $u = current_user();
    return $u && (is_admin() || (int)($u['can_sign'] ?? 0) === 1);
}
/** Kennet coordinator: triages requests and assigns them, but no settings/edit/sign. */
function is_coordinator(): bool { return user_role() === 'coordinator'; }
/** May see all requests and assign them to officers. */
function can_assign(): bool { return is_admin() || is_coordinator(); }
/** Admins & coordinators see every valuation; officers (valuers) are scoped to their own. */
function sees_all_valuations(): bool { return is_admin() || is_coordinator(); }

/** Portal user is a client-side admin (manages their company's portal). */
function client_is_admin(): bool { $c = current_client(); return $c && ($c['role'] ?? 'officer') === 'admin'; }

/** Simple request lifecycle statuses shown to portal & Kennet staff. */
function request_statuses(): array {
    return ['requested' => 'Requested', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'complete' => 'Complete', 'cancelled' => 'Cancelled'];
}
function request_status_label(string $s): string { return request_statuses()[$s] ?? ucfirst(str_replace('_', ' ', $s)); }
/** Coloured pill for a request status (uses the .badge / .b-* classes). */
function request_badge(string $s): string {
    $cls = ['requested' => 'b-amber', 'assigned' => 'b-blue', 'in_progress' => 'b-blue', 'complete' => 'b-green', 'cancelled' => 'b-grey'][$s] ?? 'b-grey';
    return '<span class="badge ' . $cls . '">' . e(request_status_label($s)) . '</span>';
}

// ---------------- Notifications (request workflow) ----------------
/** Send a plain-text email via PHP mail(). Best-effort; returns false silently on failure. */
function send_mail($to, string $subject, string $body): bool {
    $to = is_array($to) ? array_values(array_filter(array_unique($to))) : [$to];
    if (!$to) return false;
    $from = setting('mail_from', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $name = setting('company_name', 'Kennet Automobile Valuers');
    $headers = 'From: ' . $name . ' <' . $from . ">\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n" .
               'X-Mailer: KennetVS';
    $ok = false;
    foreach ($to as $addr) { if (@mail($addr, $subject, $body, $headers)) $ok = true; }
    return $ok;
}
/** Emails of all Kennet staff who can assign requests (admins + coordinators). */
function assigner_emails(): array {
    try {
        $rows = db()->query("SELECT email FROM users WHERE email <> '' AND (role='admin' OR role='coordinator') AND (active=1 OR active IS NULL)")->fetchAll(PDO::FETCH_COLUMN);
        return $rows ?: [];
    } catch (Throwable $e) { return []; }
}
function user_email(?int $uid): ?string {
    if (!$uid) return null;
    try { $st = db()->prepare('SELECT email FROM users WHERE id=?'); $st->execute([$uid]); return $st->fetchColumn() ?: null; }
    catch (Throwable $e) { return null; }
}
/** New request raised by a portal officer -> notify Kennet assigners. */
function notify_new_request(int $rid, string $reg, string $type, ?array $client): void {
    $co = lookup_name('clients', $client['client_id'] ?? null) ?: 'A client';
    $who = $client['name'] ?? 'A portal user';
    send_mail(assigner_emails(),
        "New valuation request: $reg",
        "$who ($co) requested a " . ($type === 'bank' ? 'bank' : 'insurance') . " valuation.\n\nReg No: $reg\n\nLog in to assign a valuer.");
}
/** Request assigned to a Kennet officer -> notify that officer. */
function notify_assigned(int $rid, string $reg, ?int $officerId): void {
    $email = user_email($officerId);
    if (!$email) return;
    send_mail($email, "Valuation assigned to you: $reg",
        "A valuation has been assigned to you.\n\nReg No: $reg\n\nOpen the system to complete it.");
}
/** Request completed -> notify the portal officer who raised it. */
function notify_complete(int $rid, string $reg, ?string $requesterEmail): void {
    if (!$requesterEmail) return;
    send_mail($requesterEmail, "Valuation ready: $reg",
        "The valuation you requested for $reg is complete.\n\nLog in to your client portal to view the report.");
}

/** Availability cut-off timestamp (date or date+time). */
function banner_until_ts(): ?int {
    $u = trim(setting('banner_until'));
    if ($u === '') return null;
    $s = strlen($u) <= 10 ? $u . ' 23:59:59' : str_replace('T', ' ', $u); // date-only ends at day's end
    $t = strtotime($s);
    return $t ?: null;
}
/** System is locked once the availability cut-off has passed (banner enabled). */
function system_locked(): bool {
    if (setting('banner_enabled') !== '1') return false;
    $t = banner_until_ts();
    return $t && time() > $t;
}
/** The banner text, with {until} replaced by the formatted date/time. */
function availability_message(): string {
    $msg = setting('banner_message', 'This system will be available until {until}.');
    $t = banner_until_ts();
    return str_replace('{until}', $t ? date('d M Y, g:i A', $t) : '', $msg);
}
/** The message shown to non-super-admins when the system is locked. */
function denied_message(): string {
    return setting('banner_denied_message', 'This system is no longer available for use. Please contact the administrator.');
}

/** Render the "no longer available" page and stop. */
function deny_unavailable(): void {
    http_response_code(503);
    $app = defined('APP_NAME') ? APP_NAME : 'System';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Unavailable</title>'
        . '<div style="font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#0f1216;color:#e6e9ee;'
        . 'min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;margin:0">'
        . '<div style="max-width:380px"><div style="font-size:46px">⛔</div>'
        . '<h1 style="font-size:22px;margin:10px 0 8px">System no longer available</h1>'
        . '<p style="color:#9aa4b2;font-size:14px;line-height:1.6">' . e(denied_message()) . '</p></div></div>';
    exit;
}

function require_login(): void {
    if (!current_user()) redirect('login.php');
    if (system_locked() && !is_superadmin()) { logout(); deny_unavailable(); }
    touch_activity();
}

/** Record that the current user is online and what they're doing. */
function touch_activity(?string $activity = null): void {
    $u = current_user();
    if (!$u) return;
    if ($activity === null) {
        $map = ['dashboard.php'=>'Dashboard','bank_list.php'=>'Bank Valuations','insurance_list.php'=>'Insurance Valuations',
            'analytics.php'=>'Analytics','users.php'=>'Users','settings.php'=>'Settings','audit.php'=>'Audit Log',
            'recycle.php'=>'Recycle Bin','clients.php'=>'Clients','insurers.php'=>'Insurers','types.php'=>'Valuation Types',
            'profile.php'=>'Profile','bank_form.php'=>'Bank valuation form','insurance_form.php'=>'Insurance valuation form'];
        $base = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $activity = $map[$base] ?? ucwords(str_replace(['_', '.php'], [' ', ''], $base));
    }
    $loginAt = (int)($_SESSION['login_at'] ?? time());
    try {
        db()->prepare("INSERT INTO user_activity (user_id,name,login_at,last_seen,activity)
                       VALUES (?,?,FROM_UNIXTIME(?),NOW(),?)
                       ON DUPLICATE KEY UPDATE last_seen=NOW(), activity=VALUES(activity), name=VALUES(name)")
           ->execute([$u['id'], $u['name'], $loginAt, mb_substr($activity, 0, 250)]);
    } catch (Throwable $e) { /* table may not exist yet */ }
}
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
        $_SESSION['login_at'] = time();
        audit('login', 'user', $u['id']);
        return true;
    }
    return false;
}

function logout(): void {
    if ($u = current_user()) {
        audit('logout', 'user', $u['id']);
        try { db()->prepare('DELETE FROM user_activity WHERE user_id=?')->execute([$u['id']]); } catch (Throwable $e) {}
    }
    $_SESSION = []; session_destroy();
}

// ---------------- Client portal auth (separate from staff) ----------------
function current_client(): ?array { return $_SESSION['client_user'] ?? null; }
function require_client(): void {
    if (!current_client()) redirect('portal_login.php');
    if (system_locked()) { client_logout(); deny_unavailable(); }
}

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
    return ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'complete' => 'Complete'];
}
function status_badge(?string $s): string {
    $cls = ['draft' => 'b-grey', 'submitted' => 'b-amber', 'approved' => 'b-green', 'complete' => 'b-green'][$s] ?? 'b-grey';
    $lbl = valuation_statuses()[$s] ?? ucfirst((string)($s ?: 'draft'));
    return '<span class="badge ' . $cls . '">' . e($lbl) . '</span>';
}

/** Sign valuations: stamp signed_at/by and mark status Complete. Returns count. */
function sign_records(string $table, array $ids): int {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return 0;
    $sets = []; $params = [];
    if (column_exists($table, 'signed_at')) $sets[] = 'signed_at = NOW()';
    if (column_exists($table, 'signed_by')) { $sets[] = 'signed_by = ?'; $params[] = current_user()['id'] ?? null; }
    if (column_exists($table, 'status'))    $sets[] = "status = 'complete'";
    if (!$sets) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    db()->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id IN ($in)")
        ->execute(array_merge($params, $ids));
    request_complete_for($table, $ids); // advance linked requests + notify portal officer
    return count($ids);
}
/** A linked request moves assigned -> in_progress the moment its valuation is worked on. */
function request_touch_progress(string $table, int $vid): void {
    if (!$vid) return;
    try {
        db()->prepare("UPDATE valuation_requests SET status='in_progress', updated_at=NOW()
                       WHERE valuation_table=? AND valuation_id=? AND status='assigned'")
            ->execute([$table, $vid]);
    } catch (Throwable $e) {}
}
/** Mark any linked requests complete and email the portal officer who raised them. */
function request_complete_for(string $table, array $ids): void {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return;
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT vr.id, vr.reg_no, vr.status, cu.email
                             FROM valuation_requests vr
                             LEFT JOIN client_users cu ON cu.id = vr.requested_by
                             WHERE vr.valuation_table=? AND vr.valuation_id IN ($in)
                               AND vr.status NOT IN ('complete','cancelled')");
        $st->execute(array_merge([$table], $ids));
        foreach ($st->fetchAll() as $r) {
            db()->prepare("UPDATE valuation_requests SET status='complete', updated_at=NOW() WHERE id=?")->execute([$r['id']]);
            notify_complete((int)$r['id'], (string)$r['reg_no'], $r['email'] ?? null);
        }
    } catch (Throwable $e) {}
}

/** Generate the next serial, e.g. 079/06/2026 = running number this month / month / year. */
function next_serial(): string {
    $y = date('Y'); $m = (int)date('m');
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM bankvaluations WHERE YEAR(created_at)=? AND MONTH(created_at)=?");
        $st->execute([$y, $m]);
        $seq = (int)$st->fetchColumn() + 1;
    } catch (Throwable $e) { $seq = 1; }
    $prefix = trim(setting('serial_prefix', ''));
    return ($prefix !== '' ? $prefix . '/' : '') . sprintf('%03d/%02d/%04d', $seq, $m, $y);
}

/** Show a serial with the configured prefix (adds it to bare NNN/MM/YYYY serials). */
function serial_display(?string $s): string {
    $s = trim((string)$s);
    $p = trim(setting('serial_prefix', ''));
    if ($s === '' || $p === '') return $s;
    if (preg_match('#^\d{1,4}/\d{2}/\d{4}$#', $s)) return $p . '/' . $s; // bare auto serial
    return $s; // already prefixed or legacy text
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

/** id => "Name (INI)" for assigning the valuer of a record. */
function user_list_for_valuer(): array {
    $out = [];
    try {
        $cols = 'id, name' . (column_exists('users', 'initials') ? ', initials' : '');
        foreach (db()->query("SELECT $cols FROM users ORDER BY name")->fetchAll() as $u) {
            $ini = (isset($u['initials']) && $u['initials'] !== '') ? ' (' . $u['initials'] . ')' : '';
            $out[$u['id']] = $u['name'] . $ini;
        }
    } catch (Throwable $e) {}
    return $out;
}

/** Officers may only touch their own valuations; admins & coordinators any. Aborts otherwise. */
function require_own_valuation(array $row): void {
    if (sees_all_valuations()) return;
    if (!array_key_exists('created_by', $row)) return; // pre-migration safety
    if ((int)($row['created_by'] ?? 0) !== (int)(current_user()['id'] ?? -1)) {
        flash('That valuation belongs to another valuer.', 'err');
        redirect('dashboard.php');
    }
}

/** Map a valuation table to its report type key (bank|insurance|machine). */
function table_type(?string $t): string {
    return ['valuations' => 'insurance', 'machinevaluations' => 'machine'][$t] ?? 'bank';
}
/** Map a valuation table to its edit-form page. */
function table_form(?string $t): string {
    return ['valuations' => 'insurance_form.php', 'machinevaluations' => 'machine_form.php'][$t] ?? 'bank_form.php';
}

/** Kennet officers (valuers) available to receive an assignment: id => "Name (INI)". */
function officer_list(): array {
    $out = [];
    try {
        $cols = 'id, name' . (column_exists('users', 'initials') ? ', initials' : '');
        $act  = column_exists('users', 'active') ? ' AND (active=1 OR active IS NULL)' : '';
        foreach (db()->query("SELECT $cols FROM users WHERE role='valuer'$act ORDER BY name")->fetchAll() as $u) {
            $ini = (isset($u['initials']) && $u['initials'] !== '') ? ' (' . $u['initials'] . ')' : '';
            $out[$u['id']] = $u['name'] . $ini;
        }
    } catch (Throwable $e) {}
    return $out;
}
/** Count of requests awaiting assignment (for the nav badge). */
function pending_request_count(): int {
    try { return (int)db()->query("SELECT COUNT(*) FROM valuation_requests WHERE status='requested'")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

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
