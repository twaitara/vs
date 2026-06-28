<?php
require_once __DIR__ . '/lib.php';

/** Minimal, branded shell for the client portal (no staff nav). */
function portal_header(string $title): void {
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
  .kpi-ic{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex:0 0 46px;background:rgba(37,99,235,.15);color:#5b9bff}.kpi-ic.g{background:rgba(28,122,71,.18);color:#3ddc84}
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
  .muted{color:var(--mut)} .badge{display:inline-block;font-size:11px;padding:2px 7px;border-radius:10px}
  .b-red{background:#3d0f0f;color:#f5a3a3}.b-amber{background:#3d2f0f;color:#f5d79a}.b-green{background:#0f3d24;color:#b8f5d0}.b-grey{background:#2b3340;color:#cdd5e0}
  /* preview modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center}
  .modal-bg.open{display:flex}
  .modal{background:#fff;width:90%;max-width:900px;height:90vh;border-radius:10px;overflow:hidden;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;background:#171c22;color:#fff;padding:10px 16px}
  .modal-head a,.modal-head button{background:#d41d1d;color:#fff;border:0;padding:7px 14px;border-radius:6px;font-size:13px;cursor:pointer;margin-left:8px;text-decoration:none}
  .modal-head .close{background:#2b3340} .modal iframe{flex:1;width:100%;border:0;background:#fff}
</style></head>
<body>
<div class="pbar">
  <div class="brand">
    <img src="<?= url('assets/logo2.png') ?>" alt="Kennet">
    <div class="co">Client Portal<b><?= e($company ?: 'My Company') ?></b></div>
  </div>
  <a class="out" href="<?= url('portal_logout.php') ?>"><i data-lucide="log-out"></i> Log out</a>
</div>
<div class="wrap">
  <?php if ($fl): ?><div class="flash <?= ($fl['type'] ?? 'ok') === 'err' ? 'err' : '' ?>"><?= e($fl['msg'] ?? '') ?></div><?php endif; ?>
<?php }

function portal_footer(): void { ?>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>if(window.lucide)lucide.createIcons();</script>
</body></html>
<?php }
