<?php
require_once __DIR__ . '/portal_layout.php';
require_client();

$c    = current_client();
$cid  = (int)$c['client_id'];
$mine = !client_is_admin();   // portal admins see the whole company; officers see only their own

// Cancel own pending request
if (isset($_GET['cancel'])) {
    csrf_verify_get();
    $id = (int)$_GET['cancel'];
    $sql = "UPDATE valuation_requests SET status='cancelled', updated_at=NOW() WHERE id=? AND client_id=? AND status='requested'";
    $params = [$id, $cid];
    if ($mine) { $sql .= ' AND requested_by=?'; $params[] = (int)$c['id']; }
    db()->prepare($sql)->execute($params);
    flash('Request cancelled.');
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
<h1 class="pt"><?= $mine ? 'My Requests' : 'Company Requests' ?></h1>
<p class="psub"><?= $mine ? 'Valuations you have requested.' : 'All valuation requests raised by your company.' ?> <a href="<?= url('portal_request.php') ?>" style="color:#5b9bff">+ New request</a></p>

<table data-paginate="25" class="list">
  <thead><tr>
    <th>Reg No.</th><th>Type</th>
    <?php if (!$mine): ?><th>Requested by</th><?php endif; ?>
    <th>Status</th><th>Requested</th><th></th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="<?= $mine ? 5 : 6 ?>" class="muted">No requests yet. <a href="<?= url('portal_request.php') ?>" style="color:#5b9bff">Make your first request →</a></td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['reg_no']) ?></b></td>
      <td><?= $r['type'] === 'bank' ? 'Bank' : 'Insurance' ?></td>
      <?php if (!$mine): ?><td class="muted"><?= e($r['requester_name'] ?: '—') ?></td><?php endif; ?>
      <td><?= request_badge($r['status']) ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td>
        <?php if ($r['status'] === 'requested'): ?>
          <a class="rbtn" href="?cancel=<?= (int)$r['id'] ?>&<?= csrf_query() ?>" onclick="return confirm('Cancel this request?')"><i data-lucide="x"></i>Cancel</a>
        <?php elseif ($r['status'] === 'complete' && !empty($r['valuation_id'])): ?>
          <a class="rbtn" href="<?= url('portal_pdf.php?type=' . ($r['valuation_table'] === 'valuations' ? 'insurance' : 'bank') . '&id=' . (int)$r['valuation_id']) ?>"><i data-lucide="printer"></i>Report</a>
        <?php else: ?>
          <span class="muted" style="font-size:12px">—</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<?php portal_footer();
