<?php
require_once __DIR__ . '/report_template.php';
require_once __DIR__ . '/pdf.php';
header('Content-Type: application/json');

if (!current_user() || !can_edit()) { echo json_encode(['ok' => false, 'error' => 'Not allowed']); exit; }
csrf_verify();

$type = $_POST['type'] ?? 'bank';
if (!in_array($type, ['bank', 'insurance', 'machine'], true)) $type = 'bank';
$id   = (int)($_POST['id'] ?? 0);
$to   = trim($_POST['to'] ?? '');

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok' => false, 'error' => 'Invalid email address']); exit; }

if ($type === 'insurance')   { $val = load_insurance_valuation($id); $render = 'render_insurance_report'; }
elseif ($type === 'machine') { $val = load_machine_valuation($id);   $render = 'render_machine_report'; }
else                         { $val = load_bank_valuation($id);      $render = 'render_bank_report'; }
if (!$val) { echo json_encode(['ok' => false, 'error' => 'Valuation not found']); exit; }

$html = $render($val);
try {
    $pdf = make_pdf($html);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'PDF generation failed']); exit;
}

$company  = setting('company_name', 'Kennet Valuers');
$reg      = $val['reg_no'] ?? ($val['machine_name'] ?? $id);
$filename = 'valuation-report-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$reg) . '.pdf';
$subject  = ucfirst($type) . ' Valuation Report — ' . $reg;

$body = "Dear Sir/Madam,\r\n\r\n"
      . "Please find attached the valuation report for $reg.\r\n\r\n"
      . "Regards,\r\n$company";

$ok = app_mail($to, $subject, $body, false, [['name' => $filename, 'data' => $pdf, 'type' => 'application/pdf']]);
if ($ok) {
    audit('email_report', $type, $id, $to);
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Mail server rejected the message (SMTP may need setup)']);
}
