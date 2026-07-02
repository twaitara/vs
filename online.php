<?php
require_once __DIR__ . '/lib.php';
if (!current_user()) { exit; }

// Keep the viewer marked online (they're actively watching the dashboard).
touch_activity('Dashboard');

$online = [];
try {
    $rows = db()->query("SELECT ua.user_id, ua.name, ua.activity, u.role, u.email, TIMESTAMPDIFF(MINUTE, ua.login_at, NOW()) AS mins_online
              FROM user_activity ua LEFT JOIN users u ON u.id = ua.user_id
              WHERE ua.last_seen > (NOW() - INTERVAL 5 MINUTE) ORDER BY ua.last_seen DESC")->fetchAll();
    $seen = [];
    foreach ($rows as $r) {
        if (isset($seen[$r['user_id']])) continue;
        if (strtolower(trim((string)($r['email'] ?? ''))) === strtolower(SUPERADMIN_EMAIL)) continue; // super admin invisible
        if (!is_admin() && ($r['role'] ?? '') === 'admin') continue;
        $seen[$r['user_id']] = 1; $online[] = $r;
    }
} catch (Throwable $e) {}

if (!$online) { echo '<div class="ow-empty">No one else online.</div>'; exit; }
foreach ($online as $o) {
    $m = max(0, (int)$o['mins_online']);
    $dur = $m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : $m . 'm';
    $act = stripos((string)$o['activity'], 'valuation') !== false ? e($o['activity']) . ' · ' : '';
    echo '<div class="ow-row"><span class="ow-dot"></span><span class="ow-name">' . e($o['name'])
       . '</span><span class="ow-meta">' . $act . e($dur) . '</span></div>';
}
