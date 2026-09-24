<?php
/**
 * Report verification — a bank/portal user confirms a valuation report is genuine
 * by opening (or scanning) its QR code. Only shows reports belonging to the
 * viewer's own company, and only when the token matches.
 */
require_once __DIR__ . '/report_template.php'; // pulls lib.php + the load_* helpers
require_once __DIR__ . '/portal_layout.php';   // portal_header/portal_footer + require_client
require_client(); // portal (bank) users only

$type = $_GET['t'] ?? 'bank';
if (!in_array($type, ['bank', 'insurance', 'machine'], true)) $type = 'bank';
$id   = (int)($_GET['id'] ?? 0);
$k    = (string)($_GET['k'] ?? '');

$cid  = (int)(current_client()['client_id'] ?? 0);
$ok = false; $reason = ''; $val = null;

if ($id <= 0 || !hash_equals(verify_token($type, $id), $k)) {
    $reason = 'This QR code / link is not valid.';
} else {
    $val = $type === 'insurance' ? load_insurance_valuation($id) : ($type === 'machine' ? load_machine_valuation($id) : load_bank_valuation($id));
    if (!$val) {
        $reason = 'No matching report was found.';
    } elseif ((int)($val['client'] ?? -1) !== $cid) {
        $reason = 'This report was not prepared for your organisation, so it cannot be verified here.';
    } elseif (empty($val['signed_at'])) {
        $reason = 'This report exists but has not been signed/finalised yet.';
    } else {
        $ok = true;
    }
}

$vf   = $type === 'insurance' ? 'assessed_value' : 'market_value';
$reg  = $val['reg_no'] ?? ($val['machine_name'] ?? '');
$cur  = setting('currency', CURRENCY);

portal_header('Verify Report', '');
?>
<h1 class="pt">Report Verification</h1>
<?php if ($ok): ?>
  <div class="vok"><div class="vbig">✓ GENUINE</div>
    <p class="psub" style="margin:4px 0 0">This is an authentic report issued by Kennet Automobile Valuers for your organisation.</p></div>
  <div class="card" style="max-width:560px;margin-top:16px">
    <table class="list">
      <tr><td class="vk">Registration / Machine</td><td><b><?= e($reg) ?></b></td></tr>
      <tr><td class="vk">Type</td><td><?= ucfirst($type) ?> valuation</td></tr>
      <tr><td class="vk">Market / Assessed Value</td><td><b><?= number_format((float)($val[$vf] ?? 0)) ?></b> <?= e($cur) ?></td></tr>
      <?php if ($type !== 'insurance'): ?><tr><td class="vk">Forced Value</td><td><?= number_format((float)($val['forced_value'] ?? 0)) ?> <?= e($cur) ?></td></tr><?php endif; ?>
      <tr><td class="vk">Serial No.</td><td><?= e(serial_display($val['serial_no'] ?? '')) ?: '—' ?></td></tr>
      <?php if (!empty($val['report_no'])): ?><tr><td class="vk">Report No.</td><td><?= e($val['report_no']) ?></td></tr><?php endif; ?>
      <tr><td class="vk">Signed</td><td><?= e(date('d M Y', strtotime((string)$val['signed_at']))) ?></td></tr>
      <tr><td class="vk">Signatory</td><td><?= e(setting('signatory_name', 'George Mwangi')) ?></td></tr>
    </table>
  </div>
  <p class="muted" style="font-size:12px;margin-top:12px">Confirm these figures match the printed report you are holding. If anything differs, contact Kennet.</p>
<?php else: ?>
  <div class="vbad"><div class="vbig">✕ COULD NOT VERIFY</div>
    <p class="psub" style="margin:4px 0 0"><?= e($reason) ?></p></div>
  <p class="muted" style="font-size:12px;margin-top:12px">If you scanned this from a Kennet report and expected it to verify, contact Kennet Automobile Valuers.</p>
<?php endif; ?>
<div style="margin-top:16px"><a class="btn sec" href="<?= url('verify_scan.php') ?>"><i data-lucide="qr-code"></i> Scan another</a></div>
<style>
  .vok,.vbad{border-radius:12px;padding:16px 18px}
  .vok{background:#0f3d24;border:1px solid #1c7a47} .vbad{background:#3d0f0f;border:1px solid #7a1c1c}
  .vbig{font-size:22px;font-weight:800;letter-spacing:.02em}.vok .vbig{color:#3ddc84}.vbad .vbig{color:#f5a3a3}
  td.vk{color:var(--mut);white-space:nowrap;width:45%}
</style>
<?php portal_footer();
