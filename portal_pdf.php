<?php
require_once __DIR__ . '/report_template.php';
require_once __DIR__ . '/pdf.php';
require_client();

$cid  = (int)current_client()['client_id'];
$type = $_GET['type'] ?? 'bank';
if (!in_array($type, ['bank', 'insurance', 'machine'], true)) $type = 'bank';
if ($type === 'insurance')   { $val = load_insurance_valuation($_GET['id'] ?? null); $render = 'render_insurance_report'; }
elseif ($type === 'machine') { $val = load_machine_valuation($_GET['id'] ?? null);   $render = 'render_machine_report'; }
else                         { $val = load_bank_valuation($_GET['id'] ?? null);      $render = 'render_bank_report'; }

if (!$val || (int)($val['client'] ?? -1) !== $cid) { http_response_code(403); exit('Not available.'); }
if (!portal_may_view(type_table($type), (int)$val['id'])) { http_response_code(403); exit('Not available.'); }

$html = $render($val);
$pdf  = make_pdf($html);
$fn   = 'valuation-' . preg_replace('/[^A-Za-z0-9_-]/', '', $val['reg_no'] ?? $val['id']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fn . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
