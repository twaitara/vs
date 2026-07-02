<?php
require_once __DIR__ . '/portal_layout.php';
require_client();

$c    = current_client();
$cid  = (int)$c['client_id'];
$mine = !client_is_admin();   // portal admins see the whole company; officers see only their own

/** Fetch one request the current portal user is allowed to act on. */
function load_own_request(int $id, int $cid, bool $mine, array $c): ?array {
    $sql = 'SELECT * FROM valuation_requests WHERE id=? AND client_id=?';
    $p   = [$id, $cid];
    if ($mine) { $sql .= ' AND requested_by=?'; $p[] = (int)$c['id']; }
    $st = db()->prepare($sql); $st->execute($p);
    return $st->fetch() ?: null;
}
/** Soft-delete the linked valuation record if it exists and hasn't been signed. */
function drop_linked_valuation(array $r): void {
    if (empty($r['valuation_id']) || empty($r['valuation_table'])) return;
    $t = $r['valuation_table'];
    if (!in_array($t, ['bankvaluations', 'valuations', 'machinevaluations'], true)) return;
    if (column_exists($t, 'deleted_at')) {
        $cond = column_exists($t, 'signed_at') ? ' AND signed_at IS NULL' : '';
        db()->prepare("UPDATE `$t` SET deleted_at=NOW() WHERE id=?$cond")->execute([(int)$r['valuation_id']]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $r      = load_own_request($id, $cid, $mine, $c);
    if (!$r) { flash('Request not found.', 'err'); redirect('portal_requests.php'); }

    if ($action === 'revoke' && in_array($r['status'], ['requested', 'assigned', 'in_progress'], true)) {
        db()->prepare("UPDATE valuation_requests SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
        drop_linked_valuation($r); // remove the draft valuation created on assignment (if unsigned)
        audit('request_revoke', 'valuation_request', $id, $r['reg_no']);
        flash('Request revoked.');
    } elseif ($action === 'delete') {
        drop_linked_valuation($r);
        db()->prepare('DELETE FROM valuation_requests WHERE id=?')->execute([$id]);
        audit('request_delete', 'valuation_request', $id, $r['reg_no']);
        flash('Request deleted.');
    }
    redirect('portal_requests.php');
}

$where  = 'client_id = ?';
$params = [$cid];
if ($mine) { $where .= ' AND requested_by = ?'; $params[] = (int)$c['id']; }
$st = db()->prepare("SELECT * FROM valuation_requests WHERE $where ORDER BY id DESC LIMIT 500");
$st->execute($params);
$rows = $st->fetchAll();

portal_header('My Requests', 'requests');
?>
<div class="reqhead">
  <div>
    <h1 class="pt"><?= $mine ? 'My Requests' : 'Company Requests' ?></h1>
    <p class="psub" style="margin-bottom:0"><?= $mine ? 'Valuations you have requested.' : 'All valuation requests raised by your company.' ?></p>
  </div>
  <a class="btn" href="<?= url('portal_request.php') ?>"><i data-lucide="plus"></i> New Request</a>
</div>

<table data-paginate="25" class="list">
  <thead><tr>
    <th>Reg No.</th><th>Type</th>
    <?php if (!$mine): ?><th>Requested by</th><?php endif; ?>
    <th>Status</th><th>Requested</th><th>Actions</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="<?= $mine ? 5 : 6 ?>" class="muted">No requests yet. <a href="<?= url('portal_request.php') ?>" style="color:#5b9bff">Make your first request →</a></td></tr>
  <?php else: foreach ($rows as $r): $active = in_array($r['status'], ['requested', 'accepted', 'assigned', 'in_progress'], true); ?>
    <tr>
      <td><b><?= e($r['reg_no']) ?></b></td>
      <td><?= ucfirst($r['type']) ?></td>
      <?php if (!$mine): ?><td class="muted"><?= e($r['requester_name'] ?: '—') ?></td><?php endif; ?>
      <td><?= request_badge($r['status']) ?><?php if ($r['status'] === 'denied' && !empty($r['deny_reason'])): ?><div class="muted" style="font-size:11px;margin-top:3px">Reason: <?= e($r['deny_reason']) ?></div><?php endif; ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td class="ract">
        <?php if ($r['status'] === 'complete' && !empty($r['valuation_id'])): ?>
          <a class="rbtn" href="<?= url('portal_pdf.php?type=' . table_type($r['valuation_table']) . '&id=' . (int)$r['valuation_id']) ?>"><i data-lucide="printer"></i>Report</a>
        <?php endif; ?>
        <?php if ($active): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Revoke this request? Any valuation started for it will be withdrawn.')">
            <?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="rbtn warn" type="submit"><i data-lucide="ban"></i>Revoke</button>
          </form>
        <?php endif; ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this request permanently?')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="rbtn danger" type="submit"><i data-lucide="trash-2"></i>Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<style>
  .reqhead{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:18px;flex-wrap:wrap}
  .ract{display:flex;gap:6px;flex-wrap:wrap}
  .rbtn.warn{color:#f5d79a}.rbtn.warn i{color:#f5b14a}
  .rbtn.danger{color:#f5a3a3}.rbtn.danger i{color:#ff6b6b}
  .rbtn .lucide-printer{color:#7c8896}
</style>
<?php portal_footer();
