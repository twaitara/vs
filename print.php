<?php
require_once __DIR__ . '/report_template.php';
require_once __DIR__ . '/pdf.php';
require_login();

$type = ($_GET['type'] ?? 'bank') === 'insurance' ? 'insurance' : 'bank';
if ($type === 'insurance') {
    $val = load_insurance_valuation($_GET['id'] ?? null);
    if (!$val) { http_response_code(404); exit('Valuation not found'); }
    require_own_valuation($val);
    $html = render_insurance_report($val);
} else {
    $val = load_bank_valuation($_GET['id'] ?? null);
    if (!$val) { http_response_code(404); exit('Valuation not found'); }
    require_own_valuation($val);
    $html = render_bank_report($val);
}

$pdf = make_pdf($html);
$filename = 'valuation-report-' . preg_replace('/[^A-Za-z0-9_-]/', '', $val['reg_no'] ?? $val['id']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
