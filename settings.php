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
