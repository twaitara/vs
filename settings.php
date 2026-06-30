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
    'per_page'        => ['Rows Per Page', 'number'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($FIELDS as $key => $_) {
        if (array_key_exists($key, $_POST)) set_setting($key, trim((string)$_POST[$key]));
    }
    if (array_key_exists('backup_token', $_POST)) set_setting('backup_token', trim((string)$_POST['backup_token']));
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
  </fieldset>

  <button class="btn" type="submit">Save settings</button>
</form>

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
