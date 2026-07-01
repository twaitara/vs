<?php
require_once __DIR__ . '/portal_layout.php';
require_client();

$c   = current_client();
$cid = (int)$c['client_id'];

// Valuation type is driven by the company type: bank -> bank valuation, client -> insurance.
$ctype = 'client';
if (column_exists('clients', 'type')) {
    $st = db()->prepare('SELECT type FROM clients WHERE id = ?');
    $st->execute([$cid]);
    $ctype = ($st->fetchColumn() ?: 'client');
}
$vtype = $ctype === 'bank' ? 'bank' : 'insurance';
$vtypeLabel = $vtype === 'bank' ? 'Bank valuation' : 'Insurance valuation';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $reg   = strtoupper(trim($_POST['reg_no'] ?? ''));
    $notes = trim($_POST['notes'] ?? '');
    if ($reg === '') {
        flash('Please enter the vehicle registration number.', 'err');
        redirect('portal_request.php');
    }
    $now = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO valuation_requests (client_id, requested_by, requester_name, reg_no, type, notes, status, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,\'requested\',?,?)')
        ->execute([$cid, (int)$c['id'], $c['name'] ?? '', $reg, $vtype, $notes, $now, $now]);
    $rid = (int)db()->lastInsertId();
    audit('request_create', 'valuation_request', $rid, $reg);
    notify_new_request($rid, $reg, $vtype, $c);   // stage 5 (no-op until wired)
    flash('Request submitted. Kennet will assign a valuer shortly.');
    redirect('portal_requests.php');
}

portal_header('Request a Valuation', 'request');
?>
<h1 class="pt">Request a Valuation</h1>
<p class="psub">Enter the vehicle registration and Kennet will assign a valuer. You'll see progress under <a href="<?= url('portal_requests.php') ?>" style="color:#5b9bff">My Requests</a>.</p>

<form class="card" method="post" style="max-width:520px">
  <?= csrf_field() ?>
  <div class="f">
    <label class="f">Vehicle registration number</label>
    <input type="text" name="reg_no" required autofocus placeholder="e.g. KDA 123A" style="text-transform:uppercase" value="">
  </div>
  <div class="f">
    <label class="f">Valuation type</label>
    <input type="text" value="<?= e($vtypeLabel) ?>" disabled>
    <div class="muted" style="font-size:11px;margin-top:5px">Set automatically from your company profile.</div>
  </div>
  <div class="f">
    <label class="f">Notes for the valuer (optional)</label>
    <textarea name="notes" rows="3" placeholder="Location, contact person, deadline…"></textarea>
  </div>
  <button class="btn" type="submit"><i data-lucide="send"></i> Submit request</button>
</form>
<?php portal_footer();
