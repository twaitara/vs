<?php
require_once __DIR__ . '/lib.php';

/** Render the page shell opening: <head>, sidebar, top bar, and open <main>. */
function layout_header(string $title, string $active = ''): void {
    $u = current_user();
    $nav = [
        'dashboard' => ['label' => 'Dashboard',            'href' => 'dashboard.php'],
        'bank'      => ['label' => 'Bank Valuations',      'href' => 'bank_list.php'],
        'insurance' => ['label' => 'Insurance Valuations', 'href' => 'insurance_list.php'],
    ];
    if (is_admin()) $nav['analytics'] = ['label' => 'Analytics', 'href' => 'analytics.php'];
    // Everything else lives under the Settings hub.
    if (can_edit()) $nav['settings'] = ['label' => 'Settings', 'href' => is_admin() ? 'settings.php' : 'clients.php'];
    $fl = flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/logo.png') ?>">
<link rel="apple-touch-icon" href="<?= url('assets/logo.png') ?>">
<script>(function(){try{var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0f1216;--panel:#171c22;--line:#262d36;--txt:#e6e9ee;--mut:#9aa4b2;--accent:#d41d1d;--accent2:#2563eb;}
  html[data-theme="light"]{--bg:#eef1f5;--panel:#ffffff;--line:#dce1e8;--txt:#1a2330;--mut:#5b6573;--accent:#d41d1d;--accent2:#2563eb;}
  *{box-sizing:border-box}
  body{margin:0;font-family:'Plus Jakarta Sans',system-ui,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--txt);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
  h1,h2,h3,.brand{font-weight:700;letter-spacing:-0.015em}
  a{color:inherit;text-decoration:none}
  .wrap{display:flex;min-height:100vh}
  .side{width:230px;background:var(--panel);border-right:1px solid var(--line);padding:18px 0;position:sticky;top:0;height:100vh}
  .brand{padding:0 20px 16px;border-bottom:1px solid var(--line);margin-bottom:10px}
  .brand-logo{max-width:180px;width:100%;height:auto;display:block;background:#fff;padding:8px;border-radius:8px}
  .brand small{display:block;color:var(--mut);font-weight:400;font-size:11px;margin-top:8px}
  .nav a{display:block;padding:11px 20px;color:var(--mut);font-size:14px;border-left:3px solid transparent}
  .nav a:hover{background:#1e242c;color:var(--txt)}
  .nav a.on{color:#fff;border-left-color:var(--accent);background:#1e242c}
  .main{flex:1;display:flex;flex-direction:column;min-width:0}
  .top{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;border-bottom:1px solid var(--line);background:var(--panel)}
  .top h1{font-size:17px;margin:0}
  .who{color:var(--mut);font-size:13px}
  .content{padding:24px;max-width:none;width:100%}
  .flash{background:#0f3d24;border:1px solid #1c7a47;color:#b8f5d0;padding:10px 14px;border-radius:8px;margin-bottom:16px}
  .flash-err{background:#3d0f0f;border-color:#7a1c1c;color:#f5c0c0}
  #toasts{position:fixed;top:16px;right:16px;z-index:3000;display:flex;flex-direction:column;gap:8px}
  .toast{background:#171c22;border:1px solid #1c7a47;color:#b8f5d0;padding:11px 16px;border-radius:8px;font-size:13px;box-shadow:0 6px 24px rgba(0,0,0,.35);max-width:320px;animation:tin .25s ease}
  .toast.err{border-color:#7a1c1c;color:#f5c0c0}
  @keyframes tin{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
  #spinner{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:4000;align-items:center;justify-content:center}
  #spinner.on{display:flex}
  #spinner .sp{width:42px;height:42px;border:4px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  .themebtn{background:#2b3340;color:#cdd5e0;border:0;border-radius:6px;width:28px;height:24px;cursor:pointer;font-size:13px;margin-right:6px}
  .who .role{background:#2b3340;color:#cdd5e0;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:4px}
  .subnav{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid var(--line);margin-bottom:20px}
  .subnav a{padding:9px 14px;color:var(--mut);font-size:13px;border-bottom:2px solid transparent}
  .subnav a:hover{color:var(--txt)}
  .subnav a.on{color:#fff;border-bottom-color:var(--accent)}
  .btn{display:inline-block;background:var(--accent);color:#fff;border:0;padding:9px 16px;border-radius:8px;font-size:14px;cursor:pointer}
  .btn:hover{filter:brightness(1.1)} .btn.sec{background:#2b3340} .btn.blue{background:var(--accent2)}
  table.list{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--line);border-radius:10px;overflow:hidden}
  table.list th,table.list td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--line);font-size:13px}
  table.list th{background:#1b212a;color:var(--mut);font-weight:600}
  table.list tr:hover td{background:#1a2028}
  .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px}
  .toolbar input[type=search]{background:var(--panel);border:1px solid var(--line);color:var(--txt);padding:9px 12px;border-radius:8px;width:280px}
  form.card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}
  fieldset{border:1px solid var(--line);border-radius:10px;padding:16px 18px;margin:0 0 18px}
  legend{color:var(--accent);font-weight:600;padding:0 8px;font-size:14px}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
  label.f{display:block;font-size:12px;color:var(--mut);margin-bottom:5px}
  .f input,.f select,.f textarea{width:100%;background:#0f1419;border:1px solid var(--line);color:var(--txt);padding:8px 10px;border-radius:7px;font-size:14px;font-family:inherit}
  .f textarea{min-height:70px;resize:vertical}
  .yn{display:flex;gap:14px;font-size:13px;align-items:center}
  .yn label{display:flex;gap:5px;align-items:center;color:var(--txt)}
  .actions a{margin-right:8px;font-size:13px;color:var(--accent2)}
  .actions a.rbtn{display:inline-block;border:1px solid var(--line);color:var(--txt);padding:4px 12px;border-radius:6px;background:#1b212a}
  .actions a.rbtn:hover{background:#2b3340}
  .pager{display:flex;gap:6px;justify-content:center;align-items:center;margin-top:18px;flex-wrap:wrap}
  .pager a,.pager span{padding:6px 11px;border:1px solid var(--line);border-radius:6px;font-size:13px;background:var(--panel);color:var(--txt)}
  .pager a:hover{background:#2b3340}
  .pager .cur{background:var(--accent);border-color:var(--accent);color:#fff}
  .pager .dis,.pager .gap{color:var(--mut);border-color:transparent;background:transparent}
  .count{color:var(--mut);font-size:12px;margin-top:10px;text-align:center}
  .sortlink{color:var(--mut)} .sortlink:hover{color:var(--txt)}
  .filterbar{display:none;background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:16px}
  .filterbar.open{display:block}
  .fb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
  .fb-grid label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--mut)}
  .fb-grid input,.fb-grid select{background:#0f1419;border:1px solid var(--line);color:var(--txt);padding:7px 9px;border-radius:7px;font-size:13px}
  .fb-actions{margin-top:12px;display:flex;gap:8px}
  .bulkbar{display:flex;align-items:center;gap:14px;margin-bottom:10px;font-size:13px;color:var(--mut)}
  .badge{display:inline-block;font-size:11px;padding:2px 7px;border-radius:10px;white-space:nowrap}
  .b-red{background:#3d0f0f;color:#f5a3a3;border:1px solid #7a1c1c}
  .b-amber{background:#3d2f0f;color:#f5d79a;border:1px solid #7a5c1c}
  .b-grey{background:#2b3340;color:#cdd5e0}
  .b-green{background:#0f3d24;color:#b8f5d0;border:1px solid #1c7a47}
  .listfoot{display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px}
  .listfoot .pp{color:var(--mut);font-size:12px}
  .listfoot .pp select{background:var(--panel);border:1px solid var(--line);color:var(--txt);padding:5px 8px;border-radius:6px;margin-left:6px}
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center}
  .modal-bg.open{display:flex}
  .modal{background:#fff;width:90%;max-width:900px;height:90vh;border-radius:10px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 10px 40px rgba(0,0,0,.5)}
  .modal-head{display:flex;justify-content:space-between;align-items:center;background:#171c22;color:#fff;padding:10px 16px}
  .modal-head .title{font-size:14px;font-weight:600}
  .modal-head .acts a,.modal-head .acts button{background:#d41d1d;color:#fff;border:0;padding:7px 14px;border-radius:6px;font-size:13px;text-decoration:none;cursor:pointer;margin-left:8px}
  .modal-head .acts .close{background:#2b3340}
  .modal iframe{flex:1;width:100%;border:0;background:#fff}
  .muted{color:var(--mut)}
</style>
</head>
<body>
<div class="wrap">
  <aside class="side">
    <div class="brand">
      <a href="<?= url('dashboard.php') ?>"><img src="<?= url('assets/logo2.png') ?>" alt="<?= e(APP_NAME) ?>" class="brand-logo"></a>
      <small>Automobile Valuers</small>
    </div>
    <nav class="nav">
      <?php foreach ($nav as $k => $item): ?>
        <a href="<?= url($item['href']) ?>" class="<?= $active===$k?'on':'' ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="main">
    <div class="top">
      <h1><?= e($title) ?></h1>
      <div class="who">
        <button type="button" id="themeBtn" class="themebtn" title="Toggle light/dark">◐</button>
        <a href="<?= url('profile.php') ?>" class="muted"><?= e($u['name'] ?? '') ?></a>
        <span class="role"><?= e(ucfirst($u['role'] ?? '')) ?></span>
        · <a href="<?= url('logout.php') ?>" class="muted">Log out</a>
      </div>
    </div>
    <div id="toasts"></div>
    <div id="spinner"><div class="sp"></div></div>
    <div class="content">
      <?php if ($fl): ?><script>window.__flash = <?= json_encode($fl) ?>;</script><?php endif; ?>
<?php }

/** Sub-navigation for the Settings hub. $active = section key. */
function settings_nav(string $active): void {
    $tabs = [];
    if (is_admin()) $tabs['general'] = ['Company', 'settings.php'];
    $tabs['clients']  = ['Clients', 'clients.php'];
    $tabs['insurers'] = ['Insurers', 'insurers.php'];
    $tabs['types']    = ['Valuation Types', 'types.php'];
    if (is_admin()) { $tabs['users'] = ['Users', 'users.php']; $tabs['audit'] = ['Audit Log', 'audit.php']; }
    $tabs['recycle']  = ['Recycle Bin', 'recycle.php'];
    echo '<div class="subnav">';
    foreach ($tabs as $k => $t) {
        echo '<a class="' . ($k === $active ? 'on' : '') . '" href="' . url($t[1]) . '">' . e($t[0]) . '</a>';
    }
    echo '</div>';
}

/**
 * Render a pagination bar. $base is the page filename; $params are extra query
 * params to preserve (e.g. ['q' => 'abc']).
 */
function pagination_bar(int $page, int $pages, string $base, array $params = []): void {
    if ($pages <= 1) return;
    $mk = function (int $p) use ($base, $params) {
        $params['page'] = $p;
        return url($base . '?' . http_build_query($params));
    };
    $win = 2; // pages shown on each side of current
    echo '<div class="pager">';
    echo $page > 1
        ? '<a href="' . $mk($page - 1) . '">‹ Prev</a>'
        : '<span class="dis">‹ Prev</span>';
    $start = max(1, $page - $win);
    $end   = min($pages, $page + $win);
    if ($start > 1) { echo '<a href="' . $mk(1) . '">1</a>'; if ($start > 2) echo '<span class="gap">…</span>'; }
    for ($p = $start; $p <= $end; $p++) {
        echo $p === $page
            ? '<span class="cur">' . $p . '</span>'
            : '<a href="' . $mk($p) . '">' . $p . '</a>';
    }
    if ($end < $pages) { if ($end < $pages - 1) echo '<span class="gap">…</span>'; echo '<a href="' . $mk($pages) . '">' . $pages . '</a>'; }
    echo $page < $pages
        ? '<a href="' . $mk($page + 1) . '">Next ›</a>'
        : '<span class="dis">Next ›</span>';
    echo '</div>';
}

/**
 * Live "quick search": auto-submits the search form a moment after typing stops,
 * and restores the cursor to the box after the page reloads.
 */
function quick_search_script(): void { ?>
<script>
(function(){
  var input = document.getElementById('quickSearch');
  var form  = document.getElementById('searchForm');
  if(!input || !form) return;
  var t;
  input.addEventListener('input', function(){
    clearTimeout(t);
    t = setTimeout(function(){ form.submit(); }, 350);
  });
  // keep focus + caret at end after reload
  input.focus();
  var v = input.value; input.value = ''; input.value = v;
})();
</script>
<?php }

/**
 * Reusable preview popup. Call once per page that has Preview buttons, then use
 * onclick="openPreview(ID)" on links/buttons.
 */
function preview_modal(): void { ?>
<div class="modal-bg" id="previewModal" onclick="if(event.target===this)closePreview()">
  <div class="modal">
    <div class="modal-head">
      <span class="title">Valuation Report Preview</span>
      <span class="acts">
        <a id="previewPdf" href="#" target="_blank">⬇ Download PDF</a>
        <?php if (can_edit()): ?><button id="previewEmail" type="button">✉ Email</button><?php endif; ?>
        <button class="close" onclick="closePreview()">✕ Close</button>
      </span>
    </div>
    <iframe id="previewFrame" src="about:blank"></iframe>
  </div>
</div>
<script>
function openPreview(id, type){
  type = type || 'bank';
  document.getElementById('previewFrame').src = '<?= url('preview.php') ?>?bare=1&type=' + type + '&id=' + id;
  document.getElementById('previewPdf').href = '<?= url('print.php') ?>?type=' + type + '&id=' + id;
  var em = document.getElementById('previewEmail'); if(em) em.onclick = function(){ emailReport(id, type); };
  document.getElementById('previewModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closePreview(){
  document.getElementById('previewModal').classList.remove('open');
  document.getElementById('previewFrame').src = 'about:blank';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closePreview(); });
function emailReport(id, type){
  var to = prompt('Email this report (PDF attachment) to:');
  if(!to) return;
  var fd = new FormData(); fd.append('id', id); fd.append('type', type||'bank'); fd.append('to', to); fd.append('_csrf','<?= e(csrf_token()) ?>');
  fetch('<?= url('send_report.php') ?>', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){ toast(d && d.ok ? ('Report emailed to '+to) : ('Email failed: '+((d&&d.error)||'unknown')), d&&d.ok?'ok':'err'); })
    .catch(function(){ toast('Network error sending email','err'); });
}
</script>
<?php }

/**
 * Form enhancements (progressive — the plain form still works if JS fails):
 * tabbed wizard, live money formatting + value-in-words, auto-uppercase,
 * auto-save draft, image dropzone/thumbnails, and inline quick-add for lookups.
 */
function form_assets(): void { ?>
<style>
  .wizard.tabbed > fieldset{display:none}
  .wizard.tabbed > fieldset.active{display:block}
  .tabs{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--line)}
  .tabbtn{background:transparent;border:0;border-bottom:2px solid transparent;color:var(--mut);padding:9px 14px;cursor:pointer;font-size:13px}
  .tabbtn.active{color:#fff;border-bottom-color:var(--accent)}
  .tabbtn.has-invalid{color:#f5a3a3}
  .wizard-nav{display:flex;justify-content:space-between;margin:10px 0 0}
  .f.has-err input,.f.has-err select,.f.has-err textarea{border-color:#d41d1d}
  .field-err{color:#f5a3a3;font-size:11px;margin-top:3px;display:block}
  .words-preview{display:block;margin-top:4px;font-style:italic}
  .draft-bar{background:#3d2f0f;border:1px solid #7a5c1c;color:#f5d79a;padding:8px 12px;border-radius:8px;margin-bottom:14px;font-size:13px}
  .draft-bar a{color:#fff;text-decoration:underline;cursor:pointer;margin-left:8px}
  .quick-add{margin-left:8px;font-size:12px;color:var(--accent2);cursor:pointer}
  .dropzone{border:2px dashed var(--line);border-radius:10px;padding:16px;text-align:center;color:var(--mut);cursor:pointer;font-size:13px}
  .dropzone.drag{border-color:var(--accent);color:var(--txt)}
  .thumbs{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
  .thumb{position:relative;width:92px;height:70px;border:1px solid var(--line);border-radius:6px;overflow:hidden;background:#0f1419}
  .thumb img{width:100%;height:100%;object-fit:cover}
  .thumb .rm{position:absolute;top:2px;right:2px;background:rgba(0,0,0,.65);color:#fff;border:0;border-radius:4px;cursor:pointer;font-size:11px;padding:0 5px;line-height:16px}
  .thumb.removing{opacity:.35}
</style>
<script>
(function(){
  var CSRF = '<?= e(csrf_token()) ?>';
  var QADD = '<?= url('quick_add.php') ?>';

  // ---------- value in words (preview only) ----------
  function words(n){
    n=Math.floor(Math.abs(+n||0));
    if(n===0) return 'Zero Shillings';
    var o=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    var t=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    var sc=['','Thousand','Million','Billion','Trillion'];
    function g(x){var s='';if(x>=100){s+=o[Math.floor(x/100)]+' hundred ';x%=100;}if(x>=20){s+=t[Math.floor(x/10)]+' ';x%=10;}if(x>0)s+=o[x]+' ';return s.trim();}
    var parts=[],i=0;while(n>0){var c=n%1000;if(c)parts.unshift(g(c)+(sc[i]?' '+sc[i]:''));n=Math.floor(n/1000);i++;}
    return parts.join(' ').trim()+' Shillings';
  }
  function fmt(v){v=(''+v).replace(/,/g,'');var p=v.split('.');p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g,',');return p.join('.');}

  // ---------- money fields ----------
  document.querySelectorAll('input.money').forEach(function(inp){
    function run(){
      var caretEnd = inp.selectionStart===inp.value.length;
      inp.value = fmt(inp.value);
      if(caretEnd) inp.selectionStart=inp.selectionEnd=inp.value.length;
      if(inp.dataset.words){
        var pv=inp.parentNode.querySelector('.words-preview');
        if(pv) pv.textContent = inp.value ? words(inp.value.replace(/,/g,'')) : '';
      }
    }
    inp.addEventListener('input',run); run();
  });
  // strip commas before submit
  document.querySelectorAll('form').forEach(function(f){
    f.addEventListener('submit',function(){ f.querySelectorAll('input.money').forEach(function(m){ m.value=m.value.replace(/,/g,''); }); });
  });

  // ---------- auto-uppercase ----------
  ['reg_no','chasis_no'].forEach(function(nm){
    document.querySelectorAll('input[name="'+nm+'"]').forEach(function(i){
      i.addEventListener('input',function(){var p=i.selectionStart;i.value=i.value.toUpperCase();i.selectionStart=i.selectionEnd=p;});
    });
  });

  // ---------- wizard tabs ----------
  document.querySelectorAll('form.wizard').forEach(function(form){
    var fs=Array.prototype.slice.call(form.children).filter(function(c){return c.tagName==='FIELDSET';});
    if(fs.length<2) return;
    var tabs=document.createElement('div'); tabs.className='tabs';
    fs.forEach(function(f,i){
      var lg=f.querySelector('legend'); var name=lg?lg.textContent:('Step '+(i+1));
      var b=document.createElement('button'); b.type='button'; b.className='tabbtn'+(i===0?' active':''); b.textContent=name;
      b.onclick=function(){show(i);}; tabs.appendChild(b);
    });
    form.insertBefore(tabs, fs[0]);
    var nav=document.createElement('div'); nav.className='wizard-nav';
    var prev=document.createElement('button'); prev.type='button'; prev.className='btn sec'; prev.textContent='← Back';
    var next=document.createElement('button'); next.type='button'; next.className='btn'; next.textContent='Next →';
    nav.appendChild(prev); nav.appendChild(next); fs[fs.length-1].after(nav);
    var cur=0;
    function show(i){cur=i;fs.forEach(function(f,j){f.classList.toggle('active',j===i);});
      tabs.querySelectorAll('.tabbtn').forEach(function(b,j){b.classList.toggle('active',j===i);});
      prev.style.visibility=i===0?'hidden':'visible'; next.style.visibility=i===fs.length-1?'hidden':'visible';
      window.scrollTo(0,0);}
    prev.onclick=function(){show(Math.max(0,cur-1));};
    next.onclick=function(){show(Math.min(fs.length-1,cur+1));};
    form.classList.add('tabbed'); show(0);
    // Reveal the tab holding the first invalid field (fires even when hidden).
    var revealing=false;
    form.addEventListener('invalid',function(e){
      var fsEl=e.target.closest('fieldset'); if(!fsEl) return;
      var idx=fs.indexOf(fsEl); if(idx<0) return;
      if(!revealing){ revealing=true; show(idx); setTimeout(function(){revealing=false;},0); }
    }, true);
  });

  // ---------- inline quick-add for lookups ----------
  document.querySelectorAll('.quick-add').forEach(function(el){
    el.addEventListener('click',function(){
      var sel=document.querySelector('select[name="'+el.dataset.target+'"]'); if(!sel) return;
      var name=prompt('Add new '+el.dataset.label+':'); if(!name) return;
      var fd=new FormData(); fd.append('type',el.dataset.type); fd.append('name',name); fd.append('_csrf',CSRF);
      fetch(QADD,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
        if(d&&d.ok){var o=document.createElement('option');o.value=d.id;o.textContent=d.name;o.selected=true;sel.appendChild(o);}
        else alert((d&&d.error)||'Could not add.');
      }).catch(function(){alert('Network error.');});
    });
  });

  // ---------- image dropzone + thumbnails ----------
  var fileInput=document.querySelector('input[type=file][name="images[]"]');
  if(fileInput){
    var dz=document.createElement('div'); dz.className='dropzone'; dz.textContent='Drag & drop photos here, or click to choose';
    var thumbs=document.createElement('div'); thumbs.className='thumbs';
    fileInput.style.display='none';
    fileInput.parentNode.insertBefore(dz, fileInput.nextSibling);
    fileInput.parentNode.insertBefore(thumbs, dz.nextSibling);
    dz.addEventListener('click',function(){fileInput.click();});
    ['dragover','dragenter'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.add('drag');});});
    ['dragleave','drop'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.remove('drag');});});
    dz.addEventListener('drop',function(e){ fileInput.files=e.dataTransfer.files; render(); });
    fileInput.addEventListener('change',render);
    function render(){
      thumbs.querySelectorAll('.thumb.new').forEach(function(n){n.remove();});
      Array.prototype.slice.call(fileInput.files).forEach(function(file){
        var d=document.createElement('div'); d.className='thumb new';
        var img=document.createElement('img'); img.src=URL.createObjectURL(file); d.appendChild(img); thumbs.appendChild(d);
      });
    }
  }
  // existing-image remove toggles
  document.querySelectorAll('.thumb .rm').forEach(function(btn){
    btn.addEventListener('click',function(){
      var t=btn.closest('.thumb'); var cb=t.querySelector('input[type=checkbox]');
      cb.checked=!cb.checked; t.classList.toggle('removing',cb.checked); btn.textContent=cb.checked?'↺':'✕';
    });
  });

  // ---------- auto-save draft (new forms only) ----------
  var df=document.querySelector('form[data-draft]');
  if(df && window.localStorage){
    var key='draft_'+df.dataset.draft;
    var fields=function(){return df.querySelectorAll('input[name]:not([type=file]):not([type=hidden]),select[name],textarea[name]');};
    var saved=localStorage.getItem(key);
    if(saved){
      var bar=document.createElement('div'); bar.className='draft-bar';
      bar.innerHTML='You have an unsaved draft. <a id="dRestore">Restore</a> <a id="dDiscard">Discard</a>';
      df.parentNode.insertBefore(bar, df);
      bar.querySelector('#dRestore').onclick=function(){try{var o=JSON.parse(saved);fields().forEach(function(el){if(o[el.name]!=null){if(el.type==='radio'){el.checked=(el.value===o[el.name]);}else el.value=o[el.name];}});}catch(e){} bar.remove();};
      bar.querySelector('#dDiscard').onclick=function(){localStorage.removeItem(key);bar.remove();};
    }
    var t; df.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){
      var o={}; fields().forEach(function(el){if(el.type==='radio'){if(el.checked)o[el.name]=el.value;}else o[el.name]=el.value;}); localStorage.setItem(key,JSON.stringify(o));
    },500);});
    df.addEventListener('submit',function(){localStorage.removeItem(key);});
  }
})();
</script>
<?php }

function layout_footer(): void { ?>
    </div>
  </div>
</div>
<script>
(function(){
  // ---- Toasts ----
  window.toast = function(msg, type){
    var box = document.getElementById('toasts'); if(!box) return alert(msg);
    var t = document.createElement('div'); t.className = 'toast' + (type==='err'?' err':''); t.textContent = msg;
    box.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(function(){t.remove();},400); }, 4000);
  };
  if (window.__flash) toast(window.__flash.msg, window.__flash.type);

  // ---- Loading spinner on POST submit + PDF actions ----
  var sp = document.getElementById('spinner');
  function showSpinner(){ if(sp) sp.classList.add('on'); }
  document.querySelectorAll('form').forEach(function(f){
    if ((f.getAttribute('method')||'get').toLowerCase()==='post'){
      f.addEventListener('submit', function(){ setTimeout(showSpinner, 50); });
    }
  });
  document.querySelectorAll('a[href*="print.php"]').forEach(function(a){
    a.addEventListener('click', function(){ showSpinner(); setTimeout(function(){ if(sp) sp.classList.remove('on'); }, 4000); });
  });
  window.addEventListener('pageshow', function(){ if(sp) sp.classList.remove('on'); });

  // ---- Theme toggle ----
  var btn = document.getElementById('themeBtn');
  if (btn) btn.addEventListener('click', function(){
    var cur = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', cur);
    try { localStorage.setItem('theme', cur); } catch(e){}
  });
})();
</script>
</body>
</html>
<?php }
