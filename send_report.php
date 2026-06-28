<?php
require_once __DIR__ . '/report_template.php';
require_once __DIR__ . '/pdf.php';
header('Content-Type: application/json');

if (!current_user() || !can_edit()) { echo json_encode(['ok' => false, 'error' => 'Not allowed']); exit; }
csrf_verify();

$type = ($_POST['type'] ?? 'bank') === 'insurance' ? 'insurance' : 'bank';
$id   = (int)($_POST['id'] ?? 0);
$to   = trim($_POST['to'] ?? '');

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok' => false, 'error' => 'Invalid email address']); exit; }

$val = $type === 'insurance' ? load_insurance_valuation($id) : load_bank_valuation($id);
if (!$val) { echo json_encode(['ok' => false, 'error' => 'Valuation not found']); exit; }

$html = $type === 'insurance' ? render_insurance_report($val) : render_bank_report($val);
try {
    $pdf = make_pdf($html);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'PDF generation failed']); exit;
}

$company  = setting('company_name', 'Kennet Valuers');
$from     = setting('company_email', 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
$reg      = $val['reg_no'] ?? $id;
$filename = 'valuation-report-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$reg) . '.pdf';
$subject  = ($type === 'insurance' ? 'Insurance' : 'Bank') . ' Valuation Report — ' . $reg;

$body = "Dear Sir/Madam,\r\n\r\n"
      . "Please find attached the valuation report for vehicle $reg.\r\n\r\n"
      . "Regards,\r\n$company";

$boundary = '=_' . md5(uniqid('', true));
$headers  = "From: $company <$from>\r\n"
          . "Reply-To: $from\r\n"
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$message  = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 8bit\r\n\r\n"
          . $body . "\r\n\r\n"
          . "--$boundary\r\n"
          . "Content-Type: application/pdf; name=\"$filename\"\r\n"
          . "Content-Transfer-Encoding: base64\r\n"
          . "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n"
          . chunk_split(base64_encode($pdf)) . "\r\n"
          . "--$boundary--";

$ok = @mail($to, $subject, $message, $headers);
if ($ok) {
    audit('email_report', $type, $id, $to);
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Mail server rejected the message (SMTP may need setup)']);
}
