<?php
/**
 * Exact port of the original DomPDF bank valuation report template
 * (resources/views/livewire/reports/bankvaluation.blade.php).
 * Used for both on-screen Preview and PDF Print.
 */
require_once __DIR__ . '/lib.php';

/** Number → words, matching the old NumToWords output exactly. */
function report_words($number): string {
    $number = (int)$number;
    if ($number === 0) return 'zero Shillings';
    $ones = ['', 'One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven',
        'Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['', '', 'Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $thousands = ['', 'Thousand','Million','Billion','Trillion'];

    $num = (string)$number;
    $groups = array_reverse(str_split(str_pad($num, (int)(ceil(strlen($num) / 3) * 3), '0', STR_PAD_LEFT), 3));
    $words = [];
    foreach ($groups as $key => $group) {
        $group = (int)$group;
        if ($group === 0) continue;
        $gw = [];
        $h = (int)floor($group / 100);
        $rem = $group % 100;
        if ($h > 0) $gw[] = $ones[$h] . ' hundred';
        if ($rem > 0) {
            if ($rem < 20) $gw[] = $ones[$rem];
            else {
                $gw[] = $tens[(int)floor($rem / 10)];
                if ($rem % 10 > 0) $gw[] = $ones[$rem % 10];
            }
        }
        if ($gw) { $gw[] = $thousands[$key]; array_unshift($words, trim(implode(' ', $gw))); }
    }
    return implode(' ', $words) . ' Shillings';
}

/** Read an image file and return a data URI, or null if missing. */
function report_img_data(string $absPath): ?string {
    if (!is_file($absPath)) return null;
    $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
}

/** Build the full report HTML for one bank valuation row. */
function render_bank_report(array $val): string {
    $client    = lookup_name('clients', $val['client'] ?? null);
    $fuel      = lookup_name('fuels', $val['fuel_type'] ?? null);
    $valWords  = report_words($val['market_value'] ?? 0);
    $images    = !empty($val['images']) ? (json_decode($val['images'], true) ?: []) : [];

    $logo  = report_img_data(__DIR__ . '/assets/logo.png');
    $logo2 = report_img_data(__DIR__ . '/assets/logo2.png');
    $g = fn($k) => e($val[$k] ?? '');

    // Company / report details (editable in Settings).
    $coName   = setting('company_name', 'Kennet Automobile Valuers And Assessors Ltd.');
    $coAddr   = setting('company_address', 'Desai Road off Forest Rd, Opp Nairobi Gymkhana');
    $coPobox  = setting('company_pobox', '1268 - 00600 NAIROBI, KENYA');
    $coTel    = setting('company_tel', '+254 723 441 666, +254 712 730 738, +254 706 850 101');
    $coEmail  = setting('company_email', 'info@kennetvaluers.com');
    $coFooter = setting('report_footer', '© ' . $coName . ' | Valuation Report | Confidential Document');

    $sigFile    = __DIR__ . '/' . ltrim(setting('signature_image', 'storage/signature.png'), '/');
    $signed     = !empty($val['signed_at']);
    $sig        = ($signed && is_file($sigFile)) ? report_img_data($sigFile) : null;
    $signedDate = $signed ? date('d M Y', strtotime($val['signed_at'])) : '';

    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vehicle Valuation Report</title>
<style>
    @page { margin: 10px 40px 10px 0px; }
    #watermark { opacity:0.1; position:fixed; transform:rotate(45deg); bottom:13cm; left:5.5cm; width:8cm; height:8cm; z-index:-1000; }
    body { font-family:Arial, sans-serif; font-size:12px; line-height:1.4; color:#333; margin:0; padding:20px; }
    .container { width:100%; max-width:1000px; margin:0 auto; border:1px solid #ddd; padding:10px; box-sizing:border-box; }
    .header { display:flex; align-items:center; justify-content:center; }
    .header_image { max-width:35%; float:left; }
    .header_text { text-align:center; color:red; }
    .header .header_text h1 { margin:0; font-size:18px; color:red; }
    .company-info { border-bottom:1px solid #ddd; }
    .company-info p span { color:#d41d1dff; }
    table { width:100%; border-collapse:collapse; margin-bottom:20px; font-weight:bold; }
    th, td { border:1px solid #ddd; padding:8px; text-align:left; }
    th, td span { color:#080bc0ff; }
    th { background-color:#d5d2d282; font-weight:bold; color:#080bc0ff; }
    .card { border:1px solid #ddd; margin-bottom:15px; border-radius:4px; }
    .card-header { background-color:#d5d2d282; padding:8px 12px; font-weight:bold; border-bottom:1px solid #ddd; }
    .card-body { padding:12px; }
    .value-highlight { font-weight:bold; color:#9d9d9d; font-size:18px; }
    .signature-area { margin-top:40px; padding-top:20px; border-top:1px dashed #ccc; }
    .footer { margin-top:30px; padding-top:15px; border-top:1px solid #ddd; text-align:center; font-size:11px; color:#666; }
    .note { font-style:italic; color:#666; font-size:11px; }
    .text-center { text-align:center; }
    .mb-0 { margin-bottom:0; } .mt-20 { margin-top:20px; }
    .disclaimer p { color:#21490eff; }
</style>
</head>
<?php if ($logo): ?><div id="watermark"><img src="<?= $logo ?>" height="120%" width="200%"></div><?php endif; ?>
<body>
<div class="container">
    <div class="header">
        <div class="header_image"><?php if ($logo2): ?><img src="<?= $logo2 ?>" height="80" width="210"><?php endif; ?></div>
        <div class="header_text">
            <h1 style="color:black;"><?= e($coName) ?></h1>
            <h3 style="font-style:bold;">VALUATION REPORT</h3>
        </div>
    </div>

    <div class="company-info">
        <table>
            <tr>
                <td width="50%">
                    <p><span>Address:</span> <?= e($coAddr) ?></p>
                    <p><span>P.O. Box:</span> <?= e($coPobox) ?></p>
                </td>
                <td width="50%">
                    <p><span>Tel:</span> <?= e($coTel) ?></p>
                    <p><span>Email:</span> <?= e($coEmail) ?></p>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <tr><th width="50%">Report Information</th><th width="50%">Client Information</th></tr>
        <tr>
            <td>
                <p><span>Corporate Ref No:</span> <?= $g('corporate_ref_no') ?></p>
                <p><span>Serial No:</span> <?= $g('serial_no') ?></p>
                <p><span>Inspection Date:</span> <?= $g('assesment_date') ?></p>
            </td>
            <td>
                <p><span>Client Name:</span> <?= $g('customer_name') ?></p>
                <p><span>Client's Phone No:</span> <?= $g('phone_no') ?></p>
                <p><span>Policy No:</span> <?= $g('insurance_pol_no') ?></p>
                <p><span>Policy Expiry Date:</span> <?= $g('insurance_exp') ?></p>
            </td>
        </tr>
    </table>

    <table>
        <tr><th width="25%">Registration No</th><th width="25%">Make</th><th width="25%">Body Type</th><th width="25%">Year of Manufacture</th></tr>
        <tr><td><?= $g('reg_no') ?></td><td><?= $g('make') ?></td><td><?= $g('body_type') ?></td><td><?= $g('manufacture_year') ?></td></tr>
        <tr><th>Color</th><th>Date of Registration</th><th>Engine Rating</th><th>Fuel Type</th></tr>
        <tr><td><?= $g('colour') ?></td><td><?= $g('registration_date') ?></td><td><?= $g('engine_capacity') ?></td><td><?= e($fuel) ?></td></tr>
        <tr><th>Engine No</th><td><?= $g('engine_no') ?></td><th>Chassis Number</th><td><?= $g('chasis_no') ?></td></tr>
        <tr><th>Odometer Reading</th><td><?= $g('mileage') ?></td><th>Country of Origin</th><td><?= $g('country_of_origin') ?></td></tr>
    </table>

    <table>
        <tr><th width="25%">Body Work</th><th width="25%">Electrical</th><th width="25%">Mechanical</th><th width="25%">General Condition</th></tr>
        <tr><td><?= $g('coach_condition') ?></td><td><?= $g('electrical') ?></td><td><?= $g('mechanical_condition') ?></td><td><?= $g('general_condition') ?></td></tr>
    </table>

    <table>
        <tr><th width="33%">Tyre Type & Rating</th><th width="33%">Headlights</th><th width="34%">Anti-Theft Device</th></tr>
        <tr><td><?= $g('tyres') ?></td><td><?= $g('headlight_type') ?></td><td><?= $g('anti_theft') ?></td></tr>
    </table>

    <div style="page-break-before: always;"></div>

    <table>
        <tr><th width="33%" class="text-center">Market Value</th><th width="33%" class="text-center">Forced Value</th><th width="34%" class="text-center">Note Value</th></tr>
        <tr>
            <td class="text-center">
                <p class="value-highlight" style="color:#d41d1dff;"><?= number_format((float)($val['market_value'] ?? 0), 2) ?></p>
                <p><?= e($valWords) ?></p>
            </td>
            <td class="text-center"><p class="value-highlight" style="color:#d41d1dff;"><?= number_format((float)($val['forced_value'] ?? 0), 2) ?></p></td>
            <td><p style="color:#d41d1dff;"><?= $g('note_value') ?></p></td>
        </tr>
    </table>

    <table>
        <tr><th width="50%">Extras</th><th width="50%">N.B.</th></tr>
        <tr><td><?= $g('extras') ?></td><td style="color:#d41d1dff;"><?= $g('notes') ?></td></tr>
    </table>

    <table>
        <tr><th width="25%">Remarks</th><th width="25%">Remedy</th><th width="25%">Pending</th><th width="25%">Ammendments</th></tr>
        <tr><td style="color:#d41d1dff;"><?= $g('remarks') ?></td><td><?= $g('remedy') ?></td><td><?= $g('pending') ?></td><td><?= $g('ammends') ?></td></tr>
    </table>

    <table>
        <tr><th width="50%">Inspection Information</th><th width="50%">Administrative Details</th></tr>
        <tr>
            <td>
                <p><span>Inspected By:</span> <?= $g('principal_valuer') ?></p>
                <p><span>Inspection Location:</span> <?= $g('inspection_location') ?></p>
                <p><span>Bank Officer:</span> <?= $g('bank_officer') ?></p>
                <p><span>Report Destination:</span> <?= e($client) ?></p>
            </td>
            <td><?php if (!empty($val['report_no'])): ?><p><span>Report No:</span> <?= $g('report_no') ?></p><?php endif; ?><p><span>Kennet Valuers Identifier:</span> KENBNKVL-<?= $g('id') ?></p></td>
        </tr>
    </table>

    <div class="card mt-20">
        <div class="card-header">Disclaimer</div>
        <div class="card-body disclaimer">
            <p class="note">For and on behalf of Kennet Automobile Valuers & Assessors Ltd, this Report reflects the estimated Market Value of the subject vehicle in its present condition, as presented for inspection. The subject vehicle market value may vary for any upcoming inspections, due to usage among other factors. Information on this certificate is based on the information on the presented logbook/importation documents.</p>
            <p class="note mb-0">Tyre % Marks: New - Above 90%, Good: 70-89%, Fair: 55-69% Worn Out: Below 50%</p>
        </div>
    </div>

    <div class="signature-area">
        <table>
            <tr>
                <td width="50%"><p><span>Authorizing Signature:</span></p>
                    <div style="height:50px; border-bottom:1px solid #ccc; text-align:center;"><?php if ($sig): ?><img src="<?= $sig ?>" style="max-height:48px; max-width:230px;"><?php endif; ?></div>
                    <p class="note">Name & Stamp</p></td>
                <td width="50%"><p><span>Date:</span></p>
                    <div style="height:50px; border-bottom:1px solid #ccc; font-weight:bold; color:#080bc0ff;"><?= e($signedDate) ?></div></td>
            </tr>
        </table>
    </div>

    <div style="page-break-before: always;"></div>
    <div class="photos-area">
        <table>
        <?php if ($images): for ($i = 0; $i < count($images); $i += 2): ?>
            <tr>
                <td><?php $d = gd_jpeg_data_uri(UPLOAD_DIR . '/' . ltrim($images[$i], '/'), 1000, 62); if ($d): ?><img src="<?= $d ?>" width="290"><?php endif; ?></td>
                <?php if ($i + 1 < count($images)): $d2 = gd_jpeg_data_uri(UPLOAD_DIR . '/' . ltrim($images[$i+1], '/'), 1000, 62); ?>
                    <td><?php if ($d2): ?><img src="<?= $d2 ?>" width="290"><?php endif; ?></td>
                <?php else: ?><td></td><?php endif; ?>
            </tr>
        <?php endfor; else: ?>
            <tr><td>NO IMAGES</td></tr>
        <?php endif; ?>
        </table>
    </div>

    <?php $lb = !empty($val['logbook']) ? gd_jpeg_data_uri(UPLOAD_DIR . '/' . ltrim($val['logbook'], '/'), 1200, 70) : null; ?>
    <?php if ($lb): ?>
    <div style="page-break-before: always;"></div>
    <div class="logbook-area">
        <table><tr><td width="100%"><div style="height:50px; border-bottom:1px solid #ccc;"></div><img src="<?= $lb ?>" width="550"></td></tr></table>
    </div>
    <?php endif; ?>

    <div class="footer"><p><?= e($coFooter) ?></p></div>
</div>
</body>
</html>
<?php
    return ob_get_clean();
}

/** Load a bank valuation row by id, or null. */
function load_bank_valuation($id): ?array {
    $st = db()->prepare('SELECT * FROM bankvaluations WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Load an insurance valuation row by id, or null. */
function load_insurance_valuation($id): ?array {
    $st = db()->prepare('SELECT * FROM valuations WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Build the full insurance valuation report HTML for one row. */
function render_insurance_report(array $val): string {
    $client   = lookup_name('clients', $val['client'] ?? null);
    $fuel     = lookup_name('fuels', $val['fuel_type'] ?? null);
    $valWords = report_words($val['assessed_value'] ?? 0);
    $logo  = report_img_data(__DIR__ . '/assets/logo.png');
    $logo2 = report_img_data(__DIR__ . '/assets/logo2.png');
    $g = fn($k) => e($val[$k] ?? '');

    $coName   = setting('company_name', 'Kennet Automobile Valuers And Assessors Ltd.');
    $coAddr   = setting('company_address', 'Desai Road off Forest Rd, Opp Nairobi Gymkhana');
    $coPobox  = setting('company_pobox', '1268 - 00600 NAIROBI, KENYA');
    $coTel    = setting('company_tel', '+254 723 441 666, +254 712 730 738, +254 706 850 101');
    $coEmail  = setting('company_email', 'info@kennetvaluers.com');
    $coFooter = setting('report_footer', '© ' . $coName . ' | Valuation Report | Confidential Document');

    $sigFile    = __DIR__ . '/' . ltrim(setting('signature_image', 'storage/signature.png'), '/');
    $signed     = !empty($val['signed_at']);
    $sig        = ($signed && is_file($sigFile)) ? report_img_data($sigFile) : null;
    $signedDate = $signed ? date('d M Y', strtotime($val['signed_at'])) : '';

    // Checklist groups (field => question). Values stored as "1"/"0".
    $groups = [
        'Body Work' => ['scratches_cracks'=>'Paint Scratched/Cracked','paint_work'=>'Paint Faded/Repainted','accident_repairs'=>'Accident Repairs','bumper_damage'=>'Wings & Bumpers Dented','door_rubbers_damage'=>'Door Rubbers Torn','upholstery_damage'=>'Upholstery Damaged','chasis_damage'=>'Chasis/Subframe Damage'],
        'Steering & Braking' => ['rubber_boot_damage'=>'Steering Boots Torn','tie_rods_damage'=>'Tie-Rod Ends Worn','rack_damage'=>'Steering Box/Rack Knocks','steering_alignment_ok'=>'Self-aligns Properly','tilt_telescopic_ok'=>'Tilt Telescopic OK','power_steering_ok'=>'Power Steering OK','vibrations'=>'Vibrations','brakes_ok'=>'Brakes Effective','fluid_leaks'=>'Fluid Leaks'],
        'Electrical' => ['charging_state'=>'Charging OK','battery_state'=>'Battery Secured','wiring_damage'=>'Wires Hang Dangerously','headlights'=>'Headlights OK','brake_lights'=>'Brake/Turn Signals OK','dash_cluster'=>'Panel Lights OK'],
        'Transmission' => ['gb_mounts_damage'=>'Gearbox Mounts Worn','gb_cv_damage'=>'CV Joints Worn','gb_auto_gswith'=>'Auto Gearbox Smooth','gb_tc_spin'=>'Torque Converter Spin','gb_gear_damage'=>'Gear Jumps','gb_cluth_slip'=>'Clutch Slip','gb_propeller_damage'=>'Propeller Shaft Damage','diff_damage'=>'Noisy Differential'],
        'Suspension' => ['ball_joint_damage'=>'Ball Joints Worn','shocks_damage'=>'Shock Absorbers Worn','center_bolt_damage'=>'Center Bolt Worn','coil_springs_damage'=>'Coil Springs Broken','leaf_springs_damage'=>'Leaf Springs Weak','anchor_damage'=>'Anchor Points Damaged'],
        'Engine, Cooling & AC' => ['idling'=>'Starts & Idles OK','eng_mounts_damage'=>'Mountings/Belts Worn','oil_leaks'=>'Oil/Coolant Leaks','water_pump_ok'=>'Water Pump OK','radiator_damage'=>'Radiator Damage','air_con_damage'=>'AC Operates Well'],
    ];

    ob_start(); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Insurance Valuation Report</title>
<style>
    @page { margin: 10px 40px 10px 0px; }
    #watermark { opacity:0.1; position:fixed; transform:rotate(45deg); bottom:13cm; left:5.5cm; width:8cm; height:8cm; z-index:-1000; }
    body { font-family:Arial, sans-serif; font-size:12px; line-height:1.4; color:#333; margin:0; padding:20px; }
    .container { width:100%; max-width:1000px; margin:0 auto; border:1px solid #ddd; padding:10px; box-sizing:border-box; }
    .header { display:flex; align-items:center; justify-content:center; }
    .header_image { max-width:35%; float:left; }
    .header_text { text-align:center; color:red; }
    .header .header_text h1 { margin:0; font-size:18px; color:red; }
    .company-info { border-bottom:1px solid #ddd; }
    .company-info p span { color:#d41d1dff; }
    table { width:100%; border-collapse:collapse; margin-bottom:14px; font-weight:bold; }
    th, td { border:1px solid #ddd; padding:7px; text-align:left; }
    th, td span { color:#080bc0ff; }
    th { background-color:#d5d2d282; color:#080bc0ff; }
    .chk td { font-weight:normal; }
    .chk td.a { font-weight:bold; text-align:center; width:60px; }
    .grp { background:#f0f0f0; font-weight:bold; }
    .value-highlight { font-weight:bold; color:#d41d1dff; font-size:18px; }
    .card { border:1px solid #ddd; margin-bottom:15px; border-radius:4px; }
    .card-header { background-color:#d5d2d282; padding:8px 12px; font-weight:bold; border-bottom:1px solid #ddd; }
    .card-body { padding:12px; } .note { font-style:italic; color:#666; font-size:11px; }
    .footer { margin-top:20px; padding-top:12px; border-top:1px solid #ddd; text-align:center; font-size:11px; color:#666; }
    .text-center{text-align:center;}
</style></head>
<?php if ($logo): ?><div id="watermark"><img src="<?= $logo ?>" height="120%" width="200%"></div><?php endif; ?>
<body><div class="container">
    <div class="header">
        <div class="header_image"><?php if ($logo2): ?><img src="<?= $logo2 ?>" height="80" width="210"><?php endif; ?></div>
        <div class="header_text"><h1 style="color:black;"><?= e($coName) ?></h1><h3>INSURANCE VALUATION REPORT</h3></div>
    </div>
    <div class="company-info"><table><tr>
        <td width="50%"><p><span>Address:</span> <?= e($coAddr) ?></p><p><span>P.O. Box:</span> <?= e($coPobox) ?></p></td>
        <td width="50%"><p><span>Tel:</span> <?= e($coTel) ?></p><p><span>Email:</span> <?= e($coEmail) ?></p></td>
    </tr></table></div>

    <table>
        <tr><th width="50%">Report Information</th><th width="50%">Client Information</th></tr>
        <tr>
            <td><p><span>Corporate Ref No:</span> <?= $g('corporate_ref_no') ?></p><p><span>Inspection Date:</span> <?= $g('inspection_date') ?></p><p><span>Inspection Officer:</span> <?= $g('inspection_officer') ?></p></td>
            <td><p><span>Client Name:</span> <?= $g('customer_name') ?></p><p><span>Phone:</span> <?= $g('phone_no') ?></p><p><span>Policy No:</span> <?= $g('insurance_pol_no') ?></p><p><span>Policy Expiry:</span> <?= $g('insurance_exp') ?></p></td>
        </tr>
    </table>

    <table>
        <tr><th width="25%">Registration No</th><th width="25%">Make</th><th width="25%">YOM</th><th width="25%">Colour</th></tr>
        <tr><td><?= $g('reg_no') ?></td><td><?= $g('make') ?></td><td><?= $g('manufacture_year') ?></td><td><?= $g('colour') ?></td></tr>
        <tr><th>Engine No</th><th>Chassis No</th><th>Engine Rating</th><th>Fuel</th></tr>
        <tr><td><?= $g('engine_no') ?></td><td><?= $g('chasis_no') ?></td><td><?= $g('engine_capacity') ?></td><td><?= e($fuel) ?></td></tr>
        <tr><th>Odometer</th><th>Country of Origin</th><th>No. of Airbags</th><th>Inspection Location</th></tr>
        <tr><td><?= $g('mileage') ?></td><td><?= $g('country_of_origin') ?></td><td><?= $g('air_bags_no') ?></td><td><?= $g('inspection_location') ?></td></tr>
    </table>

    <?php foreach ($groups as $gname => $items): ?>
      <table class="chk"><tr><td class="grp" colspan="4"><?= e($gname) ?></td></tr>
      <?php $cells = []; foreach ($items as $f => $lbl) { $v=$val[$f]??''; $disp=$v==='1'?'Yes':($v==='0'?'No':'—'); $cells[]='<td>'.e($lbl).'</td><td class="a">'.$disp.'</td>'; }
      // two question/answer pairs per row
      for ($i=0;$i<count($cells);$i+=2) { echo '<tr>'.$cells[$i].($cells[$i+1]??'<td></td><td></td>').'</tr>'; } ?>
      </table>
    <?php endforeach; ?>

    <table>
        <tr><th width="50%" class="text-center">Assessed Value</th><th width="50%">Notes</th></tr>
        <tr>
            <td class="text-center"><p class="value-highlight"><?= number_format((float)($val['assessed_value'] ?? 0), 2) ?></p><p><?= e($valWords) ?></p></td>
            <td><?= $g('notes') ?></td>
        </tr>
    </table>

    <table>
        <tr><th width="25%">Remarks</th><th width="25%">Remedy</th><th width="25%">Pending</th><th width="25%">Ammendments</th></tr>
        <tr style="font-weight:normal"><td><?= $g('remarks') ?></td><td><?= $g('remedy') ?></td><td><?= $g('pending') ?></td><td><?= $g('ammends') ?></td></tr>
    </table>

    <div class="card"><div class="card-header">Disclaimer</div><div class="card-body">
        <p class="note">For and on behalf of <?= e($coName) ?>, this report reflects the assessed value and condition of the subject vehicle as presented for inspection on the stated date.</p>
        <?php if (!empty($val['report_no'])): ?><p class="note">Report No: <strong><?= $g('report_no') ?></strong></p><?php endif; ?>
        <p class="note">Kennet Valuers Identifier: <strong>KENINSVL-<?= $g('id') ?></strong></p>
    </div></div>

    <table><tr>
        <td width="50%"><p><span>Authorizing Signature:</span></p><div style="height:50px;border-bottom:1px solid #ccc;text-align:center;"><?php if ($sig): ?><img src="<?= $sig ?>" style="max-height:48px;max-width:230px;"><?php endif; ?></div><p class="note">Name & Stamp</p></td>
        <td width="50%"><p><span>Date:</span></p><div style="height:50px;border-bottom:1px solid #ccc;font-weight:bold;color:#080bc0ff;"><?= e($signedDate) ?></div></td>
    </tr></table>

    <div class="footer"><p><?= e($coFooter) ?></p></div>
</div></body></html>
<?php
    return ob_get_clean();
}
