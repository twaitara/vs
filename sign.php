<?php
require_once __DIR__ . '/lib.php';
require_admin();

$type  = ($_GET['type'] ?? 'bank') === 'insurance' ? 'insurance' : 'bank';
$table = $type === 'insurance' ? 'valuations' : 'bankvaluations';
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
redirect($type === 'insurance' ? 'insurance_list.php' : 'bank_list.php');
