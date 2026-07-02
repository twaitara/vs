<?php
require_once __DIR__ . '/layout.php';
require_login();

$table = 'machinevaluations';
$soft  = column_exists($table, 'deleted_at');

// Per-row delete (draft / in-progress only; completed & signed are protected).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    if (!is_admin()) { http_response_code(403); exit('Only admins can delete valuations.'); }
    $did = (int)($_POST['id'] ?? 0);
    if ($did && $soft) {
        $guard = '';
        if (column_exists($table, 'signed_at')) $guard .= ' AND signed_at IS NULL';
        if (column_exists($table, 'status'))    $guard .= " AND status <> 'complete'";
        $st = db()->prepare("UPDATE `$table` SET deleted_at = NOW() WHERE id = ?$guard");
        $st->execute([$did]);
        flash($st->rowCount() ? 'Valuation deleted.' : 'Completed/signed reports cannot be deleted.', $st->rowCount() ? 'ok' : 'err');
    }
    redirect('machine_list.php');
}

// Sign ALL unsigned machine valuations matching the current search.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['bulk'] ?? '') === 'sign_all') {
    csrf_verify();
    if (!can_sign()) { http_response_code(403); exit('You do not have a signing mandate.'); }
    if (!sign_all_enabled()) { http_response_code(403); exit('The "Sign all matching" action is disabled.'); }
    $sq = trim($_POST['q'] ?? '');
    $c = []; $p = [];
    if ($soft) $c[] = 'deleted_at IS NULL';
    if (column_exists($table, 'signed_at')) $c[] = 'signed_at IS NULL';
    if (!sees_all_valuations() && column_exists($table, 'created_by')) { $c[] = 'created_by = ?'; $p[] = (int)(current_user()['id'] ?? 0); }
    if ($sq !== '') { $c[] = '(machine_name LIKE ? OR customer_name LIKE ? OR serial_no LIKE ?)'; $l = "%$sq%"; array_push($p, $l, $l, $l); }
    $w = $c ? ' WHERE ' . implode(' AND ', $c) : '';
    $ids = db()->prepare("SELECT id FROM `$table`$w"); $ids->execute($p);
    $idList = $ids->fetchAll(PDO::FETCH_COLUMN);
    $n = $idList ? sign_records($table, $idList) : 0;
    audit('sign_all', 'machinevaluation', '', $n . ' signed');
    flash($n ? ($n . ' report(s) signed and marked Complete.') : 'No unsigned reports matched.', $n ? 'ok' : 'err');
    redirect('machine_list.php' . ($sq !== '' ? '?q=' . urlencode($sq) : ''));
}

$cond = []; $params = [];
if ($soft) $cond[] = 'deleted_at IS NULL';
if (!sees_all_valuations() && column_exists($table, 'created_by')) { $cond[] = 'created_by = ?'; $params[] = (int)(current_user()['id'] ?? 0); }
$q = trim($_GET['q'] ?? '');
if ($q !== '') { $cond[] = '(machine_name LIKE ? OR customer_name LIKE ? OR serial_no LIKE ?)'; $l = "%$q%"; array_push($params, $l, $l, $l); }
$where = $cond ? ' WHERE ' . implode(' AND ', $cond) : '';

$rows = [];
try {
    $st = db()->prepare("SELECT * FROM `$table`$where ORDER BY id DESC LIMIT 2000");
    $st->execute($params); $rows = $st->fetchAll();
} catch (Throwable $e) {}

// Officer initials for the Officer column.
$uNames = []; $uIni = [];
try {
    $cols = 'id, name' . (column_exists('users', 'initials') ? ', initials' : '');
    foreach (db()->query("SELECT $cols FROM users")->fetchAll() as $u) {
        $uNames[$u['id']] = $u['name']; $uIni[$u['id']] = $u['initials'] ?? '';
    }
} catch (Throwable $e) {}

$cur = setting('currency', CURRENCY);
layout_header('Machine Valuations', 'machine');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
  <form method="get" style="margin:0;flex:1;min-width:220px">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search machine, customer or serial…" autocomplete="off"
           style="width:100%;max-width:420px;background:var(--input);border:1px solid var(--line);color:var(--txt);padding:10px 12px;border-radius:8px;font-size:13px">
  </form>
  <div style="display:flex;gap:8px">
    <?php if (can_sign() && sign_all_enabled()): ?>
    <form method="post" style="margin:0" onsubmit="return confirm('Sign ALL unsigned machine reports matching the current search? This marks them Complete with today\'s date.')">
      <?= csrf_field() ?><input type="hidden" name="bulk" value="sign_all"><?php if ($q !== '') echo '<input type="hidden" name="q" value="' . e($q) . '">'; ?>
      <button class="btn" type="submit" style="background:#0f7a44"><i data-lucide="pen-tool"></i>Sign all matching</button>
    </form>
    <?php endif; ?>
    <a class="btn sec" href="<?= url('export.php?type=machine' . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>"><i data-lucide="download"></i>Export CSV</a>
    <?php if (can_edit()): ?><a class="btn" href="<?= url('machine_form.php') ?>"><i data-lucide="plus"></i>New Machine Valuation</a><?php endif; ?>
  </div>
</div>

<table data-paginate="25" data-colpick data-nosearch class="list">
  <thead><tr>
    <th>Serial</th><th>Machine</th><th>Customer</th><th>Client</th><th>Value (<?= e($cur) ?>)</th><th>Status</th><th>Valuer</th><th>Officer</th><th>Date</th><th data-nocolpick>Actions</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="10" class="muted">No machine valuations yet. <a href="<?= url('machine_form.php') ?>" style="color:#5b9bff">Add the first →</a></td></tr>
  <?php else: foreach ($rows as $r):
      $signed = !empty($r['signed_at']); $cb = $r['created_by'] ?? 0; ?>
    <tr>
      <td><?= e(serial_display($r['serial_no'] ?? '')) ?: '—' ?></td>
      <td><b><?= e($r['machine_name']) ?></b></td>
      <td><?= e($r['customer_name']) ?: '—' ?></td>
      <td class="muted"><?= e(lookup_name('clients', $r['client'] ?? null)) ?: '—' ?></td>
      <td><?= $r['market_value'] !== null ? number_format((float)$r['market_value']) : '—' ?></td>
      <td><?= !empty($r['status']) ? status_badge($r['status']) : '' ?></td>
      <td class="muted" title="<?= e($uNames[$cb] ?? '') ?>"><?= e($uIni[$cb] ?? '') ?: e($uNames[$cb] ?? '—') ?></td>
      <td class="muted"><?= e($r['officer'] ?? '') ?: '—' ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td class="actions">
        <?php if (can_edit()): ?><a class="rbtn" href="<?= url('machine_form.php?id=' . (int)$r['id']) ?>" title="Edit"><i data-lucide="pencil"></i></a><?php endif; ?>
        <a class="rbtn" href="#" onclick="openPreview(<?= (int)$r['id'] ?>);return false;" title="Preview"><i data-lucide="eye"></i></a>
        <a class="rbtn" href="<?= url('print.php?type=machine&id=' . (int)$r['id']) ?>" title="Print / PDF"><i data-lucide="printer"></i></a>
        <?php if (can_sign()): ?>
          <?php if ($signed): ?>
            <span class="rbtn" style="color:#22c55e" title="Signed"><i data-lucide="badge-check"></i></span>
          <?php else: ?>
            <a class="rbtn signbtn" href="<?= url('sign.php?type=machine&id=' . (int)$r['id'] . '&' . csrf_query()) ?>" onclick="return confirm('Sign and mark this report Complete?')" title="Sign report"><i data-lucide="pen-tool"></i></a>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (is_admin() && !$signed && ($r['status'] ?? '') !== 'complete'): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this valuation? It moves to the Recycle Bin.')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="rbtn del-one" type="submit" title="Delete"><i data-lucide="trash-2"></i></button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>

<div class="modal-bg" id="pvModal" onclick="if(event.target===this)pvClose()">
  <div class="modal">
    <div class="modal-head"><span>Machine Valuation</span>
      <span><a id="pvPdf" href="#" target="_blank">⬇ PDF</a><button class="close" onclick="pvClose()">✕ Close</button></span>
    </div>
    <iframe id="pvFrame" src="about:blank"></iframe>
  </div>
</div>
<script>
function openPreview(id){document.getElementById('pvFrame').src='<?= url('preview.php') ?>?type=machine&bare=1&id='+id;document.getElementById('pvPdf').href='<?= url('print.php') ?>?type=machine&id='+id;document.getElementById('pvModal').classList.add('open');document.body.style.overflow='hidden';}
function pvClose(){document.getElementById('pvModal').classList.remove('open');document.getElementById('pvFrame').src='about:blank';document.body.style.overflow='';}
document.addEventListener('keydown',function(e){if(e.key==='Escape')pvClose();});
</script>
<style>
  .list .lucide-pencil{color:#5b9bff}.list .lucide-eye{color:#22c55e}.list .lucide-printer{color:#7c8896}.list .lucide-pen-tool{color:#f5b14a}.list .lucide-badge-check{color:#22c55e}.list .lucide-trash-2{color:#ff6b6b}
  .del-one{background:none;border:1px solid var(--line);cursor:pointer}
  .signbtn{animation:pulseamber 1.6s infinite}
  @keyframes pulseamber{0%,100%{box-shadow:0 0 0 0 rgba(245,177,74,.5)}50%{box-shadow:0 0 0 4px rgba(245,177,74,0)}}
  .modal iframe{flex:1;width:100%;border:0;background:#fff}
</style>
<?php layout_footer();
