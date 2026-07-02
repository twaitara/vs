<?php
require_once __DIR__ . '/layout.php';
require_admin();

// Editable settings: key => [label, type]
$FIELDS = [
    'company_name'    => ['Company Name', 'text'],
    'company_address' => ['Address', 'text'],
    'company_pobox'   => ['P.O. Box', 'text'],
    'company_tel'     => ['Telephone(s)', 'text'],
    'company_email'   => ['Email', 'text'],
    'report_footer'   => ['Report Footer Text', 'text'],
    'currency'        => ['Currency Code', 'text'],
    'default_valuer'  => ['Default Valuer Name', 'text'],
    'signatory_name'  => ['Authorizing Signatory Name (e.g. George Mwangi)', 'text'],
    'serial_prefix'   => ['Serial Number Prefix (optional, e.g. KEN → KEN/079/06/2026)', 'text'],
    'per_page'        => ['Rows Per Page', 'number'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($FIELDS as $key => $_) {
        if (array_key_exists($key, $_POST)) set_setting($key, trim((string)$_POST[$key]));
    }
    if (array_key_exists('backup_token', $_POST)) set_setting('backup_token', trim((string)$_POST['backup_token']));
    // System availability banner — super admin only.
    if (is_superadmin() && isset($_POST['_banner_form'])) {
        set_setting('banner_enabled', !empty($_POST['banner_enabled']) ? '1' : '0');
        set_setting('banner_until', trim((string)($_POST['banner_until'] ?? '')));
        set_setting('banner_message', trim((string)($_POST['banner_message'] ?? '')));
        set_setting('banner_denied_message', trim((string)($_POST['banner_denied_message'] ?? '')));
    }
    if (is_superadmin() && isset($_POST['_signall_form'])) {
        set_setting('sign_all_enabled', !empty($_POST['sign_all_enabled']) ? '1' : '0');
    }
    // Customer SMTP (super admin only) — send email from the customer's own domain.
    if (is_superadmin() && isset($_POST['_smtp_form'])) {
        set_setting('smtp_host', trim((string)($_POST['smtp_host'] ?? '')));
        set_setting('smtp_port', trim((string)($_POST['smtp_port'] ?? '587')));
        set_setting('smtp_secure', in_array($_POST['smtp_secure'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_secure'] : 'tls');
        set_setting('smtp_user', trim((string)($_POST['smtp_user'] ?? '')));
        if (trim((string)($_POST['smtp_pass'] ?? '')) !== '') set_setting('smtp_pass', (string)$_POST['smtp_pass']); // blank = keep existing
        set_setting('mail_from', trim((string)($_POST['mail_from'] ?? '')));
        set_setting('mail_from_name', trim((string)($_POST['mail_from_name'] ?? '')));
        set_setting('email_hourly_cap', (string)max(0, (int)($_POST['email_hourly_cap'] ?? 30)));
    }
    // Optional logo uploads (header logo + watermark).
    @mkdir(__DIR__ . '/assets', 0775, true);
    foreach (['logo2' => 'logo2.png', 'logo' => 'logo.png'] as $field => $dest) {
        if (!empty($_FILES[$field]['name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            @move_uploaded_file($_FILES[$field]['tmp_name'], __DIR__ . '/assets/' . $dest);
        }
    }
    // Authorizing signature — stored in storage/ (not web-accessible), embedded only on signed reports.
    if (!empty($_FILES['signature']['name']) && is_uploaded_file($_FILES['signature']['tmp_name'])) {
        @mkdir(__DIR__ . '/storage', 0775, true);
        if (@move_uploaded_file($_FILES['signature']['tmp_name'], __DIR__ . '/storage/signature.png')) {
            set_setting('signature_image', 'storage/signature.png');
        }
    }
    if (!empty($_FILES['stamp']['name']) && is_uploaded_file($_FILES['stamp']['tmp_name'])) {
        @mkdir(__DIR__ . '/storage', 0775, true);
        if (@move_uploaded_file($_FILES['stamp']['tmp_name'], __DIR__ . '/storage/stamp.png')) {
            set_setting('stamp_image', 'storage/stamp.png');
        }
    }
    audit('update', 'settings');
    flash('Settings saved.');
    redirect('settings.php');
}

// Super-admin: test the SMTP connection & login (no email sent).
if (is_superadmin() && isset($_GET['smtpconn'])) {
    [$ok, $msg] = smtp_test(mail_settings());
    flash(($ok ? '✓ ' : '✗ ') . $msg, $ok ? 'ok' : 'err');
    redirect('settings.php');
}
// Super-admin: send a test email to a chosen address to verify SMTP.
if (is_superadmin() && isset($_GET['smtptest'])) {
    $to = trim((string)($_GET['to'] ?? '')) ?: (current_user()['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid email address to send the test to.', 'err');
    } else {
        // Deliver immediately (bypass the hourly queue) so the test result is instant.
        $ok = mail_deliver($to, 'Kennet test email', "This is a test message from the Kennet valuation system.\n\n"
            . 'Sent via: ' . (smtp_configured() ? ('SMTP ' . setting('smtp_host')) : 'PHP mail()') . "\nTime: " . date('r'));
        flash($ok ? ('Test email sent to ' . $to . '. Check the inbox (and spam).') : 'Test failed — check the SMTP host, port, security and credentials.', $ok ? 'ok' : 'err');
    }
    redirect('settings.php');
}

layout_header('Settings', 'settings');
settings_nav('general');
?>
<form class="card" method="post" enctype="multipart/form-data" style="max-width:680px">
  <?= csrf_field() ?>
  <fieldset><legend>Company & Report Details</legend><div class="grid">
    <?php foreach ($FIELDS as $key => [$label, $type]): ?>
      <div class="f">
        <label class="f"><?= e($label) ?></label>
        <input type="<?= $type ?>" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
      </div>
    <?php endforeach; ?>
  </div></fieldset>

  <fieldset><legend>Branding (optional)</legend><div class="grid">
    <div class="f"><label class="f">Header Logo (PNG) — replaces report header logo</label><input type="file" name="logo2" accept="image/png"></div>
    <div class="f"><label class="f">Watermark Logo (PNG) — faint background on report</label><input type="file" name="logo" accept="image/png"></div>
  </div>
  <p class="muted" style="font-size:12px">Current header logo:</p>
  <img src="<?= url('assets/logo2.png') ?>?v=<?= time() ?>" style="max-height:60px;background:#fff;padding:6px;border-radius:6px">
  </fieldset>

  <fieldset><legend>Authorizing Signature (admins only)</legend>
    <p class="muted" style="font-size:13px">Upload the authorizing signature (PNG, transparent background works best). It is stored privately and appears on a report <b>only after it is signed</b>. Used when admins click “Sign report”.</p>
    <div class="f"><label class="f">Signature image (PNG)</label><input type="file" name="signature" accept="image/png"></div>
    <?php $sigp = __DIR__ . '/storage/signature.png'; if (is_file($sigp)): ?>
      <p class="muted" style="font-size:12px;margin-top:10px">Current signature on file:</p>
      <img src="data:image/png;base64,<?= base64_encode(file_get_contents($sigp)) ?>" style="max-height:70px;background:#fff;padding:6px;border-radius:6px">
    <?php else: ?>
      <p class="muted" style="font-size:12px;margin-top:10px">No signature uploaded yet.</p>
    <?php endif; ?>
    <div class="f" style="margin-top:14px"><label class="f">Company Stamp (PNG, transparent works best)</label><input type="file" name="stamp" accept="image/png"></div>
    <?php $stp = __DIR__ . '/storage/stamp.png'; if (is_file($stp)): ?>
      <p class="muted" style="font-size:12px;margin-top:10px">Current stamp on file:</p>
      <img src="data:image/png;base64,<?= base64_encode(file_get_contents($stp)) ?>" style="max-height:80px;background:#fff;padding:6px;border-radius:6px">
    <?php else: ?>
      <p class="muted" style="font-size:12px;margin-top:10px">No stamp uploaded yet.</p>
    <?php endif; ?>
  </fieldset>

  <button class="btn" type="submit">Save settings</button>
</form>

<?php if (is_superadmin()): ?>
<form class="card" method="post" style="max-width:680px;border-color:#7a5c1c">
  <?= csrf_field() ?><input type="hidden" name="_banner_form" value="1">
  <h3 style="margin-top:0">System Availability Notice <span class="muted" style="font-size:12px;font-weight:400">(super admin only)</span></h3>
  <p class="muted" style="font-size:13px">Turn on a banner shown to everyone. After the date &amp; time below passes, only the super admin can log in — everyone else sees the “access denied” message.</p>
  <label style="display:flex;gap:8px;align-items:center;font-size:14px;margin:6px 0 12px"><input type="checkbox" name="banner_enabled" value="1" <?= setting('banner_enabled') === '1' ? 'checked' : '' ?>> Show availability banner / enable lockout</label>
  <div class="f" style="max-width:280px"><label class="f">Available until (date &amp; time)</label><input type="datetime-local" name="banner_until" value="<?= e(str_replace(' ', 'T', setting('banner_until'))) ?>"></div>
  <div class="f"><label class="f">Banner message <span class="muted">(use <code>{until}</code> for the date/time)</span></label>
    <textarea name="banner_message" placeholder="This system will be available until {until}."><?= e(setting('banner_message')) ?></textarea></div>
  <div class="f"><label class="f">Access-denied message (shown when locked users try to log in)</label>
    <textarea name="banner_denied_message" placeholder="This system is no longer available for use. Please contact the administrator."><?= e(setting('banner_denied_message')) ?></textarea></div>
  <button class="btn" type="submit" style="margin-top:10px"><i data-lucide="save"></i>Save notice</button>
</form>

<form class="card" method="post" style="max-width:680px;border-color:#7a5c1c">
  <?= csrf_field() ?><input type="hidden" name="_signall_form" value="1">
  <h3 style="margin-top:0">Bulk "Sign all matching" <span class="muted" style="font-size:12px;font-weight:400">(super admin only)</span></h3>
  <p class="muted" style="font-size:13px">Controls the green <b>Sign all matching</b> button on the valuation lists, which signs every unsigned report in the current filter at once. Turn it off when you're done with the one-time bulk signing.</p>
  <label style="display:flex;gap:8px;align-items:center;font-size:14px;margin:6px 0"><input type="checkbox" name="sign_all_enabled" value="1" <?= sign_all_enabled() ? 'checked' : '' ?>> Enable the “Sign all matching” button</label>
  <button class="btn" type="submit" style="margin-top:10px"><i data-lucide="save"></i>Save</button>
</form>

<form class="card" method="post" style="max-width:680px;border-color:#7a5c1c">
  <?= csrf_field() ?><input type="hidden" name="_smtp_form" value="1">
  <h3 style="margin-top:0">Outgoing Email / SMTP <span class="muted" style="font-size:12px;font-weight:400">(super admin only)</span></h3>
  <p class="muted" style="font-size:13px">Send all system emails through the customer's own mail server, so messages come from their domain (better delivery, no spam). Leave the host blank to use the server's built-in PHP mail instead.</p>
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
    <?php $qp = 0; try { $qp = (int)db()->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn(); } catch (Throwable $e) {} ?>
    <?php if ($qp > 0): ?><br><b style="color:#c98a1a"><?= $qp ?> email(s) currently queued.</b><?php endif; ?></div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;margin-top:6px">
    <button class="btn" type="submit"><i data-lucide="save"></i>Save SMTP</button>
    <a class="btn sec" href="<?= url('settings.php?smtpconn=1') ?>"><i data-lucide="plug"></i>Test connection</a>
    <span class="muted" style="font-size:12px"><?= smtp_configured() ? 'Currently: sending via ' . e(setting('smtp_host')) : 'Currently: using server PHP mail()' ?></span>
  </div>
  <p class="muted" style="font-size:11px;margin:6px 0 0">Test connection checks the saved settings (Save first). It verifies the server, encryption and login — without sending an email.</p>
</form>

<form class="card" method="get" action="<?= url('settings.php') ?>" style="max-width:680px;border-color:#7a5c1c">
  <input type="hidden" name="smtptest" value="1">
  <h3 style="margin-top:0">Send a test email <span class="muted" style="font-size:12px;font-weight:400">(super admin only)</span></h3>
  <p class="muted" style="font-size:13px">Sends a test using the settings above (Save first). It's delivered immediately, bypassing the hourly limit.</p>
  <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <div class="f" style="flex:1;min-width:240px;margin:0"><label class="f">Send test to</label>
      <input type="email" name="to" required placeholder="you@example.com" value="<?= e(current_user()['email'] ?? '') ?>"></div>
    <button class="btn" type="submit"><i data-lucide="send"></i>Send test</button>
  </div>
</form>
<?php endif; ?>

<div class="card" style="max-width:680px">
  <h3 style="margin-top:0">Database Backup</h3>
  <div style="display:flex;gap:10px;background:#3d2f0f;border:1px solid #7a5c1c;color:#f5d79a;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:12px">
    <span style="font-size:18px;line-height:1">⚠️</span>
    <div><b>You are responsible for backing up your own data.</b><br>
    Download backups regularly and store them safely <b>off-site</b> (cloud storage or another computer). No copies are kept on your behalf — if data is lost and you have no backup, it cannot be recovered.</div>
  </div>
  <p class="muted" style="font-size:13px">Download a full SQL dump of the database below.</p>
  <a class="btn" href="<?= url('backup.php') ?>">⬇ Download backup (.sql)</a>
  <p class="muted" style="font-size:12px;margin-top:12px">
    For automated daily backups, set a <code>backup_token</code> below and add a cron job (cPanel → Cron Jobs):<br>
    <code>wget -q -O ~/backups/vs-$(date +\%F).sql "https://nineonetwo.online/vs/backup.php?token=YOUR_TOKEN"</code>
  </p>
  <form method="post" style="margin-top:8px;max-width:360px">
    <?= csrf_field() ?>
    <div class="f"><label class="f">backup_token (for cron)</label><input type="text" name="backup_token" value="<?= e(setting('backup_token')) ?>"></div>
    <button class="btn sec" type="submit">Save token</button>
  </form>
</div>
<?php layout_footer();
