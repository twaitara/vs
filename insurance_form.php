<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/forms.php';
require_can_edit();

// All persisted columns for the insurance valuations table.
$COLUMNS = [
    // identity / vehicle
    'valuation_type','client','corporate_ref_no','make','customer_name','phone_no','reg_no',
    'inspection_location','registration_date','manufacture_year','country_of_origin','mileage','colour',
    'chasis_no','engine_no','engine_capacity','fuel_type','insurer','insurance_pol_no','insurance_exp',
    // body
    'scratches_cracks','paint_work','accident_repairs','bumper_damage','door_rubbers_damage',
    'upholstery_damage','chasis_damage','body_comment',
    // steering/braking
    'rubber_boot_damage','tie_rods_damage','rack_damage','steering_alignment_ok','tilt_telescopic_ok',
    'power_steering_ok','vibrations','brakes_ok','fluid_leaks','steering_comment',
    // electrical
    'charging_state','battery_state','wiring_damage','headlights','brake_lights','dash_cluster',
    'headlight_type','elec_comment',
    // transmission
    'gb_mounts_damage','gb_cv_damage','gb_auto_gswith','gb_tc_spin','gb_gear_damage','gb_cluth_slip',
    'gb_propeller_damage','diff_damage','trans_comment',
    // suspension
    'ball_joint_damage','shocks_damage','center_bolt_damage','coil_springs_damage','leaf_springs_damage',
    'anchor_damage','sus_comment',
    // engine/cooling/AC
    'idling','eng_mounts_damage','oil_leaks','water_pump_ok','radiator_damage','air_con_damage','engine_comment',
    // other
    'air_bags_no','inspection_officer','inspection_date','extras','note_value','notes','anti_theft',
    'remarks','pending','remedy','ammends','assessed_value','status',
];

$id  = $_GET['id'] ?? ($_POST['id'] ?? null);
$row = load_row('valuations', $id);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['assessed_value'])) $_POST['assessed_value'] = str_replace(',', '', $_POST['assessed_value']);
    $required = ['reg_no' => 'Reg Number', 'make' => 'Make/Model', 'customer_name' => 'Customer Name', 'assessed_value' => 'Assessed Value'];
    foreach ($required as $f => $lbl) if (trim((string)($_POST[$f] ?? '')) === '') $errors[$f] = 'Required';
    if (!$errors) {
        $extra = (!$id && column_exists('valuations', 'report_no')) ? ['report_no' => next_report_no('valuations')] : [];
        $newId = save_row('valuations', $COLUMNS, $_POST, $id, $extra);
        audit($id ? 'update' : 'create', 'valuation', $newId, $_POST['reg_no'] ?? '');
        flash($id ? 'Insurance valuation updated.' : 'Valuation added successfully.');
        redirect('insurance_list.php');
    }
    set_form_errors($errors);
    flash('Please fix the highlighted fields.', 'err');
    $row = array_merge($row, $_POST);
}

// Checklist groups: [field => question label]
$body = [
    'scratches_cracks'=>'Is Paint Scratched or Cracked?','paint_work'=>'Is Paint faded or repainted?',
    'accident_repairs'=>'Any Signs of accident repairs?','bumper_damage'=>'Are Wings & Bumpers Dented/Repaired?',
    'door_rubbers_damage'=>'Are Door Rubbers Torn?','upholstery_damage'=>'Is Upholstery Damaged (torn/faded/worn)?',
    'chasis_damage'=>'Chasis/subframe Damage (Kinks & bends)?',
];
$steer = [
    'rubber_boot_damage'=>'Are Steering Boots Torn?','tie_rods_damage'=>'Tie-Rod Ends & Steering Drug Links Worn?',
    'rack_damage'=>'Does The Steering Box/Rack Knock?','steering_alignment_ok'=>'Does Steering Self-align Properly?',
    'tilt_telescopic_ok'=>'Does Tilt Telescopic Operate Well?','power_steering_ok'=>'Does Electric Power Steering Operate Well?',
    'vibrations'=>'Any Vibrations In Steering & Braking System?','brakes_ok'=>'Are Parking, ABS & Foot brakes effective?',
    'fluid_leaks'=>'Any Signs of Fluid Leaks?',
];
$elec = [
    'charging_state'=>'Does stator, charging operate well?','battery_state'=>'Is battery well sized and secured?',
    'wiring_damage'=>'Do electrical wires hang dangerously?','headlights'=>'Does head lights operate well?',
    'brake_lights'=>'Are brake lights & turn signal operational?','dash_cluster'=>'Are instrument panel lights ok?',
];
$trans = [
    'gb_mounts_damage'=>'Are Gearbox Mounts Worn out?','gb_cv_damage'=>'Are CV Joints Worn Out/Noisy?',
    'gb_auto_gswith'=>'Is The Automatic Gearbox Changing Smoothly?','gb_tc_spin'=>'Any Signs of Torque Converter Spin?',
    'gb_gear_damage'=>'Does Any Gear Jump out of Mesh?','gb_cluth_slip'=>'Is There Clutch Slip?',
    'gb_propeller_damage'=>'Signs of Propeller Shaft Damage (bends & wobbles)?','diff_damage'=>'Noisy Differential?',
];
$susp = [
    'ball_joint_damage'=>'Are Bushes & Ball Joints Worn?','shocks_damage'=>'Are Shock Absorbers Worn?',
    'center_bolt_damage'=>'Is the Center Bolt/Center Rubber Worn out?','coil_springs_damage'=>'Are Coil Springs Broken?',
    'leaf_springs_damage'=>'Are leaf springs weak, broken or misaligned?','anchor_damage'=>'Are spring anchor points/shackles Damaged?',
];
$eng = [
    'idling'=>'Does The Engine Start & Idle Properly?','eng_mounts_damage'=>'Are Mountings or Drive Belts Worn?',
    'oil_leaks'=>'Any Signs of Oil/Coolant Leaks?','water_pump_ok'=>'Does the Water Pump Operate Well?',
    'radiator_damage'=>'Are there signs of Radiator Damage?','air_con_damage'=>'Does the AC Operate Well?',
];

layout_header($id ? 'Edit Insurance Valuation' : 'New Insurance Valuation', 'insurance');
?>
<form class="card wizard" method="post"<?= $id ? '' : ' data-draft="insnew"' ?>>
  <?= csrf_field() ?>
  <?php if ($id): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>

  <fieldset><legend>Vehicle & Ownership Details</legend><div class="grid">
    <?= f_input('reg_no','Reg Number',$row,'text',true) ?>
    <?= f_input('country_of_origin','Country of Origin',$row) ?>
    <?= f_input('make','Make/Model',$row,'text',true) ?>
    <?= f_input('chasis_no','Chasis No.',$row) ?>
    <?= f_input('air_bags_no','Number of Airbags',$row,'number') ?>
    <?= f_input('customer_name','Customer Name',$row,'text',true) ?>
    <?= f_input('phone_no','Phone Number',$row) ?>
    <?= f_input('inspection_location','Inspection Location',$row) ?>
    <?= f_select('client','Client','clients',$row,true) ?>
    <?= f_input('corporate_ref_no','Corporate Ref No',$row) ?>
    <?= f_select('valuation_type','Valuation Type','types',$row,true) ?>
  </div></fieldset>

  <fieldset><legend>Registration & Insurance</legend><div class="grid">
    <?= f_input('registration_date','Reg Date',$row,'date') ?>
    <?= f_input('manufacture_year','YOM',$row) ?>
    <?= f_select('insurer','Insurer','insurers',$row,true) ?>
    <?= f_input('insurance_pol_no','Insurance Pol No.',$row) ?>
    <?= f_input('insurance_exp','Insurance Exp Date',$row,'date') ?>
  </div></fieldset>

  <fieldset><legend>Engine & Mileage</legend><div class="grid">
    <?= f_input('mileage','Odometer Reading',$row,'number') ?>
    <?= f_input('colour','Colour',$row) ?>
    <?= f_input('engine_no','Engine No.',$row) ?>
    <?= f_input('engine_capacity','Engine Capacity',$row) ?>
    <?= f_select('fuel_type','Fuel','fuels',$row) ?>
  </div></fieldset>

  <?php
  $blocks = [
    ['Body Work Assessment', $body, 'body_comment', 'Body work Comment'],
    ['Steering & Braking Assessment', $steer, 'steering_comment', 'Steering & Braking System Comment'],
    ['Electrical & Lighting', $elec, 'elec_comment', 'Electrical System Comment'],
    ['Transmission System Assessment', $trans, 'trans_comment', 'Transmission System Comment'],
    ['Suspension System Assessment', $susp, 'sus_comment', 'Suspension System Comment'],
    ['Engine, Cooling & AC Assessment', $eng, 'engine_comment', 'Engine System Comment'],
  ];
  foreach ($blocks as [$title, $fields, $cmt, $cmtLabel]): ?>
    <fieldset><legend><?= e($title) ?></legend><div class="grid">
      <?php foreach ($fields as $fn => $q) echo f_yn($fn, $q, $row); ?>
      <?php if ($title === 'Electrical & Lighting') echo f_input('headlight_type','Headlight Type',$row); ?>
    </div>
    <div style="margin-top:12px"><?= f_text($cmt, $cmtLabel, $row) ?></div>
    </fieldset>
  <?php endforeach; ?>

  <fieldset><legend>Vehicle Valuation</legend><div class="grid">
    <?= f_money('assessed_value','Assessed Value',$row,true,true) ?>
    <?= f_input('inspection_date','Inspection Date',$row,'date') ?>
    <?= f_input('inspection_officer','Inspection Officer',$row) ?>
    <div class="f"><label class="f">Status</label><select name="status">
      <?php foreach (valuation_statuses() as $k => $l): ?><option value="<?= $k ?>" <?= ($row['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
    <?= f_text('note_value','Note Value',$row,'W/S value estimated at 00000/= R/CD/TV value estimated at 00000/= REMARKS:') ?>
    <?= f_text('notes','N.B.',$row) ?>
    <?= f_text('extras','Extras',$row) ?>
    <?= f_text('anti_theft','Anti-Theft',$row) ?>
  </div></fieldset>

  <fieldset><legend>Extra Information</legend><div class="grid">
    <?= f_text('remarks','Remarks',$row) ?>
    <?= f_text('remedy','Remedy',$row) ?>
    <?= f_text('pending','Pending',$row) ?>
    <?= f_text('ammends','Ammendments',$row) ?>
  </div></fieldset>

  <button class="btn" type="submit"><i data-lucide="save"></i>Save</button>
  <a class="btn sec" href="<?= url('insurance_list.php') ?>"><i data-lucide="x"></i>Cancel</a>
  <?php if ($id): ?><a class="btn sec" href="<?= url('duplicate.php?type=insurance&id=' . (int)$id) ?>" onclick="return confirm('Create a duplicate of this valuation?')"><i data-lucide="copy"></i>Duplicate</a><?php endif; ?>
</form>
<?php form_assets(); ?>
<?php layout_footer();
