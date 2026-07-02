<?php
require_once __DIR__ . '/portal_layout.php';
require_client();

$c   = current_client();
$cid = (int)$c['client_id'];

// Company type suggests the default (bank -> bank, client -> insurance), but the
// officer can also request a Machine valuation.
$ctype = 'client';
if (column_exists('clients', 'type')) {
    $st = db()->prepare('SELECT type FROM clients WHERE id = ?');
    $st->execute([$cid]);
    $ctype = ($st->fetchColumn() ?: 'client');
}
$defaultType = $ctype === 'bank' ? 'bank' : 'insurance';
$TYPES = ['bank' => 'Bank valuation', 'insurance' => 'Insurance valuation', 'machine' => 'Machine valuation'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $vtype = $_POST['type'] ?? $defaultType;
    if (!isset($TYPES[$vtype])) $vtype = $defaultType;
    $reg   = strtoupper(trim($_POST['reg_no'] ?? ''));
    $notes = trim($_POST['notes'] ?? '');
    if ($reg === '') {
        flash($vtype === 'machine' ? 'Please enter the machine name or identifier.' : 'Please enter the vehicle registration number.', 'err');
        redirect('portal_request.php');
    }
    $now = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO valuation_requests (client_id, requested_by, requester_name, reg_no, type, notes, status, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,\'requested\',?,?)')
        ->execute([$cid, (int)$c['id'], $c['name'] ?? '', $reg, $vtype, $notes, $now, $now]);
    $rid = (int)db()->lastInsertId();
    audit('request_create', 'valuation_request', $rid, $reg);
    notify_new_request($rid, $reg, $vtype, $c);
    flash('Request submitted. Kennet will assign a valuer shortly.');
    redirect('portal_requests.php');
}

portal_header('Request a Valuation', 'request');
?>
<h1 class="pt">Request a Valuation</h1>
<p class="psub">Choose the type, enter the item, and Kennet will assign a valuer. You'll see progress under <a href="<?= url('portal_requests.php') ?>" style="color:#5b9bff">My Requests</a>.</p>

<form class="card" method="post" style="max-width:520px">
  <?= csrf_field() ?>
  <div class="f">
    <label class="f">Valuation type</label>
    <select name="type" id="reqType">
      <?php foreach ($TYPES as $k => $lbl): ?><option value="<?= $k ?>" <?= $k === $defaultType ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="f">
    <label class="f" id="idLabel">Vehicle registration number</label>
    <input type="text" name="reg_no" id="reqReg" required autofocus placeholder="e.g. KDA 123A" style="text-transform:uppercase" value="">
  </div>
  <div class="f">
    <label class="f">Notes for the valuer (optional)</label>
    <textarea name="notes" rows="3" placeholder="Location, contact person, deadline…"></textarea>
  </div>
  <button class="btn" type="submit"><i data-lucide="send"></i> Submit request</button>
</form>
<script>
(function(){
  var t=document.getElementById('reqType'), lbl=document.getElementById('idLabel'), inp=document.getElementById('reqReg');
  function sync(){
    if(t.value==='machine'){ lbl.textContent='Machine name / identifier'; inp.placeholder='e.g. CLAAS MARKANT 65 BALER'; inp.style.textTransform='none'; }
    else { lbl.textContent='Vehicle registration number'; inp.placeholder='e.g. KDA 123A'; inp.style.textTransform='uppercase'; }
  }
  t.addEventListener('change',sync); sync();
})();
</script>
<?php portal_footer();
