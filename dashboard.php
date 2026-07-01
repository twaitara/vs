<?php
require_once __DIR__ . '/layout.php';
require_login();

/** Helper: scalar query with graceful fallback. */
function dash_scalar(string $sql, array $p = []) {
    try { $st = db()->prepare($sql); $st->execute($p); return $st->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
$bankWhere = not_deleted_sql('bankvaluations');
$insWhere  = not_deleted_sql('valuations');

$bankTotal = (int)dash_scalar("SELECT COUNT(*) FROM bankvaluations" . $bankWhere);
$insTotal  = (int)dash_scalar("SELECT COUNT(*) FROM valuations" . $insWhere);
$monthCond = "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
$bankMonth = (int)dash_scalar("SELECT COUNT(*) FROM bankvaluations" . ($bankWhere ? $bankWhere . " AND " : " WHERE ") . $monthCond);
$totalValue = (float)dash_scalar("SELECT COALESCE(SUM(market_value),0) FROM bankvaluations" . $bankWhere);

// Recent valuations (bank) — last 30 days.
$recent = [];
try {
    $rw = ($bankWhere ? $bankWhere . " AND" : " WHERE") . " created_at >= (NOW() - INTERVAL 30 DAY)";
    $st = db()->query("SELECT id, reg_no, make, customer_name, market_value, created_at
                       FROM bankvaluations" . $rw . " ORDER BY id DESC LIMIT 200");
    $recent = $st->fetchAll();
} catch (Throwable $e) {}

// Top clients by count.
$topClients = [];
try {
    $st = db()->query("SELECT client, COUNT(*) c FROM bankvaluations" . $bankWhere .
        ($bankWhere ? " AND" : " WHERE") . " client IS NOT NULL GROUP BY client ORDER BY c DESC LIMIT 5");
    $topClients = $st->fetchAll();
} catch (Throwable $e) {}

$online = [];
try {
    $rows = db()->query("SELECT user_id, name, activity,
                TIMESTAMPDIFF(MINUTE, login_at, NOW()) AS mins_online
              FROM user_activity WHERE last_seen > (NOW() - INTERVAL 5 MINUTE) ORDER BY last_seen DESC")->fetchAll();
    $seen = []; // one line per user
    foreach ($rows as $r) { if (isset($seen[$r['user_id']])) continue; $seen[$r['user_id']] = 1; $online[] = $r; }
} catch (Throwable $e) {}

$cur = setting('currency', CURRENCY);
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$me = current_user();

layout_header('Dashboard', 'dashboard');
?>
<div class="hero">
  <div class="hero-text">
    <div class="hero-greet"><?= e($greet) ?>, <?= e($me['name'] ?? '') ?> 👋</div>
    <div class="hero-sub"><?= date('l, j F Y') ?> · Here's your valuation overview.</div>
  </div>
  <?php if (can_edit()): ?>
  <div class="hero-actions">
    <a class="btn" href="<?= url('bank_form.php') ?>"><i data-lucide="plus"></i>New Bank Valuation</a>
    <a class="btn blue" href="<?= url('insurance_form.php') ?>"><i data-lucide="plus"></i>Insurance</a>
  </div>
  <?php endif; ?>
</div>
<?php if (is_admin()): ?>
<div class="kpis">
  <div class="kpi"><div class="kpi-ic ic-blue"><i data-lucide="landmark"></i></div><div><div class="kpi-label">Bank Valuations</div><div class="kpi-num" data-count="<?= (int)$bankTotal ?>">0</div></div></div>
  <div class="kpi"><div class="kpi-ic ic-green"><i data-lucide="shield-check"></i></div><div><div class="kpi-label">Insurance Valuations</div><div class="kpi-num" data-count="<?= (int)$insTotal ?>">0</div></div></div>
  <div class="kpi"><div class="kpi-ic ic-amber"><i data-lucide="calendar-plus"></i></div><div><div class="kpi-label">New This Month</div><div class="kpi-num" data-count="<?= (int)$bankMonth ?>">0</div></div></div>
  <div class="kpi"><div class="kpi-ic ic-red"><i data-lucide="coins"></i></div><div><div class="kpi-label">Total Value (<?= e($cur) ?>)</div><div class="kpi-num sm" data-count="<?= (int)$totalValue ?>">0</div></div></div>
</div>
<?php endif; ?>

<div class="dash-grid">
  <div class="panel hide-mobile">
    <div class="panel-h">Recent Bank Valuations <span class="muted" style="font-weight:400;font-size:12px">(last 30 days)</span> <a href="<?= url('bank_list.php') ?>" class="lnk">View all →</a></div>
    <input type="search" id="recentSearch" placeholder="Quick search these…" autocomplete="off"
           style="width:100%;background:var(--input);border:1px solid var(--line);color:var(--txt);padding:8px 10px;border-radius:7px;font-size:13px;margin-bottom:12px">
    <table class="list" id="recentTable">
      <thead><tr><th>Reg No.</th><th>Make/Model</th><th>Customer</th><th>Value</th><th></th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="5" class="muted">No valuations in the last 30 days.</td></tr>
      <?php else: foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['reg_no']) ?></td>
          <td><?= e($r['make']) ?></td>
          <td><?= e($r['customer_name']) ?></td>
          <td><?= number_format((float)$r['market_value']) ?></td>
          <td class="actions"><a class="rbtn" href="#" onclick="openPreview(<?= (int)$r['id'] ?>);return false;">Preview</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <div id="recentPager" class="recent-pager"></div>
  </div>

  <div class="rightcol">
    <?php if (is_admin()): ?>
    <div class="panel">
      <div class="panel-h">Top Clients</div>
      <table class="list">
        <thead><tr><th>Client</th><th>Valuations</th></tr></thead>
        <tbody>
        <?php if (!$topClients): ?><tr><td colspan="2" class="muted">No data.</td></tr>
        <?php else: foreach ($topClients as $c): ?>
          <tr><td><?= e(lookup_name('clients', $c['client'])) ?: '<span class="muted">—</span>' ?></td><td><?= (int)$c['c'] ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      <?php if (can_edit()): ?>
      <div class="quick">
        <a class="btn" href="<?= url('bank_form.php') ?>">+ New Bank Valuation</a>
        <a class="btn blue" href="<?= url('insurance_form.php') ?>">+ New Insurance Valuation</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="panel online-widget">
      <div class="ow-h"><i data-lucide="radio"></i> Currently Online</div>
      <div id="owBody">
      <?php if (!$online): ?>
        <div class="ow-empty">No one else online.</div>
      <?php else: foreach ($online as $o):
          $m = max(0, (int)$o['mins_online']); $dur = $m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : $m . 'm';
          $act = stripos((string)$o['activity'], 'valuation') !== false ? e($o['activity']) . ' · ' : '';
      ?>
        <div class="ow-row"><span class="ow-dot"></span><span class="ow-name"><?= e($o['name']) ?></span><span class="ow-meta"><?= $act ?><?= e($dur) ?></span></div>
      <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
  .hero{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;padding:24px 26px;border-radius:18px;margin-bottom:20px;border:1px solid var(--line);position:relative;overflow:hidden;
        background:linear-gradient(120deg, rgba(212,29,29,.22), rgba(37,99,235,.16) 55%, transparent 80%), var(--panel)}
  .hero::after{content:"";position:absolute;right:-40px;top:-40px;width:180px;height:180px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.10),transparent 70%);pointer-events:none}
  .hero-greet{font-size:23px;font-weight:800;letter-spacing:-.02em}
  .hero-sub{color:var(--mut);font-size:13px;margin-top:5px}
  .hero-actions{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:22px}
  .kpi{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;display:flex;align-items:center;gap:14px}
  .kpi-ic{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex:0 0 48px}
  .kpi-ic i{width:24px;height:24px}
  .ic-blue{background:rgba(37,99,235,.15);color:#5b9bff}.ic-green{background:rgba(28,122,71,.18);color:#3ddc84}
  .ic-amber{background:rgba(122,92,28,.2);color:#f5d79a}.ic-red{background:rgba(212,29,29,.16);color:#ff6b6b}
  .kpi-label{color:var(--mut);font-size:13px;margin-bottom:4px}
  .kpi-num{font-size:28px;font-weight:800;letter-spacing:-.02em} .kpi-num.sm{font-size:20px}
  .dash-grid{display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start}
  @media(max-width:900px){.dash-grid{grid-template-columns:1fr}}
  .rightcol{display:flex;flex-direction:column;gap:18px}
  .online-widget{padding:14px 16px}
  .ow-h{font-size:14px;font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:6px}
  .ow-h i{width:15px;height:15px;color:#3ddc84}
  .ow-row{display:flex;align-items:center;gap:7px;font-size:12px;padding:5px 0;border-top:1px solid var(--line)}
  .ow-row:first-of-type{border-top:0}
  .ow-dot{width:7px;height:7px;border-radius:50%;background:#3ddc84;flex:0 0 7px}
  .ow-name{font-weight:600;white-space:nowrap}
  .ow-meta{color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .ow-empty{font-size:12px;color:var(--mut)}
  @media(max-width:860px){.hide-mobile{display:none!important}}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:16px}
  .panel-h{display:flex;justify-content:space-between;align-items:center;font-weight:600;margin-bottom:12px}
  .panel-h .lnk{font-size:13px;color:var(--accent2)}
  .quick{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
</style>
<?php preview_modal(); ?>
<script>
(function(){
  var PER = 10;
  var s = document.getElementById('recentSearch');
  var tb = document.querySelector('#recentTable tbody');
  var pager = document.getElementById('recentPager');
  if(!tb || !pager) return;
  var all = Array.prototype.slice.call(tb.querySelectorAll('tr')).filter(function(tr){ return !tr.querySelector('td.muted'); });
  var page = 1;
  function render(){
    var q = (s ? s.value : '').toLowerCase();
    var matched = all.filter(function(tr){ return tr.textContent.toLowerCase().indexOf(q) >= 0; });
    var pages = Math.max(1, Math.ceil(matched.length / PER));
    if (page > pages) page = pages;
    all.forEach(function(tr){ tr.style.display = 'none'; });
    matched.slice((page-1)*PER, page*PER).forEach(function(tr){ tr.style.display = ''; });
    pager.innerHTML = '';
    if (pages > 1){
      var mk = function(label, p, dis, cur){
        var b = document.createElement('button'); b.textContent = label; b.className = 'pgb'+(cur?' cur':'');
        if(dis) b.disabled = true; else b.onclick = function(){ page = p; render(); };
        pager.appendChild(b);
      };
      mk('‹', page-1, page===1, false);
      for (var i=1;i<=pages;i++) mk(i, i, false, i===page);
      mk('›', page+1, page===pages, false);
    }
  }
  if (s) s.addEventListener('input', function(){ page = 1; render(); });
  render();
})();
// Auto-refresh the Currently Online widget every 30s
(function(){
  var body=document.getElementById('owBody'); if(!body) return;
  setInterval(function(){
    fetch('<?= url('online.php') ?>').then(function(r){return r.text();}).then(function(h){ body.innerHTML=h; }).catch(function(){});
  }, 30000);
})();
// Animated count-up for KPI numbers
document.querySelectorAll('.kpi-num[data-count]').forEach(function(el){
  var target = +el.dataset.count || 0, dur = 900, t0 = null;
  function step(ts){ if(!t0) t0 = ts; var p = Math.min((ts - t0)/dur, 1);
    el.textContent = Math.floor(p*target).toLocaleString(); if(p<1) requestAnimationFrame(step); }
  requestAnimationFrame(step);
});
</script>
<style>
  .recent-pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:12px}
  .recent-pager .pgb{background:var(--panel);border:1px solid var(--line);color:var(--txt);padding:5px 10px;border-radius:6px;font-size:12px;cursor:pointer}
  .recent-pager .pgb:hover:not(:disabled){background:var(--hover)}
  .recent-pager .pgb.cur{background:var(--accent);border-color:var(--accent);color:#fff}
  .recent-pager .pgb:disabled{opacity:.4;cursor:default}
</style>
<?php layout_footer();
