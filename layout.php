<?php
require_once __DIR__ . '/lib.php';

/** Render the page shell opening: <head>, sidebar, top bar, and open <main>. */
function layout_header(string $title, string $active = ''): void {
    $u = current_user();
    $nav = [
        'dashboard' => ['label' => 'Dashboard',            'href' => 'dashboard.php'],
        'bank'      => ['label' => 'Bank Valuations',      'href' => 'bank_list.php'],
        'insurance' => ['label' => 'Insurance Valuations', 'href' => 'insurance_list.php'],
        'clients'   => ['label' => 'Clients',              'href' => 'clients.php'],
        'insurers'  => ['label' => 'Insurers',             'href' => 'insurers.php'],
        'types'     => ['label' => 'Valuation Types',      'href' => 'types.php'],
    ];
    if (is_admin()) {
        $nav['users']    = ['label' => 'Users',    'href' => 'users.php'];
        $nav['settings'] = ['label' => 'Settings', 'href' => 'settings.php'];
    }
    $fl = flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<style>
  :root{--bg:#0f1216;--panel:#171c22;--line:#262d36;--txt:#e6e9ee;--mut:#9aa4b2;--accent:#d41d1d;--accent2:#2563eb;}
  *{box-sizing:border-box} body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--txt)}
  a{color:inherit;text-decoration:none}
  .wrap{display:flex;min-height:100vh}
  .side{width:230px;background:var(--panel);border-right:1px solid var(--line);padding:18px 0;position:sticky;top:0;height:100vh}
  .brand{font-weight:700;font-size:18px;padding:0 20px 16px;border-bottom:1px solid var(--line);margin-bottom:10px}
  .brand small{display:block;color:var(--mut);font-weight:400;font-size:11px}
  .nav a{display:block;padding:11px 20px;color:var(--mut);font-size:14px;border-left:3px solid transparent}
  .nav a:hover{background:#1e242c;color:var(--txt)}
  .nav a.on{color:#fff;border-left-color:var(--accent);background:#1e242c}
  .main{flex:1;display:flex;flex-direction:column;min-width:0}
  .top{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;border-bottom:1px solid var(--line);background:var(--panel)}
  .top h1{font-size:17px;margin:0}
  .who{color:var(--mut);font-size:13px}
  .content{padding:24px;max-width:1200px;width:100%}
  .flash{background:#0f3d24;border:1px solid #1c7a47;color:#b8f5d0;padding:10px 14px;border-radius:8px;margin-bottom:16px}
  .flash-err{background:#3d0f0f;border-color:#7a1c1c;color:#f5c0c0}
  .who .role{background:#2b3340;color:#cdd5e0;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:4px}
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
    <div class="brand"><?= e(APP_NAME) ?><small>Automobile Valuers</small></div>
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
        <a href="<?= url('profile.php') ?>" class="muted"><?= e($u['name'] ?? '') ?></a>
        <span class="role"><?= e(ucfirst($u['role'] ?? '')) ?></span>
        · <a href="<?= url('logout.php') ?>" class="muted">Log out</a>
      </div>
    </div>
    <div class="content">
      <?php if ($fl): ?><div class="flash <?= ($fl['type'] ?? 'ok') === 'err' ? 'flash-err' : '' ?>"><?= e($fl['msg'] ?? '') ?></div><?php endif; ?>
<?php }

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
        <button class="close" onclick="closePreview()">✕ Close</button>
      </span>
    </div>
    <iframe id="previewFrame" src="about:blank"></iframe>
  </div>
</div>
<script>
function openPreview(id){
  document.getElementById('previewFrame').src = '<?= url('preview.php') ?>?bare=1&id=' + id;
  document.getElementById('previewPdf').href = '<?= url('print.php') ?>?id=' + id;
  document.getElementById('previewModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closePreview(){
  document.getElementById('previewModal').classList.remove('open');
  document.getElementById('previewFrame').src = 'about:blank';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closePreview(); });
</script>
<?php }

function layout_footer(): void { ?>
    </div>
  </div>
</div>
</body>
</html>
<?php }
