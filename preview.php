<?php
require_once __DIR__ . '/report_template.php';
require_login();

$type = $_GET['type'] ?? 'bank';
if (!in_array($type, ['bank', 'insurance', 'machine'], true)) $type = 'bank';
$id   = $_GET['id'] ?? null;

if ($type === 'insurance')    { $val = load_insurance_valuation($id); $render = 'render_insurance_report'; }
elseif ($type === 'machine')  { $val = load_machine_valuation($id);   $render = 'render_machine_report'; }
else                          { $val = load_bank_valuation($id);      $render = 'render_bank_report'; }
if (!$val) { http_response_code(404); exit('Valuation not found'); }
require_own_valuation($val);
$html = $render($val);

// "bare=1" renders just the report (used inside the preview popup/iframe).
if (!empty($_GET['bare'])) { echo $html; exit; }

$pdfUrl = url('print.php?type=' . $type . '&id=' . (int)$val['id']);
$backUrl = url($type === 'insurance' ? 'insurance_list.php' : ($type === 'machine' ? 'machine_list.php' : 'bank_list.php'));
$toolbar = '<div class="noprint" style="position:sticky;top:0;background:#171c22;padding:10px;text-align:center;z-index:9999">'
    . '<a href="' . $backUrl . '" style="background:#2b3340;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;margin:0 4px">← Back</a>'
    . '<a href="' . $pdfUrl . '" style="background:#d41d1d;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;margin:0 4px">⬇ Download PDF</a>'
    . '</div><style>@media print{.noprint{display:none!important}}</style>';

echo str_replace('<body>', '<body>' . $toolbar, $html);
