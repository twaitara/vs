<?php
/**
 * Flush the outgoing email queue (respecting the hourly cap).
 * Run hourly via cron with the backup token, or open as a logged-in admin:
 *   https://site/vs/email_cron.php?token=YOUR_TOKEN
 * cPanel cron (every hour):
 *   wget -q -O /dev/null "https://nineonetwo.online/vs/email_cron.php?token=YOUR_TOKEN"
 */
require_once __DIR__ . '/lib.php';

$token = $_GET['token'] ?? '';
$valid = ($token !== '' && hash_equals(setting('backup_token', '___none___'), $token));
if (!$valid) require_admin();

$sent = dispatch_email_queue();
header('Content-Type: text/plain; charset=utf-8');
echo "Dispatched: $sent\n";
try {
    $pending = (int)db()->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn();
    $lasthr  = emails_sent_last_hour();
    echo "Pending: $pending\nSent in last hour: $lasthr / " . email_hourly_cap() . "\n";
} catch (Throwable $e) {}
