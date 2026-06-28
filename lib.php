<?php
/**
 * Shared helpers: DB connection, auth, lookups, formatting, number-to-words.
 */
require_once __DIR__ . '/config.php';

session_start();

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

/** Flash messages (one-shot). */
function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

// ---------------- Auth ----------------
function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(): void {
    if (!current_user()) redirect('login.php');
}

function attempt_login(string $email, string $password): bool {
    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password'])) {
        unset($u['password']);
        $_SESSION['user'] = $u;
        return true;
    }
    return false;
}

function logout(): void { $_SESSION = []; session_destroy(); }

// ---------------- Lookups ----------------
/** Fetch id=>name map from a lookup table (clients, fuels, types, insurers). */
function lookup(string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $rows = db()->query("SELECT id, name FROM `$table` ORDER BY name")->fetchAll();
    $map = [];
    foreach ($rows as $r) $map[$r['id']] = $r['name'];
    return $cache[$table] = $map;
}
function lookup_name(string $table, $id): string {
    $m = lookup($table); return $m[$id] ?? '';
}

/** Render <option> tags for a lookup, marking $selected. */
function options(string $table, $selected = null): string {
    $out = '<option value="">-- select --</option>';
    foreach (lookup($table) as $id => $name) {
        $sel = ((string)$id === (string)$selected) ? ' selected' : '';
        $out .= '<option value="' . e($id) . '"' . $sel . '>' . e($name) . '</option>';
    }
    return $out;
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
        $h = intdiv($g, 100);
        $rem = $g % 100;
        if ($h) $chunk .= $ones[$h] . ' hundred';
        if ($rem) {
            if ($chunk) $chunk .= ' ';
            if ($rem < 20) $chunk .= $ones[$rem];
            else {
                $chunk .= $tens[intdiv($rem, 10)];
                if ($rem % 10) $chunk .= '-' . $ones[$rem % 10];
            }
        }
        $parts[] = $chunk . $scales[$i];
    }
    return implode(' ', $parts);
}
