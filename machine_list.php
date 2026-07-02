<?php
require_once __DIR__ . '/layout.php';
require_login();

$table = 'machinevaluations';
$soft  = column_exists($table, 'deleted_at');

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
  <?php if (can_edit()): ?><a class="btn" href="<?= url('machine_form.php') ?>"><i data-lucide="plus"></i>New Machine Valuation</a><?php endif; ?>
</div>

<table data-paginate="25" class="list">
  <thead><tr>
    <th>Serial</th><th>Machine</th><th>Customer</th><th>Client</th><th>Value (<?= e($cur) ?>)</th><th>Status</th><th>Officer</th><th>Date</th><th>Actions</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="9" class="muted">No machine valuations yet. <a href="<?= url('machine_form.php') ?>" style="color:#5b9bff">Add the first →</a></td></tr>
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
  .list .lucide-pencil{color:#5b9bff}.list .lucide-eye{color:#22c55e}.list .lucide-printer{color:#7c8896}.list .lucide-pen-tool{color:#f5b14a}.list .lucide-badge-check{color:#22c55e}
  .signbtn{animation:pulseamber 1.6s infinite}
  @keyframes pulseamber{0%,100%{box-shadow:0 0 0 0 rgba(245,177,74,.5)}50%{box-shadow:0 0 0 4px rgba(245,177,74,0)}}
  .modal iframe{flex:1;width:100%;border:0;background:#fff}
</style>
<?php layout_footer();
