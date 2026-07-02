<?php
require_once __DIR__ . '/lib.php';
require_login();

$type = $_GET['type'] ?? 'bank';
$id   = $_GET['id'] ?? null;
if (!$id) { http_response_code(400); exit('Missing id'); }

$table = $type === 'insurance' ? 'valuations' : 'bankvaluations';
$st = db()->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
$st->execute([$id]);
$v = $st->fetch();
if (!$v) { http_response_code(404); exit('Not found'); }
require_own_valuation($v); // officers may only open their own records

$clientName = lookup_name('clients', $v['client'] ?? null);
$fuelName   = lookup_name('fuels', $v['fuel_type'] ?? null);
$valueField = $type === 'insurance' ? 'assessed_value' : 'market_value';
$valWords   = number_to_words($v[$valueField] ?? 0);
$dateField  = $type === 'insurance' ? 'inspection_date' : 'assesment_date';
$officer    = $type === 'insurance' ? ($v['inspection_officer'] ?? '') : ($v['principal_valuer'] ?? '');
$prefix     = $type === 'insurance' ? 'KENINSVL' : 'KENBNKVL';

// Resolve image file paths to data URIs (so the report is self-contained / printable).
function img_data(?string $relPath): ?string {
    if (!$relPath) return null;
    $full = UPLOAD_DIR . '/' . ltrim($relPath, '/');
    if (!is_file($full)) return null;
    $data = base64_encode(file_get_contents($full));
    $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
    return "data:$mime;base64,$data";
}
$images = !empty($v['images']) ? (json_decode($v['images'], true) ?: []) : [];
$logbook = img_data($v['logbook'] ?? null);

function row2(string $l, $val): string {
    return '<div class="cell"><span class="k">' . e($l) . '</span><span class="v">' . e($val) . '</span></div>';
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8">
<title>Valuation Report · <?= e($v['reg_no']) ?></title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;color:#222;margin:0;background:#f3f4f6}
  .sheet{background:#fff;max-width:820px;margin:18px auto;padding:34px 40px;box-shadow:0 1px 6px rgba(0,0,0,.15)}
  .bar{position:sticky;top:0;background:#171c22;color:#fff;padding:10px 16px;display:flex;gap:10px;justify-content:flex-end}
  .bar button,.bar a{background:#d41d1d;color:#fff;border:0;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:14px;text-decoration:none}
  .bar a{background:#2b3340}
  h2.co{color:#d41d1d;margin:0;font-size:21px}
  .head{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #d41d1d;padding-bottom:10px}
  .head .rt{text-align:right}
  .sub{color:#555;font-size:12px;text-align:center;margin:8px 0 18px;line-height:1.6}
  h3{background:#171c22;color:#fff;font-size:13px;padding:7px 12px;margin:20px 0 10px;border-radius:4px;letter-spacing:.3px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 24px}
  .cell{display:flex;justify-content:space-between;border-bottom:1px dotted #ccc;padding:5px 0;font-size:13px}
  .k{color:#666} .v{font-weight:600;color:#080bc0;text-align:right}
  .val{font-size:22px;font-weight:700;color:#d41d1d}
  .words{font-style:italic;color:#444;font-size:13px;margin-top:3px}
  .block{font-size:13px;white-space:pre-wrap;border:1px solid #eee;padding:8px;border-radius:4px;min-height:20px}
  .photos{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}
  .photos img{width:100%;border:1px solid #ddd;border-radius:4px}
  .sign{margin-top:40px;display:flex;justify-content:space-between}
  .sign div{width:45%;border-top:1px solid #333;padding-top:6px;font-size:12px;text-align:center;color:#555}
  .ident{margin-top:14px;font-size:12px;color:#888}
  .disc{font-size:11px;color:#666;border:1px solid #eee;padding:10px;border-radius:4px;margin-top:18px;line-height:1.5}
  table.chk{width:100%;border-collapse:collapse;font-size:12px}
  table.chk td{border:1px solid #e3e3e3;padding:5px 8px}
  table.chk td.a{font-weight:700;width:60px;text-align:center}
  @media print{ body{background:#fff} .bar{display:none} .sheet{box-shadow:none;margin:0;max-width:100%} .pb{page-break-before:always} }
</style>
</head>
<body>
<div class="bar">
  <a href="<?= url($type==='insurance'?'insurance_list.php':'bank_list.php') ?>">← Back</a>
  <button onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="sheet">
  <div class="head">
    <div><h2 class="co">Kennet Automobile Valuers & Assessors Ltd.</h2></div>
    <div class="rt"><strong><?= $type==='insurance'?'INSURANCE':'BANK' ?> VALUATION REPORT</strong></div>
  </div>
  <div class="sub">
    Desai Road off Forest Rd, Opp Nairobi Gymkhana · P.O. Box 1268-00600 Nairobi<br>
    Tel +254 723 441 666 / 712 730 738 / 706 850 101 · Email info@kennetvaluers.com
  </div>

  <h3>Report & Client Information</h3>
  <div class="grid">
    <?= row2('Corporate Ref No', $v['corporate_ref_no'] ?? '') ?>
    <?= row2('Client Name', $v['customer_name'] ?? '') ?>
    <?= row2('Serial No', $v['serial_no'] ?? '') ?>
    <?= row2("Client's Phone No", $v['phone_no'] ?? '') ?>
    <?= row2('Inspection Date', ddate($v[$dateField] ?? '')) ?>
    <?= row2('Policy No', $v['insurance_pol_no'] ?? '') ?>
    <?= row2('Report Destination', $clientName) ?>
    <?= row2('Policy Expiry', ddate($v['insurance_exp'] ?? '')) ?>
  </div>

  <h3>Vehicle Details</h3>
  <div class="grid">
    <?= row2('Reg No', $v['reg_no'] ?? '') ?>
    <?= row2('Make/Model', $v['make'] ?? '') ?>
    <?= row2('Body Type', $v['body_type'] ?? '') ?>
    <?= row2('YOM', $v['manufacture_year'] ?? '') ?>
    <?= row2('Colour', $v['colour'] ?? '') ?>
    <?= row2('Date of Registration', ddate($v['registration_date'] ?? '')) ?>
    <?= row2('Engine Rating (cc)', $v['engine_capacity'] ?? '') ?>
    <?= row2('Fuel Type', $fuelName) ?>
    <?= row2('Engine No', $v['engine_no'] ?? '') ?>
    <?= row2('Chassis No', $v['chasis_no'] ?? '') ?>
    <?= row2('Odometer Reading', $v['mileage'] ?? '') ?>
    <?= row2('Country of Origin', $v['country_of_origin'] ?? '') ?>
  </div>

  <?php if ($type === 'bank'): ?>
    <h3>Condition Assessment</h3>
    <div class="grid">
      <?= row2('Body Work', $v['coach_condition'] ?? '') ?>
      <?= row2('Electrical', $v['electrical'] ?? '') ?>
      <?= row2('Mechanical', $v['mechanical_condition'] ?? '') ?>
      <?= row2('General Condition', $v['general_condition'] ?? '') ?>
      <?= row2('Tyres', $v['tyres'] ?? '') ?>
      <?= row2('Headlights', $v['headlight_type'] ?? '') ?>
      <?= row2('Anti-Theft', $v['anti_theft'] ?? '') ?>
    </div>
  <?php else: ?>
    <h3>Inspection Checklist</h3>
    <?php
    $groups = [
      'Body Work'=>['scratches_cracks'=>'Paint Scratched/Cracked','paint_work'=>'Paint Faded/Repainted','accident_repairs'=>'Accident Repairs','bumper_damage'=>'Wings & Bumpers Dented','door_rubbers_damage'=>'Door Rubbers Torn','upholstery_damage'=>'Upholstery Damaged','chasis_damage'=>'Chasis/Subframe Damage'],
      'Steering & Braking'=>['rubber_boot_damage'=>'Steering Boots Torn','tie_rods_damage'=>'Tie-Rod Ends Worn','rack_damage'=>'Steering Box/Rack Knocks','steering_alignment_ok'=>'Self-aligns Properly','tilt_telescopic_ok'=>'Tilt Telescopic OK','power_steering_ok'=>'Power Steering OK','vibrations'=>'Vibrations','brakes_ok'=>'Brakes Effective','fluid_leaks'=>'Fluid Leaks'],
      'Electrical'=>['charging_state'=>'Charging OK','battery_state'=>'Battery Secured','wiring_damage'=>'Wires Hang Dangerously','headlights'=>'Headlights OK','brake_lights'=>'Brake/Turn Signals OK','dash_cluster'=>'Panel Lights OK'],
      'Transmission'=>['gb_mounts_damage'=>'Gearbox Mounts Worn','gb_cv_damage'=>'CV Joints Worn','gb_auto_gswith'=>'Auto Gearbox Smooth','gb_tc_spin'=>'Torque Converter Spin','gb_gear_damage'=>'Gear Jumps','gb_cluth_slip'=>'Clutch Slip','gb_propeller_damage'=>'Propeller Shaft Damage','diff_damage'=>'Noisy Differential'],
      'Suspension'=>['ball_joint_damage'=>'Ball Joints Worn','shocks_damage'=>'Shock Absorbers Worn','center_bolt_damage'=>'Center Bolt Worn','coil_springs_damage'=>'Coil Springs Broken','leaf_springs_damage'=>'Leaf Springs Weak','anchor_damage'=>'Anchor Points Damaged'],
      'Engine, Cooling & AC'=>['idling'=>'Starts & Idles OK','eng_mounts_damage'=>'Mountings/Belts Worn','oil_leaks'=>'Oil/Coolant Leaks','water_pump_ok'=>'Water Pump OK','radiator_damage'=>'Radiator Damage','air_con_damage'=>'AC Operates Well'],
    ];
    foreach ($groups as $gname => $items): ?>
      <table class="chk" style="margin-bottom:12px"><tr><td colspan="2" style="background:#f0f0f0;font-weight:700"><?= e($gname) ?></td></tr>
      <?php foreach ($items as $f => $lbl):
        $val = $v[$f] ?? ''; $disp = $val==='1'?'Yes':($val==='0'?'No':'—'); ?>
        <tr><td><?= e($lbl) ?></td><td class="a"><?= e($disp) ?></td></tr>
      <?php endforeach; ?>
      </table>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="pb"></div>
  <h3>Valuation</h3>
  <div class="cell"><span class="k"><?= $type==='insurance'?'Assessed Value':'Market Value' ?> (<?= e(CURRENCY) ?>)</span>
    <span class="val"><?= money($v[$valueField] ?? 0) ?></span></div>
  <div class="words"><?= e($valWords) ?></div>
  <?php if ($type === 'bank'): ?>
    <div class="cell" style="margin-top:8px"><span class="k">Forced Value (<?= e(CURRENCY) ?>)</span>
      <span class="val" style="font-size:18px"><?= money($v['forced_value'] ?? 0) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($v['note_value'])): ?><div style="margin-top:8px"><span class="k">Note Value</span><div class="block"><?= e($v['note_value']) ?></div></div><?php endif; ?>

  <h3>Remarks & Notes</h3>
  <div class="grid">
    <div><span class="k">Remarks</span><div class="block"><?= e($v['remarks'] ?? '') ?></div></div>
    <div><span class="k">Remedy</span><div class="block"><?= e($v['remedy'] ?? '') ?></div></div>
    <div><span class="k">Pending</span><div class="block"><?= e($v['pending'] ?? '') ?></div></div>
    <div><span class="k">Extras</span><div class="block"><?= e($v['extras'] ?? '') ?></div></div>
  </div>

  <h3>Inspection Details</h3>
  <div class="grid">
    <?= row2('Inspected By', $officer) ?>
    <?= row2('Inspection Location', $v['inspection_location'] ?? '') ?>
    <?php if ($type==='bank'): ?>
      <?= row2('Bank Officer', $v['bank_officer'] ?? '') ?>
      <?= row2('Report Destination', $clientName) ?>
    <?php endif; ?>
  </div>
  <div class="ident">Kennet Valuers Identifier: <strong><?= $prefix ?>-<?= e($v['id']) ?></strong></div>

  <div class="disc">
    This valuation reflects the condition of the vehicle on the date of inspection and the prevailing market.
    Tyre condition key: New &gt;90% · Good 70-89% · Fair 55-69% · Worn Out &lt;50%.
    This is a confidential document prepared solely for the named client.
  </div>

  <div class="sign">
    <div>Authorizing Signature / Date</div>
    <div>Name & Stamp</div>
  </div>

  <?php if ($images): ?>
    <div class="pb"></div><h3>Vehicle Photos</h3>
    <div class="photos">
      <?php foreach ($images as $img): $d = img_data($img); if ($d): ?>
        <img src="<?= $d ?>" alt="photo">
      <?php endif; endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($logbook): ?>
    <div class="pb"></div><h3>Logbook</h3><img src="<?= $logbook ?>" style="max-width:100%;border:1px solid #ddd">
  <?php endif; ?>

  <p style="text-align:center;color:#999;font-size:11px;margin-top:30px">© <?= date('Y') ?> Kennet Automobile Valuers & Assessors Ltd. — Confidential Document</p>
</div>
</body></html>
