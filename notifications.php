<?php
/** Notifications API for the top-bar bell (staff and portal). */
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');

[$aud, $uid] = current_audience();
if (!$aud || !$uid) { echo json_encode(['ok' => false]); exit; }

$action = $_GET['action'] ?? 'list';

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { echo json_encode(['ok' => false]); exit; }
    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    notif_mark_read($aud, $uid, $ids); // empty ids = mark all
    echo json_encode(['ok' => true, 'count' => notif_unread_count($aud, $uid)]);
    exit;
}

$items = array_map(function ($r) {
    return [
        'id'      => (int)$r['id'],
        'title'   => $r['title'],
        'body'    => $r['body'],
        'url'     => $r['url'] ? url($r['url']) : '',
        'read'    => !empty($r['read_at']),
        'created' => ddate($r['created_at']),
    ];
}, notif_list($aud, $uid, 20));

echo json_encode(['ok' => true, 'count' => notif_unread_count($aud, $uid), 'items' => $items]);
