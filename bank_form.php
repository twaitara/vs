<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/forms.php';
require_can_edit();

// Columns persisted for a bank valuation (spelling matches the existing DB schema).
$COLUMNS = [
    'reg_no','valuation_type','make','body_type','customer_name','phone_no','corporate_ref_no',
    'client','serial_no','inspection_location','registration_date','manufacture_year','country_of_origin',
    'mileage','chasis_no','engine_no','engine_capacity','fuel_type','colour','insurer','insurance_pol_no',
    'insurance_exp','coach_condition','mechanical_condition','electrical','general_condition','headlight_type',
    'market_value','forced_value','tyres','principal_valuer','bank_officer','officer_phone','assesment_date',
    'extras','note_value','notes','anti_theft','remarks','pending','remedy','ammends',
];

$id  = $_GET['id'] ?? ($_POST['id'] ?? null);
$row = load_row('bankvaluations', $id);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (['market_value', 'forced_value'] as $m) if (isset($_POST[$m])) $_POST[$m] = str_replace(',', '', $_POST[$m]);
    $required = ['reg_no' => 'Reg Number', 'make' => 'Make/Model', 'customer_name' => 'Customer Name', 'market_value' => 'Market Value'];
    foreach ($required as $f => $lbl) if (trim((string)($_POST[$f] ?? '')) === '') $errors[$f] = 'Required';
    if (!$errors) {
        $files = handle_uploads($_POST['reg_no'] ?? '', $row);
        $newId = save_row('bankvaluations', $COLUMNS, $_POST, $id, $files);
        audit($id ? 'update' : 'create', 'bankvaluation', $newId, $_POST['reg_no'] ?? '');
        flash($id ? 'Bank valuation updated.' : 'Bank valuation saved.');
        redirect('bank_list.php');
    }
    set_form_errors($errors);
    flash('Please fix the highlighted fields.', 'err');
    $row = array_merge($row, $_POST); // repopulate entered values
}

layout_header($id ? 'Edit Bank Valuation' : 'New Bank Valuation', 'bank');
?>
<form class="card wizard" method="post" enctype="multipart/form-data"<?= $id ? '' : ' data-draft="banknew"' ?>>
  <?= csrf_field() ?>
  <?php if ($id): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>

  <fieldset><legend>Vehicle & Ownership Details</legend><div class="grid">
    <?= f_input('reg_no','Reg Number',$row,'text',true) ?>
    <?= f_input('country_of_origin','Country of Origin',$row) ?>
    <?= f_input('serial_no','Serial No.',$row) ?>
    <?= f_input('make','Make/Model',$row,'text',true) ?>
    <?= f_input('chasis_no','Chasis No.',$row) ?>
    <?= f_input('body_type','Body Type',$row) ?>
    <?= f_input('customer_name','Customer Name',$row,'text',true) ?>
    <?= f_input('phone_no','Phone Number',$row) ?>
    <?= f_input('inspection_location','Inspection Location',$row) ?>
    <?= f_select('valuation_type','Valuation Type','types',$row,true) ?>
    <?= f_select('client','Client','clients',$row,true) ?>
    <?= f_input('corporate_ref_no','Corporate Ref No',$row) ?>
  </div></fieldset>

  <fieldset><legend>Registration & Insurance</legend><div class="grid">
    <?= f_input('registration_date','Reg Date',$row,'date') ?>
    <?= f_input('manufacture_year','YOM',$row) ?>
    <?= f_select('insurer','Insurer','insurers',$row,true) ?>
    <?= f_input('insurance_pol_no','Insurance Pol No.',$row) ?>
    <?= f_input('insurance_exp','Insurance Exp Date',$row,'date') ?>
  </div></fieldset>

  <fieldset><legend>Engine & Mileage</legend><div class="grid">
    <?= f_input('mileage','Odometer Reading (Km/Miles)',$row) ?>
    <?= f_input('colour','Colour',$row) ?>
    <?= f_input('engine_no','Engine No.',$row) ?>
    <?= f_input('engine_capacity','Engine Capacity (cc)',$row) ?>
    <?= f_select('fuel_type','Fuel','fuels',$row) ?>
  </div></fieldset>

  <fieldset><legend>Condition</legend><div class="grid">
    <?= f_input('coach_condition','Coach Work Condition',$row) ?>
    <?= f_input('electrical','Electrical Condition',$row) ?>
    <?= f_input('headlight_type','Type of Headlights',$row) ?>
    <?= f_input('mechanical_condition','Mechanical Condition',$row) ?>
    <?= f_input('general_condition','General Condition',$row) ?>
    <?= f_text('tyres','Tyres',$row) ?>
  </div></fieldset>

  <fieldset><legend>Vehicle Valuation</legend><div class="grid">
    <?= f_money('market_value','Market Value',$row,true,true) ?>
    <?= f_money('forced_value','Forced Value',$row,false) ?>
    <?= f_text('note_value','Note Value',$row,'W/S value estimated at 00000/= R/CD/TV value estimated at 00000/= REMARKS:') ?>
    <?= f_text('notes','N.B.',$row) ?>
    <?= f_text('extras','Extras',$row) ?>
    <?= f_text('anti_theft','Anti-Theft',$row) ?>
  </div></fieldset>

  <fieldset><legend>Officer Details</legend><div class="grid">
    <?= f_input('principal_valuer','Principal Valuer',$row) ?>
    <?= f_input('assesment_date','Assessment Date',$row,'date') ?>
    <?= f_input('bank_officer','Bank Officer Name',$row) ?>
    <?= f_input('officer_phone','Officer Phone No.',$row) ?>
  </div></fieldset>

  <fieldset><legend>Extra Information</legend><div class="grid">
    <?= f_text('remarks','Remarks',$row) ?>
    <?= f_text('remedy','Remedy',$row) ?>
    <?= f_text('pending','Pending',$row) ?>
    <?= f_text('ammends','Ammendments',$row) ?>
  </div>
  <div class="grid" style="margin-top:14px">
    <div class="f"><label class="f">Logbook (image)</label><input type="file" name="logbook" accept="image/*"></div>
    <div class="f"><label class="f">Photos (multiple)</label><input type="file" name="images[]" accept="image/*" multiple></div>
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

  <button class="btn" type="submit">Save</button>
  <a class="btn sec" href="<?= url('bank_list.php') ?>">Cancel</a>
  <?php if ($id): ?><a class="btn sec" href="<?= url('duplicate.php?type=bank&id=' . (int)$id) ?>" onclick="return confirm('Create a duplicate of this valuation?')">Duplicate</a><?php endif; ?>
</form>
<?php form_assets(); ?>
<?php layout_footer();
