<?php
/**
 * Database backup — downloads a .sql dump of the whole database.
 * Admin (logged in) OR cron with a matching token:
 *   https://site/vs/backup.php?token=YOUR_TOKEN
 * Set the token in Settings (key 'backup_token') or define BACKUP_TOKEN in config.
 */
require_once __DIR__ . '/lib.php';

$token = $_GET['token'] ?? '';
$validToken = (defined('BACKUP_TOKEN') && BACKUP_TOKEN && hash_equals(BACKUP_TOKEN, $token))
    || ($token !== '' && hash_equals(setting('backup_token', '___none___'), $token));

if (!$validToken) require_admin();

$pdo = db();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$fname = DB_NAME . '-backup-' . date('Ymd-His') . '.sql';
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $fname . '"');

echo "-- Kennet Valuation DB backup\n-- Database: " . DB_NAME . "\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
    $ddl = $create['Create Table'] ?? ($create['Create View'] ?? '');
    echo "DROP TABLE IF EXISTS `$t`;\n$ddl;\n\n";

    $rows = $pdo->query("SELECT * FROM `$t`");
    $count = 0; $buffer = '';
    while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
        $cols = '`' . implode('`,`', array_keys($r)) . '`';
        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($r));
        $buffer .= "INSERT INTO `$t` ($cols) VALUES (" . implode(',', $vals) . ");\n";
        if (++$count % 200 === 0) { echo $buffer; $buffer = ''; flush(); }
    }
    echo $buffer . "\n";
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
if (current_user()) audit('backup', 'database');
