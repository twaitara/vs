<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/forms.php';
require_can_edit();
require_module('machine');

// Columns persisted for a machine valuation.
$COLUMNS = [
    'serial_no','corporate_ref_no','customer_name','phone_no','assesment_date','inspection_location',
    'machine_name','machine_serial','manufacture_year','colour','market_value','forced_value','note_value','client','officer',
    'principal_valuer','extras','notes','status',
];

$id  = $_GET['id'] ?? ($_POST['id'] ?? null);
$row = load_row('machinevaluations', $id);

if ($id) require_own_valuation($row);
if ($id && !empty($row['signed_at']) && !is_admin()) {
    flash('This report is signed and can only be edited by an admin.', 'err');
    redirect('machine_list.php');
}
touch_activity(($id ? 'Editing' : 'New') . ' machine valuation' . (!empty($row['machine_name']) ? ' — ' . $row['machine_name'] : ''));
if (!$id && empty($row['serial_no'])) $row['serial_no'] = next_serial();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (['market_value', 'forced_value'] as $m) if (isset($_POST[$m])) $_POST[$m] = str_replace(',', '', $_POST[$m]);
    $required = ['machine_name' => 'Machine Name'];
    foreach ($required as $f => $lbl) if (trim((string)($_POST[$f] ?? '')) === '') $errors[$f] = 'Required';
    if (!$errors) {
        $folderKey = $_POST['serial_no'] ?: ($_POST['machine_name'] ?? 'machine');
        $files = handle_uploads($folderKey, $row);
        if (!$id && column_exists('machinevaluations', 'report_no')) $files['report_no'] = next_report_no('machinevaluations');
        if (is_admin() && !empty($_POST['valuer_id'])) $files['created_by'] = (int)$_POST['valuer_id'];
        $newId = save_row('machinevaluations', $COLUMNS, $_POST, $id, $files);
        request_touch_progress('machinevaluations', (int)$newId);
        delete_form_draft('machine' . ($id ? (int)$id : 'new'));
        if (($_POST['status'] ?? '') === 'complete') request_complete_for('machinevaluations', [(int)$newId]);
        audit($id ? 'update' : 'create', 'machinevaluation', $newId, $_POST['machine_name'] ?? '');
        flash($id ? 'Machine valuation updated.' : 'Machine valuation saved.');
        redirect('machine_list.php');
    }
    set_form_errors($errors);
    flash('Please fix the highlighted fields.', 'err');
    $row = array_merge($row, $_POST);
}

layout_header($id ? 'Edit Machine Valuation' : 'New Machine Valuation', 'machine');
?>
<form class="card wizard" method="post" enctype="multipart/form-data" data-draft="machine<?= $id ? (int)$id : 'new' ?>">
  <?= csrf_field() ?>
  <?php if ($id): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>

  <fieldset><legend>Machine & Customer Details</legend><div class="grid">
    <?= f_input('serial_no','Serial No. (auto)',$row,'text',false,'readonly') ?>
    <?= f_input('machine_name','Machine Name',$row,'text',true,'placeholder="e.g. CLAAS MARKANT 65 BALER"') ?>
    <?= f_input('machine_serial','Machine Serial No. (if any)',$row) ?>
    <?= f_input('manufacture_year','Year of Manufacture (if any)',$row) ?>
    <?= f_input('colour','Colour',$row) ?>
    <?= f_input('customer_name','Customer Name',$row) ?>
    <?= f_input('phone_no','Phone Number',$row) ?>
    <?= f_input('assesment_date','Inspection Date',$row,'date') ?>
    <?= f_input('inspection_location','Location',$row) ?>
    <?= f_select('client','Client','clients',$row,true) ?>
  </div></fieldset>

  <fieldset><legend>Valuation</legend><div class="grid">
    <?= f_money('market_value','Market Value',$row,false,true,'note_value') ?>
    <?= f_money('forced_value','Forced Value',$row,false,true) ?>
    <?= f_text('note_value','Value in Words (auto)',$row,'auto-filled from Market Value — you can edit') ?>
    <?= f_text('notes','N.B. / Notes',$row) ?>
    <?= f_text('extras','Extras',$row) ?>
  </div></fieldset>

  <fieldset><legend>Officer Details</legend><div class="grid">
    <?= f_input('officer','Officer',$row) ?>
    <?= f_input('principal_valuer','Inspected By',$row) ?>
    <div class="f"><label class="f">Status</label><select name="status">
      <?php foreach (valuation_statuses() as $k => $l): ?><option value="<?= $k ?>" <?= ($row['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
    <?php if (is_admin()): ?>
    <div class="f"><label class="f">Officer (assign)</label><select name="valuer_id">
      <option value="">-- keep current --</option>
      <?php foreach (user_list_for_valuer() as $vid => $vn): ?><option value="<?= $vid ?>" <?= (int)($row['created_by'] ?? 0) === $vid ? 'selected' : '' ?>><?= e($vn) ?></option><?php endforeach; ?>
    </select></div>
    <?php endif; ?>
  </div></fieldset>

  <fieldset><legend>Photos</legend>
  <div class="grid">
    <div class="f"><label class="f">Photos (from gallery)</label><input type="file" name="images[]" accept="image/*" multiple></div>
  </div>
  <div style="margin-top:12px">
    <label class="btn sec camera-btn" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
      <i data-lucide="camera"></i> Take Photo with Camera
      <input type="file" name="camera_photos[]" accept="image/*" capture="environment" multiple style="display:none">
    </label>
    <small class="muted" style="display:block;margin-top:6px">On a phone this opens the camera so you can snap the machine directly.</small>
  </div>
  <?php $existingImgs = !empty($row['images']) ? (json_decode($row['images'], true) ?: []) : []; ?>
  <?php if ($existingImgs): ?>
    <div style="margin-top:14px">
      <label class="f">Existing photos — click ✕ to remove on save</label>
      <div class="thumbs">
        <?php foreach ($existingImgs as $p): ?>
          <div class="thumb">
            <img src="<?= e(UPLOAD_URL . '/' . ltrim($p, '/')) ?>" alt="">
            <input type="checkbox" name="removed_images[]" value="<?= e($p) ?>" style="display:none">
            <button type="button" class="rm">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  </fieldset>

  <button class="btn" type="submit"><i data-lucide="save"></i>Save</button>
  <a class="btn sec" href="<?= url('machine_list.php') ?>"><i data-lucide="x"></i>Cancel</a>
</form>
<?php form_assets(); ?>
<?php layout_footer();
