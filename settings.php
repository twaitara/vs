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
    // Optional logo uploads (header logo + watermark).
    @mkdir(__DIR__ . '/assets', 0775, true);
    foreach (['logo2' => 'logo2.png', 'logo' => 'logo.png'] as $field => $dest) {
        if (!empty($_FILES[$field]['name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            @move_uploaded_file($_FILES[$field]['tmp_name'], __DIR__ . '/assets/' . $dest);
        }
    }
    audit('update', 'settings');
    flash('Settings saved.');
    redirect('settings.php');
}

layout_header('Settings', 'settings');
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

  <button class="btn" type="submit">Save settings</button>
</form>
<?php layout_footer();
