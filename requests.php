<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/forms.php';
require_login();
if (!can_assign()) { flash('You do not have access to the requests inbox.', 'err'); redirect('dashboard.php'); }

$clients  = lookup('clients');           // id => name
$officers = officer_list();              // id => label

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $rid    = (int)($_POST['id'] ?? 0);

    $st = db()->prepare('SELECT * FROM valuation_requests WHERE id=?');
    $st->execute([$rid]);
    $req = $st->fetch();
    if (!$req) { flash('Request not found.', 'err'); redirect('requests.php'); }

    if ($action === 'accept' && $req['status'] === 'requested') {
        db()->prepare("UPDATE valuation_requests SET status='accepted', updated_at=NOW() WHERE id=?")->execute([$rid]);
        audit('request_accept', 'valuation_request', $rid, $req['reg_no']);
        notify_user('client', (int)$req['requested_by'], 'Request accepted: ' . $req['reg_no'], 'Kennet has accepted your valuation request and will assign a valuer.', 'portal_requests.php');
        notify_client_admins((int)$req['client_id'], 'Request accepted: ' . $req['reg_no'], 'Kennet accepted the request raised by ' . ($req['requester_name'] ?: 'your team') . '.', 'portal_requests.php', (int)$req['requested_by']);
        send_mail(client_user_email((int)$req['requested_by']), 'Valuation request accepted: ' . $req['reg_no'], "Your valuation request for {$req['reg_no']} has been accepted. A valuer will be assigned shortly.");
        flash('Request accepted.');
        redirect('requests.php');
    } elseif ($action === 'deny' && in_array($req['status'], ['requested', 'accepted'], true)) {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') { flash('A reason is required to deny a request.', 'err'); redirect('requests.php'); }
        $hasReason = column_exists('valuation_requests', 'deny_reason');
        if ($hasReason) db()->prepare("UPDATE valuation_requests SET status='denied', deny_reason=?, updated_at=NOW() WHERE id=?")->execute([$reason, $rid]);
        else            db()->prepare("UPDATE valuation_requests SET status='denied', updated_at=NOW() WHERE id=?")->execute([$rid]);
        audit('request_deny', 'valuation_request', $rid, $req['reg_no'] . ' — ' . $reason);
        notify_user('client', (int)$req['requested_by'], 'Request declined: ' . $req['reg_no'], 'Reason: ' . $reason, 'portal_requests.php');
        notify_client_admins((int)$req['client_id'], 'Request declined: ' . $req['reg_no'], 'Raised by ' . ($req['requester_name'] ?: 'your team') . '. Reason: ' . $reason, 'portal_requests.php', (int)$req['requested_by']);
        send_mail(client_user_email((int)$req['requested_by']), 'Valuation request declined: ' . $req['reg_no'], "Your valuation request for {$req['reg_no']} was declined.\n\nReason: $reason");
        flash('Request denied.');
        redirect('requests.php');
    } elseif ($action === 'assign' && in_array($req['status'], ['requested', 'accepted'], true)) {
        $officerId = (int)($_POST['officer_id'] ?? 0);
        if (!$officerId || !isset($officers[$officerId])) {
            flash('Choose a valuer to assign.', 'err'); redirect('requests.php');
        }
        $table = ['insurance' => 'valuations', 'machine' => 'machinevaluations'][$req['type']] ?? 'bankvaluations';

        // Create the pre-filled valuation record owned by the assigned officer.
        $extra = ['created_by' => $officerId];
        if (column_exists($table, 'status'))    $extra['status'] = 'draft';
        if (column_exists($table, 'serial_no'))  $extra['serial_no'] = next_serial();
        if (column_exists($table, 'report_no'))  $extra['report_no'] = next_report_no($table);
        // Attribute the valuation to the portal officer who requested it.
        $offCol = requesting_officer_field($table);
        if (column_exists($table, $offCol) && trim((string)$req['requester_name']) !== '') {
            $extra[$offCol] = $req['requester_name'];
        }
        // Machines have no reg_no — the requested identifier is the machine name.
        if ($table === 'machinevaluations') { $post = ['machine_name' => $req['reg_no'], 'client' => (int)$req['client_id']]; $cols = ['machine_name', 'client']; }
        else                                { $post = ['reg_no' => $req['reg_no'], 'client' => (int)$req['client_id']]; $cols = ['reg_no', 'client']; }
        $vid  = save_row($table, $cols, $post, null, $extra);

        db()->prepare("UPDATE valuation_requests SET status='assigned', assigned_to=?, valuation_id=?, valuation_table=?, updated_at=NOW() WHERE id=?")
            ->execute([$officerId, $vid, $table, $rid]);
        audit('request_assign', 'valuation_request', $rid, $req['reg_no'] . ' → ' . ($officers[$officerId] ?? $officerId));
        notify_assigned($rid, (string)$req['reg_no'], $officerId);
        flash('Assigned to ' . ($officers[$officerId] ?? 'valuer') . '. A valuation record was created for ' . $req['reg_no'] . '.');
        redirect('requests.php');
    } elseif ($action === 'cancel' && in_array($req['status'], ['requested', 'assigned'], true)) {
        db()->prepare("UPDATE valuation_requests SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$rid]);
        audit('request_cancel', 'valuation_request', $rid, $req['reg_no']);
        flash('Request cancelled.');
        redirect('requests.php');
    }
    redirect('requests.php');
}

$filter = $_GET['status'] ?? 'open';
$where  = '1=1'; $params = [];
if ($filter === 'open')          { $where = "status IN ('requested','accepted','assigned','in_progress')"; }
elseif ($filter !== 'all' && isset(request_statuses()[$filter])) { $where = 'status = ?'; $params[] = $filter; }

$st = db()->prepare("SELECT * FROM valuation_requests WHERE $where ORDER BY
    FIELD(status,'requested','accepted','assigned','in_progress','complete','denied','cancelled'), id DESC LIMIT 500");
$st->execute($params);
$rows = $st->fetchAll();

// Names of assigned officers (for display)
$unames = [];
try { foreach (db()->query('SELECT id,name FROM users')->fetchAll() as $u) $unames[$u['id']] = $u['name']; } catch (Throwable $e) {}

layout_header('Valuation Requests', 'requests');
?>
<div class="reqfilter">
  <?php
  $tabs = ['open' => 'Open', 'requested' => 'Awaiting review', 'accepted' => 'Accepted', 'complete' => 'Complete', 'denied' => 'Denied', 'all' => 'All'];
  foreach ($tabs as $k => $lbl): ?>
    <a class="<?= $filter === $k ? 'on' : '' ?>" href="?status=<?= $k ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<table data-paginate="25" class="list">
  <thead><tr>
    <th>Reg No.</th><th>Company</th><th>Type</th><th>Requested by</th><th>Status</th><th>Valuer</th><th>Requested</th><th>Action</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="8" class="muted">No requests in this view.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['reg_no']) ?></b></td>
      <td><?= e($clients[$r['client_id']] ?? ('#' . $r['client_id'])) ?></td>
      <td><?= ucfirst($r['type']) ?></td>
      <td class="muted"><?= e($r['requester_name'] ?: '—') ?></td>
      <td><?= request_badge($r['status']) ?></td>
      <td class="muted"><?= $r['assigned_to'] ? e($unames[$r['assigned_to']] ?? ('#' . $r['assigned_to'])) : '—' ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td class="actions" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <?php if (in_array($r['status'], ['requested', 'accepted'], true)): ?>
          <?php if ($r['status'] === 'requested'): ?>
          <form method="post" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="accept"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="rbtn ok" type="submit"><i data-lucide="check"></i>Accept</button>
          </form>
          <?php endif; ?>
          <form method="post" style="display:flex;gap:6px;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select name="officer_id" required style="padding:6px 8px;font-size:12px">
              <option value="">Assign to…</option>
              <?php foreach ($officers as $oid => $on): ?><option value="<?= $oid ?>"><?= e($on) ?></option><?php endforeach; ?>
            </select>
            <button class="rbtn" type="submit"><i data-lucide="user-check"></i>Assign</button>
          </form>
          <form method="post" style="display:inline" onsubmit="return reqDeny(this)">
            <?= csrf_field() ?><input type="hidden" name="action" value="deny"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="reason" value="">
            <button class="rbtn danger" type="submit"><i data-lucide="x"></i>Deny</button>
          </form>
        <?php elseif (in_array($r['status'], ['assigned', 'in_progress'], true) && !empty($r['valuation_id'])): ?>
          <a class="rbtn" href="<?= url(table_form($r['valuation_table']) . '?id=' . (int)$r['valuation_id']) ?>"><i data-lucide="pencil"></i>Open</a>
        <?php elseif ($r['status'] === 'complete' && !empty($r['valuation_id'])): ?>
          <a class="rbtn" href="<?= url('preview.php?type=' . table_type($r['valuation_table']) . '&id=' . (int)$r['valuation_id']) ?>" target="_blank"><i data-lucide="eye"></i>View</a>
        <?php elseif ($r['status'] === 'denied' && !empty($r['deny_reason'])): ?>
          <span class="muted" style="font-size:12px" title="<?= e($r['deny_reason']) ?>">Reason: <?= e(mb_strimwidth((string)$r['deny_reason'], 0, 40, '…')) ?></span>
        <?php else: ?>
          <span class="muted" style="font-size:12px">—</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<style>
  .reqfilter{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
  .reqfilter a{padding:8px 14px;border-radius:8px;font-size:13px;color:var(--mut);background:var(--panel);border:1px solid var(--line)}
  .reqfilter a.on{background:var(--accent);color:#fff;border-color:var(--accent)}
  .list .lucide-user-check{color:#22c55e}.list .lucide-x{color:#ff6b6b}.list .lucide-check{color:#22c55e}
  .rbtn.ok{border-color:#1c7a47}.rbtn.ok .lucide-check{color:#3ddc84}
  .rbtn.danger{border-color:#7a1c1c}.rbtn.danger .lucide-x{color:#ff6b6b}
</style>
<script>
function reqDeny(f){var r=prompt('Reason for denying this request (required):');if(r===null)return false;r=r.trim();if(!r){alert('A reason is required.');return false;}f.reason.value=r;return true;}
</script>
<?php layout_footer();
