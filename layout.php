<?php
require_once __DIR__ . '/lib.php';

/** Render the page shell opening: <head>, sidebar, top bar, and open <main>. */
function layout_header(string $title, string $active = ''): void {
    $u = current_user();
    $nav = [
        'bank'      => ['label' => 'Bank Valuations',      'href' => 'bank_list.php'],
        'insurance' => ['label' => 'Insurance Valuations', 'href' => 'insurance_list.php'],
        'clients'   => ['label' => 'Clients',              'href' => 'clients.php'],
        'insurers'  => ['label' => 'Insurers',             'href' => 'insurers.php'],
        'types'     => ['label' => 'Valuation Types',      'href' => 'types.php'],
    ];
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
        <?= e($u['name'] ?? '') ?> · <a href="<?= url('logout.php') ?>" class="muted">Log out</a>
      </div>
    </div>
    <div class="content">
      <?php if ($fl): ?><div class="flash"><?= e($fl) ?></div><?php endif; ?>
<?php }

function layout_footer(): void { ?>
    </div>
  </div>
</div>
</body>
</html>
<?php }
