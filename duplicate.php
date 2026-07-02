<?php
require_once __DIR__ . '/lib.php';
require_can_edit();

$map = [
    'bank'      => ['table' => 'bankvaluations', 'form' => 'bank_form.php',      'entity' => 'bankvaluation'],
    'insurance' => ['table' => 'valuations',     'form' => 'insurance_form.php', 'entity' => 'valuation'],
    'machine'   => ['table' => 'machinevaluations', 'form' => 'machine_form.php', 'entity' => 'machinevaluation'],
];
$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
if (!isset($map[$type]) || !$id) { http_response_code(400); exit('Bad request'); }

$cfg = $map[$type];
$st = db()->prepare("SELECT * FROM `{$cfg['table']}` WHERE id = ? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { http_response_code(404); exit('Not found'); }

// Drop fields that must not be copied.
foreach (['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by'] as $k) unset($row[$k]);

$now = date('Y-m-d H:i:s');
$row['created_at'] = $now;
$row['updated_at'] = $now;
if (column_exists($cfg['table'], 'created_by')) $row['created_by'] = current_user()['id'] ?? null;

$cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($row)));
$ph   = implode(', ', array_fill(0, count($row), '?'));
db()->prepare("INSERT INTO `{$cfg['table']}` ($cols) VALUES ($ph)")->execute(array_values($row));
$newId = (int)db()->lastInsertId();
audit('duplicate', $cfg['entity'], $newId, 'from #' . $id);
flash('Duplicate created — review and save.');
redirect($cfg['form'] . '?id=' . $newId);
