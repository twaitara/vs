<?php
require_once __DIR__ . '/lib.php';
if (!current_user()) { exit; }

// Keep the viewer marked online (they're actively watching the dashboard).
touch_activity('Dashboard');

$online = [];
try {
    $rows = db()->query("SELECT user_id, name, activity, TIMESTAMPDIFF(MINUTE, login_at, NOW()) AS mins_online
              FROM user_activity WHERE last_seen > (NOW() - INTERVAL 5 MINUTE) ORDER BY last_seen DESC")->fetchAll();
    $seen = [];
    foreach ($rows as $r) { if (isset($seen[$r['user_id']])) continue; $seen[$r['user_id']] = 1; $online[] = $r; }
} catch (Throwable $e) {}

if (!$online) { echo '<div class="ow-empty">No one else online.</div>'; exit; }
foreach ($online as $o) {
    $m = max(0, (int)$o['mins_online']);
    $dur = $m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : $m . 'm';
    $act = stripos((string)$o['activity'], 'valuation') !== false ? e($o['activity']) . ' · ' : '';
    echo '<div class="ow-row"><span class="ow-dot"></span><span class="ow-name">' . e($o['name'])
       . '</span><span class="ow-meta">' . $act . e($dur) . '</span></div>';
}
