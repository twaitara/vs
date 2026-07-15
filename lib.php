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
    $SESSION_TTL = 30 * 86400; // stay logged in for 30 days
    @ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
    session_set_cookie_params([
        'lifetime' => $SESSION_TTL,   // persistent cookie so the browser keeps you signed in
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('KENNETSESS');
    session_start();
}
// Only log out after 30 days of NO use (applies to every device).
$IDLE = 30 * 86400;
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
        // Relax STRICT mode so valuations can be saved partially ("save early, finish later").
        // Many legacy columns are NOT NULL without a default; strict MySQL would 500 on an
        // incomplete save. Non-strict lets MySQL fill implicit defaults instead.
        try { $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'"); } catch (Throwable $e) {}
    }
    return $pdo;
}

/** HTML-escape. */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Build an app URL. */
/** System modules the super admin can switch on/off. key => label. */
function modules_list(): array {
    return ['bank' => 'Bank Valuations', 'insurance' => 'Insurance Valuations', 'machine' => 'Machine Valuations',
            'requests' => 'Requests workflow', 'analytics' => 'Analytics', 'portal' => 'Client Portal'];
}
/** True if a module is enabled (default on). */
function module_enabled(string $key): bool { return setting('mod_' . $key, '1') === '1'; }
/** Guard a page behind a module toggle; redirects if disabled. */
function require_module(string $key): void {
    if (!module_enabled($key)) { flash('That section is currently disabled.', 'err'); redirect('dashboard.php'); }
}

/** Opaque routing: full-page URLs are shown as short codes (…/vs/ab12cd34ef) via r.php. */
function route_enabled(): bool { return !defined('ROUTE_OBFUSCATE') || ROUTE_OBFUSCATE; }
/** Pages whose URL is obfuscated. Assets, AJAX endpoints (activity/online/notifications/quick_add),
 *  sw.js, manifest.php, notif.js, email_cron.php are intentionally left as direct paths. */
function route_pages(): array {
    return [
        'index.php','dashboard.php','bank_list.php','insurance_list.php','machine_list.php',
        'bank_form.php','insurance_form.php','machine_form.php',
        'requests.php','analytics.php','settings.php','settings_email.php','profile.php',
        'users.php','client_users.php','clients.php','insurers.php','types.php','fuels.php',
        'audit.php','recycle.php','assign_valuers.php',
        'duplicate.php','sign.php','preview.php','print.php','export.php','send_report.php','backup.php',
        'login.php','logout.php',
        'portal.php','portal_request.php','portal_requests.php','portal_team.php',
        'portal_view.php','portal_pdf.php','portal_login.php','portal_logout.php',
    ];
}
function route_code(string $file): string {
    static $codes = null;
    if ($codes === null) { $codes = []; $salt = 'k912route|' . (defined('BASE_URL') ? BASE_URL : ''); foreach (route_pages() as $f) $codes[$f] = substr(hash('sha256', $salt . '|' . $f), 0, 12); }
    return $codes[$file] ?? '';
}
function code_page(string $code): string {
    $code = preg_replace('/[^a-f0-9]/', '', $code);
    if ($code === '') return '';
    foreach (route_pages() as $f) if (route_code($f) === $code) return $f;
    return '';
}
/** The real page currently executing (router-aware), for self-links/redirects. */
function self_page(): string { return $GLOBALS['ROUTE_PAGE'] ?? basename($_SERVER['SCRIPT_NAME'] ?? ''); }

function url(string $path = ''): string {
    $p = ltrim($path, '/');
    if (route_enabled()) {
        $cut = strcspn($p, '?#');
        $file = substr($p, 0, $cut);
        $code = route_code($file);
        if ($code !== '') return BASE_URL . '/' . $code . substr($p, $cut);
    }
    return BASE_URL . '/' . $p;
}
/** Online-widget label: the registration/machine being valued if on a valuation form, else "online". */
function online_meta(?string $activity): string {
    $a = (string)$activity;
    if (stripos($a, 'valuation') !== false && mb_strpos($a, '—') !== false) {
        $reg = trim(mb_substr($a, mb_strpos($a, '—') + 1));
        if ($reg !== '') return '<b>' . e($reg) . '</b>';
    }
    return '<span class="muted">online</span>';
}

/** Developer credit line (compact). */
function dev_credit(): string {
    return 'Developed by <b>Nine One Two Holdings Limited</b> · '
         . '<a href="https://www.nineonetwo.co.ke" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">www.nineonetwo.co.ke</a> · '
         . '<a href="tel:+254722974970" style="color:inherit;text-decoration:none">0722 974 970</a>';
}
/** Full branded developer footer bar shown fixed at the bottom of every page (super-admin editable).
 *  $variant 'login' hides it on mobile / installed-app view. */
function dev_footer(string $variant = ''): string {
    $devName  = setting('footer_dev_name', 'Nine One Two Holdings Ltd');
    $website  = trim(setting('footer_website', 'www.nineonetwo.co.ke'));
    $phone    = trim(setting('footer_phone', '+254 722 974 970'));
    $servicesRaw = setting('footer_services', 'Enterprise Business Systems, Custom Software, AI & Automation, WhatsApp Integrations, IT Security, IT Infrastructure, Network Solutions, Cloud Services, CCTV & Access Control, VoIP & PBX, Managed IT Services');
    $services = array_filter(array_map('trim', preg_split('/[\n,]+/', $servicesRaw)));
    $links = implode('<span class="df-sep">·</span>', array_map(fn($s) => '<span>' . e($s) . '</span>', $services));

    $webHref = $website === '' ? '' : (preg_match('#^https?://#i', $website) ? $website : 'https://' . $website);
    $digits  = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($digits, '0') === 0) $digits = '254' . substr($digits, 1); // local -> intl for WhatsApp
    $telHref = 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
    $waHref  = 'https://wa.me/' . $digits;

    $wa = '<svg viewBox="0 0 32 32" width="15" height="15" fill="currentColor" style="flex:0 0 auto"><path d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.1 1.6 5.9L4 29l8.3-1.6c1.7.9 3.7 1.4 5.7 1.4 6.6 0 12-5.4 12-12S22.6 3 16 3zm0 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-4.9 1 1-4.8-.3-.4a9.8 9.8 0 01-1.5-5.3c0-5.4 4.4-9.8 9.8-9.8s9.8 4.4 9.8 9.8-4.4 9.9-9.5 9.9zm5.4-7.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-.9 1.2-.3.2-.6.1a8 8 0 01-2.4-1.5 9 9 0 01-1.6-2c-.2-.3 0-.5.1-.6l.5-.5.3-.5c.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.3 5.2 4.6 2.6 1.1 3.1.9 3.7.8.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.2-.3-.2-.6-.4z"/></svg>';

    $contacts = '';
    if ($website !== '') $contacts .= '<a href="' . e($webHref) . '" target="_blank" rel="noopener"><span class="df-ic"><i data-lucide="globe-2"></i></span>' . e($website) . '</a>';
    if ($phone !== '')   $contacts .= '<a href="' . e($telHref) . '"><span class="df-ic"><i data-lucide="phone-call"></i></span>' . e($phone) . '</a>';
    if ($digits !== '')  $contacts .= '<a href="' . e($waHref) . '" target="_blank" rel="noopener" title="Chat on WhatsApp"><span class="df-ic wa">' . $wa . '</span>WhatsApp</a>';

    $cls = 'site-footer' . ($variant === 'login' ? ' sf-login' : '');
    return '<style>
      .site-footer{background:#0c0f13;border-top:2px solid #d41d1d;color:#c7ccd4}
      .df-inner{max-width:none;margin:0;padding:10px 16px;display:flex;flex-direction:column;align-items:flex-start;gap:3px;text-align:left}
      .df-top{display:flex;align-items:center;gap:8px 18px;flex-wrap:wrap}
      .df-dev{font-size:13.5px;color:#e6e9ee}.df-dev b{color:#fff}
      .df-contacts{display:inline-flex;align-items:center;gap:8px 16px;flex-wrap:wrap}
      .df-contacts a{color:#c7ccd4;text-decoration:none;display:inline-flex;align-items:center;gap:7px;font-size:13px}
      .df-contacts a:hover{color:#fff}
      .df-ic{width:23px;height:23px;border-radius:50%;background:#161b22;border:1px solid #262d36;display:inline-flex;align-items:center;justify-content:center;color:#d41d1d}
      .df-ic i{width:13px;height:13px}
      .df-ic.wa{background:#0d3d2b;border-color:#125a3f;color:#25d366}
      .df-links{font-size:11.5px;color:#8a93a0;line-height:1.5;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .df-links .df-sep{margin:0 6px;color:#3a4048}
      @media(min-width:861px){ .site-footer{position:fixed;left:0;right:0;bottom:0;z-index:500} body{padding-bottom:70px} .fab{bottom:84px} }
      @media(max-width:860px){.site-footer{margin-top:20px}.df-links{white-space:normal}.site-footer.sf-login{display:none}}
      @media(display-mode:standalone){.site-footer.sf-login{display:none}}
    </style>
    <footer class="' . $cls . '"><div class="df-inner">
      <div class="df-top">
        <span class="df-dev">Developed by <b>' . e($devName) . '</b></span>
        <span class="df-contacts">' . $contacts . '</span>
      </div>
      <div class="df-links">' . $links . '</div>
    </div></footer>';
}

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

/** Rough device class from the User-Agent: 'mobile' or 'desktop'. */
function device_class(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/Mobi|Android|iPhone|iPod|IEMobile|BlackBerry|Opera Mini|Windows Phone/i', $ua) ? 'mobile' : 'desktop';
}
/** Max devices a user may be signed in on at once. */
if (!defined('MAX_SESSIONS')) define('MAX_SESSIONS', 2);
/** Record this login as one of the user's active sessions; keeps only the newest MAX_SESSIONS. */
function register_session(int $uid): void {
    if (!$uid || !table_exists('user_sessions')) return;
    $token = bin2hex(random_bytes(16));
    $_SESSION['sess_token'] = $token;
    try {
        db()->prepare("INSERT INTO user_sessions (user_id, token, device, created_at, last_seen) VALUES (?,?,?,NOW(),NOW())")
            ->execute([$uid, $token, device_class()]);
        // Keep only the newest MAX_SESSIONS for this user; a 3rd device evicts the oldest.
        db()->prepare("DELETE FROM user_sessions WHERE user_id=? AND id NOT IN
                       (SELECT id FROM (SELECT id FROM user_sessions WHERE user_id=? ORDER BY id DESC LIMIT " . (int)MAX_SESSIONS . ") t)")
            ->execute([$uid, $uid]);
    } catch (Throwable $e) {}
}
/** True if this session is still one of the user's active sessions (not evicted by newer logins). */
function session_is_current(): bool {
    if (!table_exists('user_sessions')) return true;      // pre-migration
    $u = current_user(); if (!$u) return false;
    $tok = $_SESSION['sess_token'] ?? '';
    if ($tok === '') return true;                          // session predates this feature
    try {
        $st = db()->prepare("SELECT id FROM user_sessions WHERE user_id=? AND token=? LIMIT 1");
        $st->execute([(int)$u['id'], $tok]);
        if ($st->fetchColumn() === false) return false;   // evicted (3rd device pushed this one out)
        db()->prepare("UPDATE user_sessions SET last_seen=NOW() WHERE token=?")->execute([$tok]);
        return true;
    } catch (Throwable $e) { return true; }
}
/** Mark a user/portal-user as recently active (drives the 30-day clock). Throttled to ~2×/day. */
function bump_last_seen(string $table, int $id): void {
    if (!$id) return;
    $flag = 'lastseen_' . $table;
    if (!empty($_SESSION[$flag]) && (time() - $_SESSION[$flag] < 43200)) return;
    $_SESSION[$flag] = time();
    if (column_exists($table, 'last_login_at')) {
        try { db()->prepare("UPDATE `$table` SET last_login_at=NOW() WHERE id=?")->execute([$id]); } catch (Throwable $e) {}
    }
}
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
/** "Sign all matching" bulk button — super admin can switch it off (default on). */
function sign_all_enabled(): bool { return setting('sign_all_enabled', '1') === '1'; }
/** Photo-to-text (OCR) scanning — prototype. Always on for the super admin;
 *  the super admin flips it on for everyone else via settings. */
function ocr_enabled_for_user(): bool { return is_superadmin() || setting('ocr_enabled', '0') === '1'; }
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
    return ['requested' => 'Requested', 'accepted' => 'Accepted', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'complete' => 'Complete', 'denied' => 'Denied', 'cancelled' => 'Cancelled'];
}
function request_status_label(string $s): string { return request_statuses()[$s] ?? ucfirst(str_replace('_', ' ', $s)); }
/** Coloured pill for a request status (uses the .badge / .b-* classes). */
function request_badge(string $s): string {
    $cls = ['requested' => 'b-amber', 'accepted' => 'b-blue', 'assigned' => 'b-blue', 'in_progress' => 'b-blue', 'complete' => 'b-green', 'denied' => 'b-red', 'cancelled' => 'b-grey'][$s] ?? 'b-grey';
    return '<span class="badge ' . $cls . '">' . e(request_status_label($s)) . '</span>';
}

// ---------------- Email (SMTP-aware) ----------------
/** Current mail configuration (customer SMTP + From identity). */
function mail_settings(): array {
    return [
        'host'     => trim(setting('smtp_host', '')),
        'port'     => (int)(setting('smtp_port', '587') ?: 587),
        'secure'   => strtolower(trim(setting('smtp_secure', 'tls'))), // tls | ssl | none
        'user'     => trim(setting('smtp_user', '')),
        'pass'     => (string)setting('smtp_pass', ''),
        'from'     => trim(setting('mail_from', '')) ?: trim(setting('company_email', '')) ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')),
        'fromname' => trim(setting('mail_from_name', '')) ?: setting('company_name', 'Kennet Automobile Valuers'),
    ];
}
/** True when a customer SMTP server has been configured. */
function smtp_configured(): bool { return mail_settings()['host'] !== ''; }

/** Low-level SMTP delivery of a pre-built message. Returns true on a 250 after DATA. */
function smtp_send(array $cfg, array $to, string $data): bool {
    $host = $cfg['host']; $port = $cfg['port'] ?: 587; $secure = $cfg['secure'];
    $timeout = 20;
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return false;
    stream_set_timeout($fp, $timeout);
    $read = function () use ($fp) { $d = ''; while (($line = fgets($fp, 515)) !== false) { $d .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return $d; };
    $cmd = function ($s) use ($fp, $read) { fwrite($fp, $s . "\r\n"); return $read(); };
    $ok = fn($r, $code) => strpos($r, $code) === 0;
    $read(); // greeting
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $cmd($ehlo);
    if ($secure === 'tls') {
        if (!$ok($cmd('STARTTLS'), '220')) { fclose($fp); return false; }
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        if (!@stream_socket_enable_crypto($fp, true, $crypto)) { fclose($fp); return false; }
        $cmd($ehlo);
    }
    if ($cfg['user'] !== '') {
        $cmd('AUTH LOGIN'); $cmd(base64_encode($cfg['user']));
        if (!$ok($cmd(base64_encode($cfg['pass'])), '235')) { fclose($fp); return false; }
    }
    if (!$ok($cmd('MAIL FROM:<' . $cfg['from'] . '>'), '250')) { fclose($fp); return false; }
    foreach ($to as $addr) $cmd('RCPT TO:<' . $addr . '>');
    if (!$ok($cmd('DATA'), '354')) { fclose($fp); return false; }
    $data = preg_replace('/^\./m', '..', $data); // dot-stuffing
    fwrite($fp, $data . "\r\n.\r\n");
    $r = $read();
    $cmd('QUIT'); fclose($fp);
    return $ok($r, '250');
}

/** Max emails the system may send per rolling hour (super-admin editable). 0 = unlimited. */
function email_hourly_cap(): int { $n = (int)setting('email_hourly_cap', '30'); return max(0, $n); }
/** Count of emails actually sent in the last rolling hour. */
function emails_sent_last_hour(): int {
    if (!table_exists('email_queue')) return 0;
    try {
        $s = db()->query("SELECT COUNT(*) FROM email_queue WHERE status='sent' AND sent_at > (NOW() - INTERVAL 1 HOUR)");
        return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
/** Log a lightweight 'sent' row (for hourly counting). */
function email_log_sent(array $to, string $subject): void {
    if (!table_exists('email_queue')) return;
    try { db()->prepare("INSERT INTO email_queue (recipients,subject,status,created_at,sent_at) VALUES (?,?, 'sent', NOW(), NOW())")
        ->execute([implode(',', $to), mb_substr($subject, 0, 250)]); } catch (Throwable $e) {}
}
/** Hold an email for later delivery (over the hourly cap). */
function email_enqueue(array $to, string $subject, string $body, bool $html, array $attachments): void {
    $atts = [];
    foreach ($attachments as $a) $atts[] = ['name' => $a['name'], 'type' => $a['type'] ?? 'application/octet-stream', 'data' => base64_encode($a['data'])];
    try { db()->prepare("INSERT INTO email_queue (recipients,subject,body,is_html,attachments,status,created_at) VALUES (?,?,?,?,?, 'pending', NOW())")
        ->execute([implode(',', $to), $subject, $body, $html ? 1 : 0, $atts ? json_encode($atts) : null]); } catch (Throwable $e) {}
}
/** Deliver queued emails while there is remaining hourly capacity. Returns number sent. */
function dispatch_email_queue(int $max = 0): int {
    if (!table_exists('email_queue')) return 0;
    $cap = email_hourly_cap();
    $sent = 0;
    try {
        $room = $cap === 0 ? 200 : ($cap - emails_sent_last_hour());
        if ($room <= 0) return 0;
        if ($max > 0) $room = min($room, $max);
        $rows = db()->query("SELECT * FROM email_queue WHERE status='pending' ORDER BY id ASC LIMIT $room")->fetchAll();
        foreach ($rows as $r) {
            $atts = $r['attachments'] ? (json_decode($r['attachments'], true) ?: []) : [];
            foreach ($atts as &$a) { if (isset($a['data'])) $a['data'] = base64_decode($a['data']); } unset($a);
            $to = array_values(array_filter(array_map('trim', explode(',', $r['recipients']))));
            $ok = mail_deliver($to, (string)$r['subject'], (string)$r['body'], (int)$r['is_html'] === 1, $atts);
            if ($ok) { db()->prepare("UPDATE email_queue SET status='sent', sent_at=NOW(), attempts=attempts+1, body=NULL, attachments=NULL WHERE id=?")->execute([$r['id']]); $sent++; }
            else     { db()->prepare("UPDATE email_queue SET attempts=attempts+1, status=IF(attempts>=9,'failed','pending') WHERE id=?")->execute([$r['id']]); }
            if ($cap !== 0 && emails_sent_last_hour() >= $cap) break;
        }
    } catch (Throwable $e) {}
    return $sent;
}

/** Test the SMTP connection & login without sending mail. Returns [ok(bool), message(string)]. */
function smtp_test(array $cfg): array {
    $host = $cfg['host'];
    if ($host === '') return [false, 'No SMTP host is set — the system is using the server’s built-in PHP mail().'];
    $port = $cfg['port'] ?: 587; $secure = $cfg['secure']; $timeout = 15;
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, "Could not connect to $host:$port — " . ($errstr ?: 'no response') . " (#$errno)"];
    stream_set_timeout($fp, $timeout);
    $read = function () use ($fp) { $d = ''; while (($line = fgets($fp, 515)) !== false) { $d .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return $d; };
    $cmd = function ($s) use ($fp, $read) { fwrite($fp, $s . "\r\n"); return $read(); };
    $ok = fn($r, $code) => strpos($r, $code) === 0;
    if (!$ok($read(), '220')) { fclose($fp); return [false, 'Connected, but the server did not greet correctly.']; }
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    if (!$ok($cmd($ehlo), '250')) { fclose($fp); return [false, 'Server refused EHLO (handshake).']; }
    if ($secure === 'tls') {
        if (!$ok($cmd('STARTTLS'), '220')) { fclose($fp); return [false, 'Server refused STARTTLS — try SSL/TLS (465) or a different port.']; }
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        if (!@stream_socket_enable_crypto($fp, true, $crypto)) { fclose($fp); return [false, 'TLS negotiation failed.']; }
        $cmd($ehlo);
    }
    if ($cfg['user'] !== '') {
        $cmd('AUTH LOGIN'); $cmd(base64_encode($cfg['user']));
        if (!$ok($cmd(base64_encode($cfg['pass'])), '235')) { fclose($fp); return [false, 'Connected, but authentication was rejected — check the username & password.']; }
        $cmd('QUIT'); fclose($fp);
        return [true, "Success — connected to $host:$port and logged in."];
    }
    $cmd('QUIT'); fclose($fp);
    return [true, "Success — connected to $host:$port (no username configured)."];
}

/**
 * Public mailer: rate-limited (per hour) with a queue for overflow.
 * Sends now if under the cap, otherwise queues for a later hour. Returns true if sent or queued.
 */
function app_mail($to, string $subject, string $body, bool $html = false, array $attachments = []): bool {
    $to = is_array($to) ? array_values(array_filter(array_unique($to))) : [$to];
    if (!$to) return false;
    // Opportunistically flush any backlog that now fits within capacity.
    dispatch_email_queue();
    $cap = email_hourly_cap();
    if ($cap > 0 && table_exists('email_queue') && emails_sent_last_hour() >= $cap) {
        email_enqueue($to, $subject, $body, $html, $attachments); // cued for the next hour
        return true;
    }
    $ok = mail_deliver($to, $subject, $body, $html, $attachments);
    if ($ok) email_log_sent($to, $subject);
    return $ok;
}

/**
 * Actually build & deliver an email through the customer's SMTP (if configured) or PHP mail().
 * $attachments: list of ['name'=>, 'data'=>, 'type'=>].
 */
function mail_deliver($to, string $subject, string $body, bool $html = false, array $attachments = []): bool {
    $to = is_array($to) ? array_values(array_filter(array_unique($to))) : [$to];
    if (!$to) return false;
    $cfg = mail_settings();
    $eol = "\r\n";
    $ctype = ($html ? 'text/html' : 'text/plain') . '; charset=UTF-8';
    $headers = ['MIME-Version: 1.0'];
    if ($attachments) {
        $b = '=_' . md5(uniqid('', true));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $b . '"';
        $msg  = '--' . $b . $eol . 'Content-Type: ' . $ctype . $eol . 'Content-Transfer-Encoding: 8bit' . $eol . $eol . $body . $eol . $eol;
        foreach ($attachments as $att) {
            $msg .= '--' . $b . $eol
                 . 'Content-Type: ' . ($att['type'] ?? 'application/octet-stream') . '; name="' . $att['name'] . '"' . $eol
                 . 'Content-Transfer-Encoding: base64' . $eol
                 . 'Content-Disposition: attachment; filename="' . $att['name'] . '"' . $eol . $eol
                 . chunk_split(base64_encode($att['data'])) . $eol;
        }
        $msg .= '--' . $b . '--';
        $body = $msg;
    } else {
        $headers[] = 'Content-Type: ' . $ctype;
    }
    $fromH = 'From: ' . $cfg['fromname'] . ' <' . $cfg['from'] . '>';
    $replyH = 'Reply-To: ' . $cfg['from'];
    if (!smtp_configured()) {
        $h = $fromH . $eol . $replyH . $eol . implode($eol, $headers) . $eol . 'X-Mailer: KennetVS';
        $ok = false;
        foreach ($to as $a) { if (@mail($a, $subject, $body, $h)) $ok = true; }
        return $ok;
    }
    $data = $fromH . $eol . 'To: ' . implode(', ', $to) . $eol . 'Subject: ' . $subject . $eol
          . 'Date: ' . date('r') . $eol . $replyH . $eol . implode($eol, $headers) . $eol . $eol . $body;
    return smtp_send($cfg, $to, $data);
}

// ---------------- Notifications (request workflow) ----------------
/** Send a plain-text email (via customer SMTP if configured, else PHP mail()). */
function send_mail($to, string $subject, string $body): bool {
    return app_mail($to, $subject, $body, false);
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
/** A portal user's email by id. */
function client_user_email(?int $id): ?string {
    if (!$id) return null;
    try { $s = db()->prepare('SELECT email FROM client_users WHERE id=?'); $s->execute([$id]); return $s->fetchColumn() ?: null; }
    catch (Throwable $e) { return null; }
}
/** IDs of Kennet staff who can assign (admins + coordinators). */
function assigner_ids(): array {
    try { return db()->query("SELECT id FROM users WHERE (role='admin' OR role='coordinator') AND (active=1 OR active IS NULL)")->fetchAll(PDO::FETCH_COLUMN) ?: []; }
    catch (Throwable $e) { return []; }
}

// ---------------- In-app notifications ----------------
/** Create a notification for one user. audience: 'staff' (users) or 'client' (client_users). */
function notify_user(string $audience, ?int $userId, string $title, string $body = '', string $url = ''): void {
    if (!$userId || !table_exists('notifications')) return;
    $aud = $audience === 'client' ? 'client' : 'staff';
    try { db()->prepare("INSERT INTO notifications (audience,user_id,title,body,url,created_at) VALUES (?,?,?,?,?,NOW())")
        ->execute([$aud, (int)$userId, mb_substr($title, 0, 250), $body, $url]); } catch (Throwable $e) {}
}
/** Create the same notification for many staff users. */
function notify_staff(array $userIds, string $title, string $body = '', string $url = ''): void {
    foreach (array_unique(array_filter(array_map('intval', $userIds))) as $id) notify_user('staff', $id, $title, $body, $url);
}
function notif_unread_count(string $aud, int $uid): int {
    if (!$uid || !table_exists('notifications')) return 0;
    try { $s = db()->prepare("SELECT COUNT(*) FROM notifications WHERE audience=? AND user_id=? AND read_at IS NULL"); $s->execute([$aud, $uid]); return (int)$s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
function notif_list(string $aud, int $uid, int $limit = 20): array {
    if (!$uid || !table_exists('notifications')) return [];
    $limit = max(1, min(50, $limit));
    try { $s = db()->prepare("SELECT id,title,body,url,read_at,created_at FROM notifications WHERE audience=? AND user_id=? ORDER BY id DESC LIMIT $limit"); $s->execute([$aud, $uid]); return $s->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function notif_mark_read(string $aud, int $uid, array $ids = []): void {
    if (!$uid || !table_exists('notifications')) return;
    try {
        if ($ids) { $in = implode(',', array_fill(0, count($ids), '?')); db()->prepare("UPDATE notifications SET read_at=NOW() WHERE audience=? AND user_id=? AND read_at IS NULL AND id IN ($in)")->execute(array_merge([$aud, $uid], array_map('intval', $ids))); }
        else      { db()->prepare("UPDATE notifications SET read_at=NOW() WHERE audience=? AND user_id=? AND read_at IS NULL")->execute([$aud, $uid]); }
    } catch (Throwable $e) {}
}
/** IDs of a company's portal admins (optionally excluding one user). */
function client_admin_ids(int $clientId, int $except = 0): array {
    if (!$clientId) return [];
    try {
        $s = db()->prepare("SELECT id FROM client_users WHERE client_id=? AND role='admin' AND (active=1 OR active IS NULL)");
        $s->execute([$clientId]);
        $ids = $s->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) { return []; }
    return array_values(array_filter(array_map('intval', $ids), fn($i) => $i !== $except));
}
/** Notify a company's portal admins (excluding $except) — for team-request oversight. */
function notify_client_admins(int $clientId, string $title, string $body = '', string $url = '', int $except = 0): void {
    foreach (client_admin_ids($clientId, $except) as $id) notify_user('client', $id, $title, $body, $url);
}
/** Audience + id of the current viewer (staff or portal), or [null,0]. */
function current_audience(): array {
    if ($u = current_user()) return ['staff', (int)($u['id'] ?? 0)];
    if ($c = current_client()) return ['client', (int)($c['id'] ?? 0)];
    return [null, 0];
}
/** New request raised by a portal officer -> notify Kennet assigners. */
function notify_new_request(int $rid, string $reg, string $type, ?array $client): void {
    $co = lookup_name('clients', $client['client_id'] ?? null) ?: 'A client';
    $who = $client['name'] ?? 'A portal user';
    $label = $type === 'insurance' ? 'insurance' : ($type === 'machine' ? 'machine' : 'bank');
    send_mail(assigner_emails(),
        "New valuation request: $reg",
        "$who ($co) requested a $label valuation.\n\nReg No: $reg\n\nLog in to assign a valuer.");
    notify_staff(assigner_ids(), "New valuation request: $reg", "$who ($co) requested a $label valuation.", 'requests.php');
}
/** Request assigned to a Kennet officer -> notify that officer. */
function notify_assigned(int $rid, string $reg, ?int $officerId): void {
    if (!$officerId) return;
    $email = user_email($officerId);
    if ($email) send_mail($email, "Valuation assigned to you: $reg",
        "A valuation has been assigned to you.\n\nReg No: $reg\n\nOpen the system to complete it.");
    notify_user('staff', $officerId, "Valuation assigned to you: $reg", "Open it to complete the valuation.", 'dashboard.php');
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
    if (!session_is_current()) { logout(); redirect('login.php?e=other'); }
    if (system_locked() && !is_superadmin()) { logout(); deny_unavailable(); }
    bump_last_seen('users', (int)(current_user()['id'] ?? 0));
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
        $base = self_page();
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
    if (!$ok) { record_login_attempt($email, $ip, false); return false; }

    $isSuper = strtolower(trim((string)($u['email'] ?? ''))) === strtolower(SUPERADMIN_EMAIL);

    // Already disabled — say why if it was auto-disabled for inactivity.
    if (array_key_exists('active', $u) && (int)$u['active'] !== 1) {
        record_login_attempt($email, $ip, false);
        return (($u['disabled_reason'] ?? '') === 'inactivity') ? 'expired' : 'disabled';
    }
    // Auto-disable after 30 days without logging in (never super admins or admins).
    $isAdminRole = ($u['role'] ?? '') === 'admin';
    if (!$isSuper && !$isAdminRole && !empty($u['last_login_at'])) {
        $last = strtotime((string)$u['last_login_at']);
        if ($last && (time() - $last) > 30 * 86400) {
            try { db()->prepare("UPDATE users SET active=0, disabled_reason='inactivity' WHERE id=?")->execute([$u['id']]); } catch (Throwable $e) {}
            record_login_attempt($email, $ip, false);
            return 'expired';
        }
    }

    record_login_attempt($email, $ip, true);
    unset($u['password']);
    session_regenerate_id(true);
    $_SESSION['user'] = $u;
    $_SESSION['login_at'] = time();
    if (column_exists('users', 'last_login_at')) { try { db()->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]); } catch (Throwable $e) {} }
    register_session((int)$u['id']);
    audit('login', 'user', $u['id']);
    return true;
}

function logout(): void {
    if ($u = current_user()) {
        audit('logout', 'user', $u['id']);
        try { db()->prepare('DELETE FROM user_activity WHERE user_id=?')->execute([$u['id']]); } catch (Throwable $e) {}
        if (table_exists('user_sessions') && !empty($_SESSION['sess_token'])) {
            try { db()->prepare('DELETE FROM user_sessions WHERE token=?')->execute([$_SESSION['sess_token']]); } catch (Throwable $e) {}
        }
    }
    $_SESSION = []; session_destroy();
}

// ---------------- Client portal auth (separate from staff) ----------------
function current_client(): ?array { return $_SESSION['client_user'] ?? null; }
function require_client(): void {
    if (!module_enabled('portal')) { if (current_client()) client_logout(); http_response_code(403); exit('The client portal is currently unavailable.'); }
    if (!current_client()) redirect('portal_login.php');
    if (system_locked()) { client_logout(); deny_unavailable(); }
    bump_last_seen('client_users', (int)(current_client()['id'] ?? 0));
}

function attempt_client_login(string $email, string $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (recent_failed_logins($email, $ip) >= 8) return 'locked';
    $st = db()->prepare('SELECT * FROM client_users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password'])) { record_login_attempt($email, $ip, false); return false; }

    // Already disabled — say why if it was auto-disabled for inactivity.
    if ((int)($u['active'] ?? 1) !== 1) {
        record_login_attempt($email, $ip, false);
        return (($u['disabled_reason'] ?? '') === 'inactivity') ? 'expired' : 'disabled';
    }
    // Auto-disable after 30 days without logging in.
    if (!empty($u['last_login_at'])) {
        $last = strtotime((string)$u['last_login_at']);
        if ($last && (time() - $last) > 30 * 86400) {
            try { db()->prepare("UPDATE client_users SET active=0, disabled_reason='inactivity' WHERE id=?")->execute([$u['id']]); } catch (Throwable $e) {}
            record_login_attempt($email, $ip, false);
            return 'expired';
        }
    }

    record_login_attempt($email, $ip, true);
    unset($u['password']);
    session_regenerate_id(true);
    $_SESSION['client_user'] = $u;
    if (column_exists('client_users', 'last_login_at')) { try { db()->prepare("UPDATE client_users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]); } catch (Throwable $e) {} }
    audit('portal_login', 'client_user', $u['id'], $email);
    return true;
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
        $st = db()->prepare("SELECT vr.id, vr.reg_no, vr.status, vr.requested_by, vr.client_id, cu.email
                             FROM valuation_requests vr
                             LEFT JOIN client_users cu ON cu.id = vr.requested_by
                             WHERE vr.valuation_table=? AND vr.valuation_id IN ($in)
                               AND vr.status NOT IN ('complete','cancelled')");
        $st->execute(array_merge([$table], $ids));
        foreach ($st->fetchAll() as $r) {
            db()->prepare("UPDATE valuation_requests SET status='complete', updated_at=NOW() WHERE id=?")->execute([$r['id']]);
            notify_complete((int)$r['id'], (string)$r['reg_no'], $r['email'] ?? null);
            notify_user('client', (int)($r['requested_by'] ?? 0), 'Valuation ready: ' . $r['reg_no'], 'Your valuation report is complete and ready to download.', 'portal_requests.php');
            notify_client_admins((int)($r['client_id'] ?? 0), 'Valuation ready: ' . $r['reg_no'], 'A valuation requested by your team is complete.', 'portal_requests.php', (int)($r['requested_by'] ?? 0));
        }
    } catch (Throwable $e) {}
}

/** True if a table exists in the current database (cached). */
function table_exists(string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->execute([$table]);
        return $cache[$table] = ((int)$st->fetchColumn() > 0);
    } catch (Throwable $e) { return $cache[$table] = false; }
}
/**
 * Atomically allocate the next value for a named counter (race-safe).
 * On first use the row is seeded with $seed (typically the current max) so new
 * numbers never collide with records created before the sequences table existed.
 * Falls back to $seed+1 (best-effort) if schema_v12 hasn't been run yet.
 */
function next_sequence(string $key, int $seed = 0): int {
    if (!table_exists('sequences')) return $seed + 1;
    try {
        db()->prepare("INSERT IGNORE INTO sequences (name, value) VALUES (?, ?)")->execute([$key, $seed]);
        db()->prepare("UPDATE sequences SET value = LAST_INSERT_ID(value + 1) WHERE name = ?")->execute([$key]);
        return (int)db()->query("SELECT LAST_INSERT_ID()")->fetchColumn();
    } catch (Throwable $e) { return $seed + 1; }
}

/** Generate the next serial, e.g. 079/06/2026 = running number this month / month / year.
 *  The monthly counter is shared across bank/insurance/machine so serials are unique. */
function next_serial(): string {
    $y = date('Y'); $m = (int)date('m');
    // Seed = count of everything already created this month across all valuation types.
    $seed = 0;
    foreach (['bankvaluations', 'valuations', 'machinevaluations'] as $t) {
        if (!table_exists($t)) continue;
        try {
            $st = db()->prepare("SELECT COUNT(*) FROM `$t` WHERE YEAR(created_at)=? AND MONTH(created_at)=?");
            $st->execute([$y, $m]); $seed += (int)$st->fetchColumn();
        } catch (Throwable $e) {}
    }
    $seq = next_sequence(sprintf('serial-%04d-%02d', $y, $m), $seed);
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

/** Generate next sequential report number, e.g. KEN/2026/0007 (atomic, per table per year). */
function next_report_no(string $table): string {
    $prefix = setting('report_prefix', 'KEN');
    $year = date('Y');
    $seed = 0;
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE YEAR(created_at) = ?");
        $st->execute([$year]); $seed = (int)$st->fetchColumn();
    } catch (Throwable $e) {}
    $seq = next_sequence("report-$table-$year", $seed);
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
/** Map a report type key to its valuation table. */
function type_table(?string $t): string {
    return ['insurance' => 'valuations', 'machine' => 'machinevaluations'][$t] ?? 'bankvaluations';
}
/** The column holding the client-side officer who requested the valuation, per table. */
function requesting_officer_field(string $table): string {
    return ['bankvaluations' => 'bank_officer', 'valuations' => 'insurance_officer', 'machinevaluations' => 'officer'][$table] ?? 'officer';
}
/** Human label for that officer field, per table. */
function requesting_officer_label(string $table): string {
    return ['bankvaluations' => 'Bank Officer', 'valuations' => 'Insurance Officer', 'machinevaluations' => 'Officer'][$table] ?? 'Officer';
}
/** The requesting-officer value from a valuation row. */
function requesting_officer(array $row, string $table): string {
    return (string)($row[requesting_officer_field($table)] ?? '');
}
/** Portal officers may only open reports for valuations tied to requests they raised. Admins: any in their company. */
function portal_may_view(string $table, int $valuationId): bool {
    if (client_is_admin()) return true;
    try {
        $st = db()->prepare("SELECT 1 FROM valuation_requests WHERE requested_by=? AND valuation_table=? AND valuation_id=? LIMIT 1");
        $st->execute([(int)(current_client()['id'] ?? 0), $table, $valuationId]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
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
    try { return (int)db()->query("SELECT COUNT(*) FROM valuation_requests WHERE status IN ('requested','accepted')")->fetchColumn(); }
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
