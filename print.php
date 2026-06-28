<?php
require_once __DIR__ . '/report_template.php';
require_once __DIR__ . '/dompdf_autoload.php';
require_login();

use Dompdf\Dompdf;
use Dompdf\Options;

$val = load_bank_valuation($_GET['id'] ?? null);
if (!$val) { http_response_code(404); exit('Valuation not found'); }

$html = render_bank_report($val);

// Writable temp dir for Dompdf font cache (created under uploads/).
$tmp = UPLOAD_DIR . '/.dompdf';
if (!is_dir($tmp)) @mkdir($tmp, 0775, true);

$options = new Options();
$options->set('isRemoteEnabled', true);          // allow embedded data: URIs / assets
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');
$options->set('dpi', 96);
$options->set('tempDir', $tmp);
// Leave fontDir/fontCache at the bundled defaults (lib_dompdf/dompdf/lib/fonts).
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'vehicle-valuation-report-' . preg_replace('/[^A-Za-z0-9_-]/', '', $val['reg_no'] ?? $val['id']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);   // download
