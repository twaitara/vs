<?php
/** Shared PDF generation via the bundled Dompdf. */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/dompdf_autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** Render HTML to PDF bytes (A4 portrait). */
function make_pdf(string $html): string {
    $tmp = UPLOAD_DIR . '/.dompdf';
    if (!is_dir($tmp)) @mkdir($tmp, 0775, true);

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Arial');
    $options->set('dpi', 96);
    $options->set('tempDir', $tmp);
    $options->set('chroot', __DIR__);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}
