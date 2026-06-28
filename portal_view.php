<?php
require_once __DIR__ . '/report_template.php';
require_client();

$cid  = (int)current_client()['client_id'];
$type = ($_GET['type'] ?? 'bank') === 'insurance' ? 'insurance' : 'bank';
$val  = $type === 'insurance' ? load_insurance_valuation($_GET['id'] ?? null) : load_bank_valuation($_GET['id'] ?? null);

// Strict ownership: clients only ever see their own company's valuations.
if (!$val || (int)($val['client'] ?? -1) !== $cid) { http_response_code(403); exit('Not available.'); }

echo $type === 'insurance' ? render_insurance_report($val) : render_bank_report($val);
