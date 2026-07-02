<?php
require_once __DIR__ . '/lib.php';

/** Minimal, branded shell for the client portal (no staff nav). */
function portal_header(string $title, string $nav = ''): void {
    $c = current_client();
    $company = lookup_name('clients', $c['client_id'] ?? null);
    $fl = flash();
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Client Portal</title>
<link rel="icon" type="image/png" href="<?= url('assets/logo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0f1216;--panel:#171c22;--line:#262d36;--txt:#e6e9ee;--mut:#9aa4b2;--accent:#d41d1d;--accent2:#2563eb;--input:#0f1419;--chip:#1b212a;--th:#1b212a;--rowh:#1a2028;--hover:#2b3340}
  *{box-sizing:border-box}
  body{margin:0;font-family:'Plus Jakarta Sans',system-ui,Segoe UI,Roboto,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:var(--txt);
       background:radial-gradient(1100px 560px at 100% -8%,rgba(212,29,29,.12),transparent 55%),radial-gradient(900px 520px at -8% 108%,rgba(37,99,235,.10),transparent 55%),var(--bg);min-height:100vh}
  a{color:inherit;text-decoration:none}
  .pbar{display:flex;justify-content:space-between;align-items:center;padding:14px 26px;background:var(--panel);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:30}
  .pbar .brand{display:flex;align-items:center;gap:12px}
  .pbar .brand img{height:38px;background:#fff;padding:5px 8px;border-radius:8px}
  .pbar .brand .co{font-size:13px;color:var(--mut)} .pbar .brand .co b{color:var(--txt);display:block;font-size:14px}
  .pbar .out{display:inline-flex;align-items:center;gap:7px;background:var(--chip);color:var(--txt);padding:8px 14px;border-radius:8px;font-size:13px;transition:background .15s}
  .pbar .out:hover{background:var(--hover)} .pbar .out i{width:16px;height:16px;color:#ff6b6b}
  .pbar-right{display:flex;align-items:center;gap:12px}
  .notif-wrap{position:relative;display:inline-block}
  .notif-bell{background:var(--chip);color:var(--txt);border:0;border-radius:6px;width:32px;height:30px;cursor:pointer;position:relative}
  .notif-bell i{width:16px;height:16px;color:#f5b14a}
  .notif-badge{position:absolute;top:-5px;right:-5px;background:var(--accent);color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;line-height:16px;border-radius:8px;padding:0 4px;text-align:center}
  .notif-panel{display:none;position:absolute;right:0;top:calc(100% + 8px);width:330px;max-width:88vw;background:var(--panel);border:1px solid var(--line);border-radius:10px;box-shadow:0 16px 40px rgba(0,0,0,.4);z-index:200;overflow:hidden}
  .notif-panel.open{display:block}
  .notif-head{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid var(--line);font-weight:600;font-size:13px}
  .notif-markall{background:none;border:0;color:var(--accent2,#2563eb);font-size:12px;cursor:pointer}
  .notif-list{max-height:60vh;overflow:auto}
  .notif-item{display:block;padding:10px 12px;border-bottom:1px solid var(--line);color:var(--txt);text-decoration:none;font-size:13px}
  .notif-item:hover{background:var(--hover)}
  .notif-item.unread{background:rgba(37,99,235,.10);border-left:3px solid #5b9bff}
  .notif-title{font-weight:600}.notif-body{color:var(--mut);font-size:12px;margin-top:2px}.notif-time{color:var(--mut);font-size:11px;margin-top:3px}
  .notif-empty{padding:18px 12px;color:var(--mut);font-size:13px;text-align:center}
  .pbar .whoami{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--txt)}
  .pbar .whoami i{width:16px;height:16px;color:#5b9bff}
  @media(max-width:860px){.pbar .whoami span{display:none}}
  .wrap{max-width:1200px;margin:0 auto;padding:24px;animation:fadeUp .45s ease}
  @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  h1.pt{font-size:22px;font-weight:800;letter-spacing:-.02em;margin:0 0 4px}
  .psub{color:var(--mut);font-size:13px;margin:0 0 20px}
  .flash{background:#0f3d24;border:1px solid #1c7a47;color:#b8f5d0;padding:10px 14px;border-radius:8px;margin-bottom:16px}
  .flash.err{background:#3d0f0f;border-color:#7a1c1c;color:#f5c0c0}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:16px;box-shadow:0 10px 28px rgba(0,0,0,.07)}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:22px}
  .kpi{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden}
  .kpi::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,var(--accent),#ff8a5c)}
  .kpi-ic{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex:0 0 46px;background:rgba(37,99,235,.15);color:#5b9bff}.kpi-ic.g{background:rgba(28,122,71,.18);color:#3ddc84}.kpi-ic.a{background:rgba(245,177,74,.16);color:#f5b14a}
  .kpi-ic i{width:22px;height:22px}.kpi-label{color:var(--mut);font-size:13px}.kpi-num{font-size:26px;font-weight:800}
  input[type=search]{width:100%;background:var(--input);border:1px solid var(--line);color:var(--txt);padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:14px}
  table.list{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:0 10px 28px rgba(0,0,0,.07)}
  table.list th,table.list td{padding:11px 13px;text-align:left;border-bottom:1px solid var(--line);font-size:13px}
  table.list th{background:linear-gradient(180deg,var(--th),var(--panel));color:var(--mut);font-weight:600}
  table.list tr:hover td{background:var(--rowh)}
  .tabs{display:flex;gap:6px;margin-bottom:14px}
  .tabs a{padding:9px 16px;border-radius:8px;font-size:13px;color:var(--mut);background:var(--chip)}
  .tabs a.on{background:var(--accent);color:#fff}
  .rbtn{display:inline-flex;align-items:center;gap:5px;border:1px solid var(--line);color:var(--txt);padding:5px 11px;border-radius:6px;font-size:12px;background:var(--chip);margin-right:6px;transition:transform .15s}
  .rbtn:hover{transform:translateY(-1px)} .rbtn i{width:14px;height:14px}
  .rbtn .lucide-eye{color:#22c55e}.rbtn .lucide-printer{color:#7c8896}
  .ppager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:14px}
  .ppager .pgb{background:var(--panel);border:1px solid var(--line);color:var(--txt);padding:6px 11px;border-radius:6px;font-size:12px;cursor:pointer}
  .ppager .pgb:hover:not(:disabled){background:var(--hover)} .ppager .pgb.cur{background:var(--accent);border-color:var(--accent);color:#fff} .ppager .pgb:disabled{opacity:.4;cursor:default}
  .muted{color:var(--mut)} .badge{display:inline-block;font-size:11px;padding:2px 7px;border-radius:10px}
  .b-red{background:#3d0f0f;color:#f5a3a3}.b-amber{background:#3d2f0f;color:#f5d79a}.b-green{background:#0f3d24;color:#b8f5d0}.b-grey{background:#2b3340;color:#cdd5e0}.b-blue{background:#0f2440;color:#a3c8f5}
  .pnav{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px}
  .pnav a{padding:9px 15px;border-radius:8px;font-size:13px;color:var(--mut);background:var(--chip);display:inline-flex;align-items:center;gap:7px}
  .pnav a i{width:15px;height:15px}
  .pnav a.on{background:var(--accent);color:#fff}
  .btn{display:inline-flex;align-items:center;gap:7px;background:var(--accent);color:#fff;border:0;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
  .btn.sec{background:var(--chip);color:var(--txt)} .btn i{width:15px;height:15px}
  .f{display:block;margin-bottom:12px} label.f{font-size:12px;color:var(--mut);margin-bottom:5px;display:block}
  .f input,.f select,.f textarea{width:100%;background:var(--input);border:1px solid var(--line);color:var(--txt);padding:10px 12px;border-radius:8px;font-size:13px;font-family:inherit}
  /* preview modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center}
  .modal-bg.open{display:flex}
  .modal{background:#fff;width:90%;max-width:900px;height:90vh;border-radius:10px;overflow:hidden;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;background:#171c22;color:#fff;padding:10px 16px}
  .modal-head a,.modal-head button{background:#d41d1d;color:#fff;border:0;padding:7px 14px;border-radius:6px;font-size:13px;cursor:pointer;margin-left:8px;text-decoration:none}
  .modal-head .close{background:#2b3340} .modal iframe{flex:1;width:100%;border:0;background:#fff}
  @media(max-width:860px){
    .pbar{padding:12px 14px} .pbar .brand img{height:32px}
    .wrap{padding:16px}
    .kpis{grid-template-columns:1fr 1fr}
    table.list{display:block;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch}
    .modal{width:96%;height:94vh}
  }
  #topbar{position:fixed;top:0;left:0;right:0;height:5px;z-index:6000;opacity:0;transition:opacity .25s;pointer-events:none}
  #topbar.on{opacity:1}
  #topbar .topbar-fill{height:100%;width:0;border-radius:0 4px 4px 0;background:linear-gradient(90deg,#2563eb,#7a4dd1,#d41d1d,#ff8a3c);background-size:300% 100%;animation:tbflow 2s linear infinite;box-shadow:0 0 14px rgba(212,29,29,.85),0 0 8px rgba(37,99,235,.8);transition:width .25s ease}
  @keyframes tbflow{to{background-position:300% 0}}
</style></head>
<body>
<div id="topbar"><div class="topbar-fill"></div></div>
<div class="pbar">
  <div class="brand">
    <img src="<?= url('assets/logo2.png') ?>" alt="Kennet">
    <div class="co">Client Portal<b><?= e($company ?: 'My Company') ?></b></div>
  </div>
  <div class="pbar-right">
    <div class="notif-wrap">
      <button type="button" id="notifBell" class="notif-bell" title="Notifications"><i data-lucide="bell"></i><span id="notifBadge" class="notif-badge" style="display:none">0</span></button>
      <div id="notifPanel" class="notif-panel">
        <div class="notif-head"><span>Notifications</span><button type="button" id="notifMarkAll" class="notif-markall">Mark all read</button></div>
        <div id="notifList" class="notif-list"></div>
      </div>
    </div>
    <span class="whoami"><i data-lucide="user-round"></i><span><?= e($c['name'] ?? $c['email'] ?? 'User') ?></span></span>
    <a class="out" href="<?= url('portal_logout.php') ?>"><i data-lucide="log-out"></i> Log out</a>
  </div>
</div>
<div class="wrap">
  <?php
  $navItems = [
      'valuations' => ['portal.php', 'folder', 'My Valuations'],
      'request'    => ['portal_request.php', 'plus-circle', 'Request Valuation'],
      'requests'   => ['portal_requests.php', 'list-checks', client_is_admin() ? 'Company Requests' : 'My Requests'],
  ];
  if (client_is_admin()) $navItems['team'] = ['portal_team.php', 'users', 'My Team'];
  ?>
  <nav class="pnav">
    <?php foreach ($navItems as $k => $it): ?>
      <a class="<?= $nav === $k ? 'on' : '' ?>" href="<?= url($it[0]) ?>"><i data-lucide="<?= $it[1] ?>"></i><?= e($it[2]) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($fl): ?><div class="flash <?= ($fl['type'] ?? 'ok') === 'err' ? 'err' : '' ?>"><?= e($fl['msg'] ?? '') ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['avail_notice_pending']) && setting('banner_enabled') === '1'): unset($_SESSION['avail_notice_pending']); ?>
    <div class="modal-bg open" id="availModal" style="z-index:5000">
      <div class="modal" style="max-width:440px;height:auto;background:var(--panel);color:var(--txt);border:1px solid var(--line)">
        <div style="padding:26px;text-align:center">
          <div style="font-size:34px">⚠️</div>
          <h3 style="margin:8px 0 10px">Please note</h3>
          <p style="color:var(--mut);font-size:14px;line-height:1.6"><?= e(availability_message()) ?></p>
          <button class="rbtn" type="button" style="background:var(--accent);color:#fff;border:0;padding:9px 18px" onclick="document.getElementById('availModal').remove()">I acknowledge</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php }

function portal_footer(): void { ?>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
if(window.lucide)lucide.createIcons();
(function(){
  document.querySelectorAll('table[data-paginate]').forEach(function(tbl){
    var per=parseInt(tbl.getAttribute('data-paginate'),10)||25;
    var body=tbl.tBodies[0]; if(!body) return;
    var rows=Array.prototype.slice.call(body.rows).filter(function(tr){return !tr.querySelector('td[colspan]');});
    if(rows.length<=per) return;
    var pager=document.createElement('div'); pager.className='ppager';
    tbl.parentNode.insertBefore(pager, tbl.nextSibling);
    var page=1;
    function render(){
      var pages=Math.max(1,Math.ceil(rows.length/per)); if(page>pages)page=pages;
      rows.forEach(function(tr,i){tr.style.display=(i>=(page-1)*per&&i<page*per)?'':'none';});
      pager.innerHTML='';
      if(pages<=1)return;
      var mk=function(label,p,dis,cur){var b=document.createElement('button');b.textContent=label;b.className='pgb'+(cur?' cur':'');if(dis)b.disabled=true;else b.onclick=function(){page=p;render();window.scrollTo(0,0);};pager.appendChild(b);};
      mk('‹',page-1,page===1,false);
      for(var i=1;i<=pages;i++){ if(pages>9&&Math.abs(i-page)>2&&i>1&&i<pages){ if(i===2||i===pages-1)mk('…',page,true,false); continue; } mk(i,i,false,i===page); }
      mk('›',page+1,page===pages,false);
    }
    render();
  });
})();
(function(){var L={'log-out':'Log out','eye':'View report','printer':'Download PDF','landmark':'Bank Valuations','shield-check':'Insurance Valuations','mail':'Email','lock':'Password','arrow-right':'Sign in'};
 document.querySelectorAll('.lucide').forEach(function(svg){var n='';svg.classList.forEach(function(c){if(c.indexOf('lucide-')===0)n=c.slice(7);});var el=svg.closest('a,button');if(!el||el.getAttribute('title'))return;var t=(el.textContent||'').trim();el.setAttribute('title',t||L[n]||n.replace(/-/g,' '));});})();
(function(){
  var TB=document.getElementById('topbar'),TBF=TB?TB.querySelector('.topbar-fill'):null,t,v=0;
  function start(){if(!TB)return;TB.classList.add('on');v=10;TBF.style.width='10%';clearInterval(t);t=setInterval(function(){v+=Math.max(.4,(92-v)*.07);if(v>92)v=92;TBF.style.width=v+'%';},180);}
  function done(){if(!TB)return;clearInterval(t);TBF.style.width='100%';setTimeout(function(){TB.classList.remove('on');TBF.style.width='0';},400);}
  document.querySelectorAll('a[href]').forEach(function(a){var h=a.getAttribute('href')||'';if(a.target==='_blank'||h.charAt(0)==='#'||h.indexOf('javascript:')===0||a.onclick)return;a.addEventListener('click',function(e){if(e.metaKey||e.ctrlKey)return;start();});});
  document.querySelectorAll('a[href*="portal_pdf.php"]').forEach(function(a){a.addEventListener('click',function(){start();setTimeout(done,5000);});});
  window.addEventListener('pageshow',done); window.addEventListener('beforeunload',start);
})();
</script>
<script>window.NOTIF_CFG={url:'<?= url('notifications.php') ?>',csrf:'<?= e(csrf_token()) ?>'};</script>
<script src="<?= url('notif.js') ?>"></script>
</body></html>
<?php }
