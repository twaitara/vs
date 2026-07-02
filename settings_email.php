<?php
require_once __DIR__ . '/layout.php';
require_admin();
if (!is_superadmin()) { flash('Email settings are super-admin only.', 'err'); redirect('settings.php'); }

// Save SMTP + rate limit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_smtp_form'])) {
    csrf_verify();
    set_setting('smtp_host', trim((string)($_POST['smtp_host'] ?? '')));
    set_setting('smtp_port', trim((string)($_POST['smtp_port'] ?? '587')));
    set_setting('smtp_secure', in_array($_POST['smtp_secure'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_secure'] : 'tls');
    set_setting('smtp_user', trim((string)($_POST['smtp_user'] ?? '')));
    if (trim((string)($_POST['smtp_pass'] ?? '')) !== '') set_setting('smtp_pass', (string)$_POST['smtp_pass']); // blank = keep existing
    set_setting('mail_from', trim((string)($_POST['mail_from'] ?? '')));
    set_setting('mail_from_name', trim((string)($_POST['mail_from_name'] ?? '')));
    set_setting('email_hourly_cap', (string)max(0, (int)($_POST['email_hourly_cap'] ?? 30)));
    audit('update', 'settings', null, 'smtp');
    flash('Email settings saved.');
    redirect('settings_email.php');
}

// Test the SMTP connection & login (no email sent).
if (isset($_GET['smtpconn'])) {
    [$ok, $msg] = smtp_test(mail_settings());
    set_setting('smtp_last_test', ($ok ? 'OK' : 'FAIL') . '|' . date('Y-m-d H:i') . '|Connection: ' . $msg);
    flash(($ok ? '✓ ' : '✗ ') . $msg, $ok ? 'ok' : 'err');
    redirect('settings_email.php');
}

// Send a test email to a chosen address.
if (isset($_GET['smtptest'])) {
    $to = trim((string)($_GET['to'] ?? '')) ?: (current_user()['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid email address to send the test to.', 'err');
    } else {
        $ok = mail_deliver($to, 'Kennet test email', "This is a test message from the Kennet valuation system.\n\n"
            . 'Sent via: ' . (smtp_configured() ? ('SMTP ' . setting('smtp_host')) : 'PHP mail()') . "\nTime: " . date('r'));
        $msg = $ok ? ('Test email sent to ' . $to . '. Check the inbox (and spam).') : 'Test failed — check the SMTP host, port, security and credentials.';
        set_setting('smtp_last_test', ($ok ? 'OK' : 'FAIL') . '|' . date('Y-m-d H:i') . '|Test email to ' . $to . ': ' . ($ok ? 'accepted for delivery' : 'rejected'));
        flash(($ok ? '✓ ' : '✗ ') . $msg, $ok ? 'ok' : 'err');
    }
    redirect('settings_email.php');
}

layout_header('Email Settings', 'settings');
settings_nav('email');
?>
<form class="card" method="post" style="max-width:680px;border-color:#7a5c1c">
  <?= csrf_field() ?><input type="hidden" name="_smtp_form" value="1">
  <h3 style="margin-top:0">Outgoing Email / SMTP</h3>
  <p class="muted" style="font-size:13px">Send all system emails through the customer's own mail server, so messages come from their domain (better delivery, no spam). Leave the host blank to use the server's built-in PHP mail instead.</p>
  <?php $lt = setting('smtp_last_test'); if ($lt): [$st, $when, $detail] = array_pad(explode('|', $lt, 3), 3, ''); $good = $st === 'OK'; ?>
  <div style="display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:8px;margin:2px 0 14px;font-size:13px;background:<?= $good ? '#0f3d24' : '#3d0f0f' ?>;border:1px solid <?= $good ? '#1c7a47' : '#7a1c1c' ?>;color:<?= $good ? '#b8f5d0' : '#f5c0c0' ?>">
    <span style="font-size:16px;line-height:1"><?= $good ? '✓' : '✗' ?></span>
    <div><b>Last test: <?= $good ? 'Successful' : 'Failed' ?></b> <span style="opacity:.8">· <?= e($when) ?></span><br><?= e($detail) ?></div>
  </div>
  <?php endif; ?>
  <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px">
    <div class="f"><label class="f">SMTP Host</label><input type="text" name="smtp_host" placeholder="mail.customer.com" value="<?= e(setting('smtp_host')) ?>"></div>
    <div class="f"><label class="f">Port</label><input type="number" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>"></div>
    <div class="f"><label class="f">Security</label><select name="smtp_secure">
      <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'None (25)'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= setting('smtp_secure', 'tls') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select></div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <div class="f"><label class="f">Username</label><input type="text" name="smtp_user" autocomplete="off" placeholder="no-reply@customer.com" value="<?= e(setting('smtp_user')) ?>"></div>
    <div class="f"><label class="f">Password <span class="muted">(blank = keep current)</span></label><input type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= setting('smtp_pass') !== '' ? '••••••••' : '' ?>"></div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <div class="f"><label class="f">From address</label><input type="email" name="mail_from" placeholder="no-reply@customer.com" value="<?= e(setting('mail_from')) ?>"></div>
    <div class="f"><label class="f">From name</label><input type="text" name="mail_from_name" placeholder="Company Valuations" value="<?= e(setting('mail_from_name')) ?>"></div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;align-items:end">
    <div class="f"><label class="f">Max emails per hour</label><input type="number" name="email_hourly_cap" min="0" value="<?= e(setting('email_hourly_cap', '30')) ?>"></div>
    <div class="muted" style="font-size:12px;padding-bottom:12px">Beyond this, emails are queued and sent automatically in the following hour(s). Set 0 for no limit.
    <?php $qp = 0; try { $qp = (int)db()->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn(); } catch (Throwable $e) {}
          $sentHr = emails_sent_last_hour(); $capN = email_hourly_cap(); ?>
    <br><b style="color:#5b9bff">Sent in the last hour: <?= $sentHr ?><?= $capN ? ' / ' . $capN : '' ?></b>
    <?php if ($qp > 0): ?> &nbsp; <b style="color:#c98a1a"><?= $qp ?> queued</b><?php endif; ?></div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;margin-top:6px">
    <button class="btn" type="submit"><i data-lucide="save"></i>Save SMTP</button>
    <a class="btn sec" href="<?= url('settings_email.php?smtpconn=1') ?>"><i data-lucide="plug"></i>Test connection</a>
    <span class="muted" style="font-size:12px"><?= smtp_configured() ? 'Currently: sending via ' . e(setting('smtp_host')) : 'Currently: using server PHP mail()' ?></span>
  </div>
  <p class="muted" style="font-size:11px;margin:6px 0 0">Test connection checks the saved settings (Save first). It verifies the server, encryption and login — without sending an email.</p>
</form>

<form class="card" method="get" action="<?= url('settings_email.php') ?>" style="max-width:680px;border-color:#7a5c1c">
  <input type="hidden" name="smtptest" value="1">
  <h3 style="margin-top:0">Send a test email</h3>
  <p class="muted" style="font-size:13px">Sends a test using the settings above (Save first). It's delivered immediately, bypassing the hourly limit.</p>
  <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <div class="f" style="flex:1;min-width:240px;margin:0"><label class="f">Send test to</label>
      <input type="email" name="to" required placeholder="you@example.com" value="<?= e(current_user()['email'] ?? '') ?>"></div>
    <button class="btn" type="submit"><i data-lucide="send"></i>Send test</button>
  </div>
</form>
<?php layout_footer();
