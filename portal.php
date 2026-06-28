<?php
require_once __DIR__ . '/portal_layout.php';
require_client();

$c   = current_client();
$cid = (int)$c['client_id'];
$tab = ($_GET['tab'] ?? 'bank') === 'insurance' ? 'insurance' : 'bank';
$table = $tab === 'insurance' ? 'valuations' : 'bankvaluations';
$vf    = $tab === 'insurance' ? 'assessed_value' : 'market_value';

function pcount(string $table, int $cid): int {
    $w = column_exists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
    try { $st = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE client = ?$w"); $st->execute([$cid]); return (int)$st->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
$bankCount = pcount('bankvaluations', $cid);
$insCount  = pcount('valuations', $cid);

$del = column_exists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
$statSel = column_exists($table, 'status') ? 'status' : "'' AS status";
$repSel  = column_exists($table, 'report_no') ? 'report_no' : "'' AS report_no";
$rows = [];
try {
    $st = db()->prepare("SELECT id, $repSel, reg_no, make, manufacture_year, insurance_exp, $statSel, `$vf` AS val, created_at
                         FROM `$table` WHERE client = ?$del ORDER BY id DESC LIMIT 1000");
    $st->execute([$cid]); $rows = $st->fetchAll();
} catch (Throwable $e) {}

$cur = setting('currency', CURRENCY);

portal_header('My Valuations');
?>
<h1 class="pt">Welcome<?= $c['name'] ? ', ' . e($c['name']) : '' ?></h1>
<p class="psub">Vehicle valuations carried out for your company by Kennet Automobile Valuers.</p>

<div class="kpis">
  <div class="kpi"><div class="kpi-ic"><i data-lucide="landmark"></i></div><div><div class="kpi-label">Bank Valuations</div><div class="kpi-num"><?= number_format($bankCount) ?></div></div></div>
  <div class="kpi"><div class="kpi-ic g"><i data-lucide="shield-check"></i></div><div><div class="kpi-label">Insurance Valuations</div><div class="kpi-num"><?= number_format($insCount) ?></div></div></div>
</div>

<div class="tabs">
  <a class="<?= $tab==='bank'?'on':'' ?>" href="?tab=bank">Bank Valuations</a>
  <a class="<?= $tab==='insurance'?'on':'' ?>" href="?tab=insurance">Insurance Valuations</a>
</div>

<input type="search" id="psearch" placeholder="Search your vehicles (reg no, make)…" autocomplete="off">
<table class="list" id="ptable">
  <thead><tr><th>Report No</th><th>Reg No.</th><th>Make/Model</th><th>YOM</th><th>Status</th><th><?= $tab==='insurance'?'Assessed':'Market' ?> Value</th><th>Date</th><th>Report</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="8" class="muted">No valuations on record yet.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['report_no']) ?: '—' ?></td>
      <td><?= e($r['reg_no']) ?></td>
      <td><?= e($r['make']) ?></td>
      <td><?= e($r['manufacture_year']) ?></td>
      <td><?= $r['status'] ? status_badge($r['status']) : '' ?></td>
      <td><?= $r['val'] !== null ? number_format((float)$r['val']) : '' ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td>
        <a class="rbtn" href="#" onclick="pOpen(<?= (int)$r['id'] ?>);return false;"><i data-lucide="eye"></i>View</a>
        <a class="rbtn" href="<?= url('portal_pdf.php?type='.$tab.'&id='.$r['id']) ?>"><i data-lucide="printer"></i>PDF</a>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>

<div class="modal-bg" id="pModal" onclick="if(event.target===this)pClose()">
  <div class="modal">
    <div class="modal-head"><span>Valuation Report</span>
      <span><a id="pPdf" href="#" target="_blank">⬇ Download PDF</a><button class="close" onclick="pClose()">✕ Close</button></span>
    </div>
    <iframe id="pFrame" src="about:blank"></iframe>
  </div>
</div>
<script>
var TAB='<?= $tab ?>';
function pOpen(id){document.getElementById('pFrame').src='<?= url('portal_view.php') ?>?type='+TAB+'&id='+id;document.getElementById('pPdf').href='<?= url('portal_pdf.php') ?>?type='+TAB+'&id='+id;document.getElementById('pModal').classList.add('open');document.body.style.overflow='hidden';}
function pClose(){document.getElementById('pModal').classList.remove('open');document.getElementById('pFrame').src='about:blank';document.body.style.overflow='';}
document.addEventListener('keydown',function(e){if(e.key==='Escape')pClose();});
(function(){var s=document.getElementById('psearch'),tb=document.querySelector('#ptable tbody');if(!s)return;
 s.addEventListener('input',function(){var q=s.value.toLowerCase();tb.querySelectorAll('tr').forEach(function(tr){tr.style.display=tr.textContent.toLowerCase().indexOf(q)>=0?'':'none';});});})();
</script>
<?php portal_footer();
