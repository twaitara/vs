<?php
require_once __DIR__ . '/lib.php';
require_login();
if (!can_sign()) { http_response_code(403); exit('You do not have a signing mandate.'); }

$type  = $_GET['type'] ?? 'bank';
if (!in_array($type, ['bank', 'insurance', 'machine'], true)) $type = 'bank';
$table = $type === 'insurance' ? 'valuations' : ($type === 'machine' ? 'machinevaluations' : 'bankvaluations');
$id    = (int)($_GET['id'] ?? 0);

// CSRF guard for the GET action (token carried in the link).
if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['_csrf'] ?? '')) {
    http_response_code(403); exit('Invalid or expired token. Go back and try again.');
}

if ($id) {
    $n = sign_records($table, [$id]);
    audit('sign', $type, $id);
    flash($n ? 'Report signed and marked Complete.' : 'Could not sign (run schema_v6.sql).', $n ? 'ok' : 'err');
}
redirect($type === 'insurance' ? 'insurance_list.php' : ($type === 'machine' ? 'machine_list.php' : 'bank_list.php'));
