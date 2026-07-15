<?php
/** Server-side autosave of in-progress valuation drafts (so admins can see them). */
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');

$u = current_user();
if (!$u || !can_edit()) { echo json_encode(['ok' => false]); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { echo json_encode(['ok' => false]); exit; }
if (!table_exists('form_drafts')) { echo json_encode(['ok' => false, 'e' => 'migrate']); exit; }

$uid = (int)$u['id'];
$key = substr(trim($_POST['key'] ?? ''), 0, 40);
if ($key === '') { echo json_encode(['ok' => false]); exit; }
$action = $_POST['action'] ?? 'save';

try {
    if ($action === 'delete') {
        db()->prepare("DELETE FROM form_drafts WHERE user_id=? AND draft_key=?")->execute([$uid, $key]);
        echo json_encode(['ok' => true]); exit;
    }
    $form    = in_array($_POST['form'] ?? '', ['bank', 'insurance', 'machine'], true) ? $_POST['form'] : 'bank';
    $rid     = (int)($_POST['rid'] ?? 0) ?: null;
    $label   = substr(trim($_POST['label'] ?? ''), 0, 150);
    $payload = (string)($_POST['data'] ?? '');
    if (strlen($payload) > 300000) { echo json_encode(['ok' => false]); exit; }
    db()->prepare("INSERT INTO form_drafts (user_id, draft_key, form, record_id, label, payload, updated_at)
                   VALUES (?,?,?,?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE form=VALUES(form), record_id=VALUES(record_id),
                                           label=VALUES(label), payload=VALUES(payload), updated_at=NOW()")
        ->execute([$uid, $key, $form, $rid, $label, $payload]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) { echo json_encode(['ok' => false]); }
