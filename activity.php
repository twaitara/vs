<?php
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');

if (!current_user()) { echo json_encode(['ok' => false]); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { echo json_encode(['ok' => false]); exit; }

$type = $_POST['type'] ?? 'bank';
if (!in_array($type, ['bank', 'insurance', 'machine'], true)) $type = 'bank';
$mode = ($_POST['mode'] ?? 'New') === 'Editing' ? 'Editing' : 'New';
$reg  = strtoupper(preg_replace('/[^A-Za-z0-9 \-]/', '', trim($_POST['reg'] ?? '')));

touch_activity($mode . ' ' . $type . ' valuation' . ($reg !== '' ? ' — ' . $reg : ''));
echo json_encode(['ok' => true]);
