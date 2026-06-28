<?php
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');

if (!current_user() || !can_edit()) { echo json_encode(['ok' => false, 'error' => 'Not allowed']); exit; }
csrf_verify();

$allowed = ['clients', 'insurers', 'types'];
$type = $_POST['type'] ?? '';
$name = trim($_POST['name'] ?? '');

if (!in_array($type, $allowed, true) || $name === '') {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']); exit;
}

try {
    $now = date('Y-m-d H:i:s');
    $st = db()->prepare("INSERT INTO `$type` (name, created_at, updated_at) VALUES (?,?,?)");
    $st->execute([$name, $now, $now]);
    $id = db()->lastInsertId();
    audit('quick_add', $type, $id, $name);
    echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
