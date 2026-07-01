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

    if ($action === 'assign' && $req['status'] === 'requested') {
        $officerId = (int)($_POST['officer_id'] ?? 0);
        if (!$officerId || !isset($officers[$officerId])) {
            flash('Choose a valuer to assign.', 'err'); redirect('requests.php');
        }
        $table = $req['type'] === 'insurance' ? 'valuations' : 'bankvaluations';

        // Create the pre-filled valuation record owned by the assigned officer.
        $extra = ['created_by' => $officerId];
        if (column_exists($table, 'status'))    $extra['status'] = 'draft';
        if (column_exists($table, 'serial_no'))  $extra['serial_no'] = next_serial();
        if (column_exists($table, 'report_no'))  $extra['report_no'] = next_report_no($table);
        $post = ['reg_no' => $req['reg_no'], 'client' => (int)$req['client_id']];
        $vid  = save_row($table, ['reg_no', 'client'], $post, null, $extra);

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
if ($filter === 'open')          { $where = "status IN ('requested','assigned','in_progress')"; }
elseif ($filter !== 'all' && isset(request_statuses()[$filter])) { $where = 'status = ?'; $params[] = $filter; }

$st = db()->prepare("SELECT * FROM valuation_requests WHERE $where ORDER BY
    FIELD(status,'requested','assigned','in_progress','complete','cancelled'), id DESC LIMIT 500");
$st->execute($params);
$rows = $st->fetchAll();

// Names of assigned officers (for display)
$unames = [];
try { foreach (db()->query('SELECT id,name FROM users')->fetchAll() as $u) $unames[$u['id']] = $u['name']; } catch (Throwable $e) {}

layout_header('Valuation Requests', 'requests');
?>
<div class="reqfilter">
  <?php
  $tabs = ['open' => 'Open', 'requested' => 'Awaiting assignment', 'complete' => 'Complete', 'cancelled' => 'Cancelled', 'all' => 'All'];
  foreach ($tabs as $k => $lbl): ?>
    <a class="<?= $filter === $k ? 'on' : '' ?>" href="?status=<?= $k ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<table class="list">
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
      <td><?= $r['type'] === 'insurance' ? 'Insurance' : 'Bank' ?></td>
      <td class="muted"><?= e($r['requester_name'] ?: '—') ?></td>
      <td><?= request_badge($r['status']) ?></td>
      <td class="muted"><?= $r['assigned_to'] ? e($unames[$r['assigned_to']] ?? ('#' . $r['assigned_to'])) : '—' ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td class="actions">
        <?php if ($r['status'] === 'requested'): ?>
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
        <?php elseif (in_array($r['status'], ['assigned', 'in_progress'], true) && !empty($r['valuation_id'])): ?>
          <a class="rbtn" href="<?= url(($r['valuation_table'] === 'valuations' ? 'insurance_form.php' : 'bank_form.php') . '?id=' . (int)$r['valuation_id']) ?>"><i data-lucide="pencil"></i>Open</a>
        <?php elseif ($r['status'] === 'complete' && !empty($r['valuation_id'])): ?>
          <a class="rbtn" href="<?= url('preview.php?type=' . ($r['valuation_table'] === 'valuations' ? 'insurance' : 'bank') . '&id=' . (int)$r['valuation_id']) ?>" target="_blank"><i data-lucide="eye"></i>View</a>
        <?php else: ?>
          <span class="muted" style="font-size:12px">—</span>
        <?php endif; ?>
        <?php if (in_array($r['status'], ['requested', 'assigned'], true)): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Cancel this request?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="rbtn" type="submit"><i data-lucide="x"></i></button>
          </form>
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
  .list .lucide-user-check{color:#22c55e}.list .lucide-x{color:#ff6b6b}
</style>
<?php layout_footer();
