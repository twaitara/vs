<?php
require_once __DIR__ . '/report_template.php';
require_login();

$val = load_bank_valuation($_GET['id'] ?? null);
if (!$val) { http_response_code(404); exit('Valuation not found'); }

$html = render_bank_report($val);

// "bare=1" renders just the report (used inside the preview popup/iframe).
if (!empty($_GET['bare'])) {
    echo $html;
    exit;
}

// Standalone view: inject a small toolbar (hidden when printing).
$toolbar = '<div class="noprint" style="position:sticky;top:0;background:#171c22;padding:10px;text-align:center;z-index:9999">'
    . '<a href="' . url('bank_list.php') . '" style="background:#2b3340;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;margin:0 4px">← Back</a>'
    . '<a href="' . url('print.php?id=' . (int)$val['id']) . '" style="background:#d41d1d;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;margin:0 4px">⬇ Download PDF</a>'
    . '</div>'
    . '<style>@media print{.noprint{display:none!important}}</style>';

echo str_replace('<body>', '<body>' . $toolbar, $html);
