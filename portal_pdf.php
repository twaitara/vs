<?php
require_once __DIR__ . '/report_template.php';
require_once __DIR__ . '/pdf.php';
require_client();

$cid  = (int)current_client()['client_id'];
$type = ($_GET['type'] ?? 'bank') === 'insurance' ? 'insurance' : 'bank';
$val  = $type === 'insurance' ? load_insurance_valuation($_GET['id'] ?? null) : load_bank_valuation($_GET['id'] ?? null);

if (!$val || (int)($val['client'] ?? -1) !== $cid) { http_response_code(403); exit('Not available.'); }

$html = $type === 'insurance' ? render_insurance_report($val) : render_bank_report($val);
$pdf  = make_pdf($html);
$fn   = 'valuation-' . preg_replace('/[^A-Za-z0-9_-]/', '', $val['reg_no'] ?? $val['id']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fn . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
