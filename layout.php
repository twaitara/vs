<?php
require_once __DIR__ . '/lib.php';

/** Render the page shell opening: <head>, sidebar, top bar, and open <main>. */
function layout_header(string $title, string $active = ''): void {
    $u = current_user();
    $nav = [
        'dashboard' => ['label' => 'Dashboard',            'href' => 'dashboard.php',       'icon' => 'layout-dashboard'],
        'bank'      => ['label' => 'Bank Valuations',      'href' => 'bank_list.php',       'icon' => 'landmark'],
        'insurance' => ['label' => 'Insurance Valuations', 'href' => 'insurance_list.php',  'icon' => 'shield-check'],
        'machine'   => ['label' => 'Machine Valuations',   'href' => 'machine_list.php',    'icon' => 'cog'],
    ];
    if (can_assign()) {
        $pend = pending_request_count();
        $nav['requests'] = ['label' => 'Requests', 'href' => 'requests.php', 'icon' => 'inbox', 'badge' => $pend];
    }
    if (is_admin()) $nav['analytics'] = ['label' => 'Analytics', 'href' => 'analytics.php', 'icon' => 'bar-chart-3'];
    // Settings hub is admin-only.
    if (is_admin()) $nav['settings'] = ['label' => 'Settings', 'href' => 'settings.php', 'icon' => 'settings'];
    $fl = flash();
    $lastBackup = ''; $bkOverdue = false;
    if (is_admin()) {
        try {
            $bk = db()->query("SELECT created_at, user_name FROM audit_log WHERE action='backup' ORDER BY id DESC LIMIT 1")->fetch();
            if ($bk) {
                $t = strtotime($bk['created_at']);
                $bkUser = trim((string)($bk['user_name'] ?? ''));
                $days = $t ? (int)floor((time() - $t) / 86400) : 999;
                $bkOverdue = $days > 10;
                $lastBackup = 'Last: ' . ($t ? date('j M y', $t) : '')
                    . ($bkUser !== '' ? ' · ' . strtok($bkUser, ' ') : '')
                    . ($bkOverdue ? ' · ' . $days . 'd ago' : '');
            } else {
                $bkOverdue = true;
                $lastBackup = 'Never backed up';
            }
        } catch (Throwable $e) { $lastBackup = ''; }
    }
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/logo.png') ?>">
<link rel="manifest" href="<?= url('manifest.php') ?>">
<meta name="theme-color" content="#0f1216">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="<?= url('icons/apple-180.png') ?>">
<script>(function(){try{var t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0f1216;--panel:#171c22;--line:#262d36;--txt:#e6e9ee;--mut:#9aa4b2;--accent:#d41d1d;--accent2:#2563eb;
        --input:#0f1419;--chip:#1b212a;--th:#1b212a;--rowh:#1a2028;--hover:#2b3340;}
  html[data-theme="light"]{--bg:#eef1f5;--panel:#ffffff;--line:#dce1e8;--txt:#1a2330;--mut:#5b6573;--accent:#d41d1d;--accent2:#2563eb;
        --input:#f3f6f9;--chip:#eef1f5;--th:#eef2f7;--rowh:#f5f8fb;--hover:#e6ebf1;}
  *{box-sizing:border-box}
  body{margin:0;font-family:'Plus Jakarta Sans',system-ui,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--txt);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
  h1,h2,h3,.brand{font-weight:700;letter-spacing:-0.015em}
  a{color:inherit;text-decoration:none}
  .wrap{display:flex;min-height:100vh}
  .side{width:230px;background:var(--panel);border-right:1px solid var(--line);padding:18px 0;position:sticky;top:0;height:100vh}
  .brand{padding:0 20px 16px;border-bottom:1px solid var(--line);margin-bottom:10px}
  .brand-logo{max-width:180px;width:100%;height:auto;display:block;background:#fff;padding:8px;border-radius:8px}
  .brand small{display:block;color:var(--mut);font-weight:400;font-size:11px;margin-top:8px}
  .nav a{display:flex;align-items:center;gap:11px;padding:11px 20px;color:var(--mut);font-size:14px;border-left:3px solid transparent;transition:background .18s,color .18s,transform .18s,padding .18s;opacity:0;animation:navIn .4s ease forwards}
  .nav a i{width:18px;height:18px;flex:0 0 18px;transition:transform .18s}
  .nav a:hover{background:var(--rowh);color:var(--txt);padding-left:24px}
  .nav a:hover i{transform:scale(1.15)}
  .nav a.on{color:var(--txt);border-left-color:var(--accent);background:linear-gradient(90deg,rgba(212,29,29,.16),transparent)}
  @keyframes navIn{from{opacity:0;transform:translateX(-10px)}to{opacity:1;transform:none}}
  .main{flex:1;display:flex;flex-direction:column;min-width:0}
  .top{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;border-bottom:1px solid var(--line);background:var(--panel)}
  .top h1{font-size:17px;margin:0}
  .who{color:var(--mut);font-size:13px}
  .content{padding:24px;max-width:none;width:100%}
  .flash{background:#0f3d24;border:1px solid #1c7a47;color:#b8f5d0;padding:10px 14px;border-radius:8px;margin-bottom:16px}
  .flash-err{background:#3d0f0f;border-color:#7a1c1c;color:#f5c0c0}
  .sysbanner{display:flex;align-items:center;gap:8px;justify-content:center;background:linear-gradient(90deg,#7a5c1c,#9a7320);color:#fff;padding:9px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:600}
  .sysbanner i{width:16px;height:16px}
  #toasts{position:fixed;top:16px;right:16px;z-index:3000;display:flex;flex-direction:column;gap:8px}
  .toast{background:#171c22;border:1px solid #1c7a47;color:#b8f5d0;padding:11px 16px;border-radius:8px;font-size:13px;box-shadow:0 6px 24px rgba(0,0,0,.35);max-width:320px;animation:tin .25s ease}
  .toast.err{border-color:#7a1c1c;color:#f5c0c0}
  @keyframes tin{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
  /* top progress bar */
  #topbar{position:fixed;top:0;left:0;right:0;height:5px;z-index:6000;background:transparent;pointer-events:none;opacity:0;transition:opacity .25s}
  #topbar.on{opacity:1}
  #topbar .topbar-fill{height:100%;width:0;border-radius:0 4px 4px 0;
       background:linear-gradient(90deg,#2563eb,#7a4dd1,#d41d1d,#ff8a3c);
       background-size:300% 100%;animation:tbflow 2s linear infinite;
       box-shadow:0 0 14px rgba(212,29,29,.85),0 0 8px rgba(37,99,235,.8);transition:width .25s ease}
  @keyframes tbflow{to{background-position:300% 0}}
  /* processing overlay card */
  #spinner{display:none;position:fixed;inset:0;background:rgba(6,9,13,.62);backdrop-filter:blur(3px);z-index:4000;align-items:center;justify-content:center}
  #spinner.on{display:flex;animation:fadein .2s ease}
  @keyframes fadein{from{opacity:0}to{opacity:1}}
  #spinner .sp-card{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:26px 30px;width:300px;text-align:center;box-shadow:0 24px 70px rgba(0,0,0,.55);animation:pop .3s cubic-bezier(.2,.8,.2,1)}
  @keyframes pop{from{opacity:0;transform:translateY(14px) scale(.96)}to{opacity:1;transform:none}}
  #spinner .sp-ring{width:46px;height:46px;margin:0 auto 16px;border:4px solid var(--line);border-top-color:var(--accent);border-right-color:#ff8a3c;border-radius:50%;animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  #spinner .sp-bar{height:8px;border-radius:6px;background:var(--input);overflow:hidden;position:relative}
  #spinner .sp-bar span{position:absolute;left:-45%;width:45%;height:100%;border-radius:6px;
       background:linear-gradient(90deg,#2563eb,#d41d1d,#ff8a3c);animation:slide 1.1s ease-in-out infinite}
  @keyframes slide{0%{left:-45%}100%{left:100%}}
  #spinner .sp-msg{margin-top:15px;color:var(--txt);font-weight:700;font-size:15px}
  #spinner .sp-sub{color:var(--mut);font-size:12px;margin-top:3px}
  .themebtn{background:var(--chip);color:var(--mut);border:0;border-radius:6px;width:28px;height:24px;cursor:pointer;font-size:13px;margin-right:6px}
  .topbackup{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#1c9c5d,#27c46f);color:#fff;padding:7px 13px;border-radius:8px;font-size:13px;font-weight:700;box-shadow:0 4px 14px rgba(28,156,93,.4);transition:transform .15s,box-shadow .15s}
  .topbackup:hover{transform:translateY(-1px);box-shadow:0 7px 20px rgba(28,156,93,.55)}
  .topbackup i{width:16px;height:16px}
  .topbackup .bk-txt{display:flex;flex-direction:column;align-items:flex-start;line-height:1.15}
  .topbackup .bk-main{font-size:13px;font-weight:700}
  .topbackup .bk-meta{font-size:9.5px;font-weight:500;opacity:.9}
  .topbackup.overdue{background:linear-gradient(135deg,#d41d1d,#e24b4a);box-shadow:0 0 0 0 rgba(226,75,74,.55);animation:bkpulse 1.4s infinite}
  .topbackup.overdue:hover{transform:translateY(-1px)}
  @keyframes bkpulse{0%{box-shadow:0 0 0 0 rgba(226,75,74,.55)}70%{box-shadow:0 0 0 12px rgba(226,75,74,0)}100%{box-shadow:0 0 0 0 rgba(226,75,74,0)}}
  .who{display:flex;align-items:center;gap:10px}
  .who .whoami{display:flex;align-items:center;gap:6px;color:var(--mut);font-size:13px;transition:color .15s}
  .who .whoami:hover{color:var(--txt)} .who .whoami i{width:16px;height:16px}
  /* notifications bell */
  .notif-wrap{position:relative;display:inline-block}
  .notif-bell{background:var(--chip);color:var(--mut);border:0;border-radius:6px;width:30px;height:26px;cursor:pointer;position:relative}
  .notif-bell:hover{color:var(--txt)} .notif-bell i{width:16px;height:16px;color:#f5b14a}
  .notif-badge{position:absolute;top:-6px;right:-6px;background:var(--accent);color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;line-height:16px;border-radius:8px;padding:0 4px;text-align:center}
  .notif-panel{display:none;position:absolute;right:0;top:calc(100% + 8px);width:330px;max-width:88vw;background:var(--panel);border:1px solid var(--line);border-radius:10px;box-shadow:0 16px 40px rgba(0,0,0,.4);z-index:200;overflow:hidden}
  .notif-panel.open{display:block}
  .notif-head{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid var(--line);font-weight:600;font-size:13px;color:var(--txt)}
  .notif-markall{background:none;border:0;color:var(--accent2,#2563eb);font-size:12px;cursor:pointer}
  .notif-list{max-height:60vh;overflow:auto}
  .notif-item{display:block;padding:10px 12px;border-bottom:1px solid var(--line);color:var(--txt);text-decoration:none;font-size:13px}
  .notif-item:hover{background:var(--hover,#2b3340)}
  .notif-item.unread{background:rgba(37,99,235,.08);border-left:3px solid var(--accent2,#2563eb)}
  .notif-title{font-weight:600} .notif-body{color:var(--mut);font-size:12px;margin-top:2px} .notif-time{color:var(--mut);font-size:11px;margin-top:3px}
  .notif-empty{padding:18px 12px;color:var(--mut);font-size:13px;text-align:center}
  .devcredit{margin-top:28px;padding:14px 0 4px;border-top:1px solid var(--line);text-align:center;color:var(--mut);font-size:12px}
  .devcredit b{color:var(--txt)}
  .who .logout{display:inline-flex;color:var(--mut);transition:color .15s,transform .15s} .who .logout:hover{color:var(--accent);transform:translateX(2px)} .who .logout i{width:18px;height:18px}
  .who .role{background:var(--chip);color:var(--mut);font-size:11px;padding:2px 8px;border-radius:10px}
  /* ---- global motion + icon polish ---- */
  .content{animation:fadeUp .45s cubic-bezier(.2,.7,.2,1)}
  @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  .btn{transition:transform .15s,filter .15s,box-shadow .15s} .btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(212,29,29,.25)}
  .btn:active{transform:translateY(0)}
  .btn.sec:hover,.btn.blue:hover{box-shadow:0 6px 18px rgba(0,0,0,.25)}
  .btn i,.rbtn i{width:15px;height:15px;vertical-align:-3px;margin-right:5px}
  .rbtn.ico{padding:3px 5px} .rbtn.ico i{margin-right:0;width:13px;height:13px}
  .actions{display:flex;flex-wrap:wrap;gap:3px}
  table.list tr{transition:background .12s}
  .rbtn{transition:background .15s,transform .15s} .rbtn:hover{transform:translateY(-1px)}
  .rbtn.sign-due{background:linear-gradient(135deg,#f5a623,#f7b733);color:#3a2a00!important;border-color:#f5a623;font-weight:700;animation:signpulse 1.5s infinite}
  .rbtn.sign-due i{color:#3a2a00}
  .rbtn.sign-due:hover{filter:brightness(1.05)}
  @keyframes signpulse{0%{box-shadow:0 0 0 0 rgba(245,166,35,.6)}70%{box-shadow:0 0 0 9px rgba(245,166,35,0)}100%{box-shadow:0 0 0 0 rgba(245,166,35,0)}}
  .themebtn{display:inline-flex;align-items:center;justify-content:center} .themebtn i{width:15px;height:15px}
  .card,.kpi,.panel{transition:transform .2s,box-shadow .2s,border-color .2s}
  .kpi:hover,.panel:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.18);border-color:var(--line)}
  .badge{transition:transform .15s}.badge:hover{transform:scale(1.05)}
  a,button{ -webkit-tap-highlight-color:transparent }
  ::-webkit-scrollbar{width:10px;height:10px}::-webkit-scrollbar-thumb{background:var(--hover);border-radius:6px}::-webkit-scrollbar-track{background:transparent}
  /* ===================== visual life ===================== */
  body{background:
      radial-gradient(1100px 560px at 100% -8%, rgba(212,29,29,.12), transparent 55%),
      radial-gradient(900px 520px at -8% 108%, rgba(37,99,235,.10), transparent 55%),
      var(--bg);
      background-attachment:fixed}
  .side{background:linear-gradient(180deg, var(--panel), var(--bg))}
  .brand-logo{box-shadow:0 6px 18px rgba(0,0,0,.25)}
  .top{position:sticky;top:0;z-index:50;background:var(--panel);box-shadow:0 1px 0 var(--line)}
  .btn{background:linear-gradient(135deg,var(--accent),#ff5a3c);font-weight:600;position:relative;overflow:hidden}
  .btn.sec{background:var(--chip);color:var(--txt)}
  .btn.blue{background:linear-gradient(135deg,#2563eb,#4f8bff)}
  .btn::after{content:"";position:absolute;top:0;left:-130%;width:55%;height:100%;
      background:linear-gradient(120deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);transition:left .6s}
  .btn:hover::after{left:150%}
  .card,.panel,.kpi{box-shadow:0 1px 2px rgba(0,0,0,.06),0 10px 28px rgba(0,0,0,.07)}
  .kpi{overflow:hidden;position:relative}
  .kpi::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,var(--accent),#ff8a5c)}
  table.list{box-shadow:0 10px 28px rgba(0,0,0,.07)}
  table.list th{background:linear-gradient(180deg,var(--th),var(--panel))}
  .badge::before{content:"";display:inline-block;width:6px;height:6px;border-radius:50%;background:currentColor;margin-right:6px;vertical-align:1px;opacity:.85}
  table.list tbody tr{animation:rowIn .35s ease both}
  @keyframes rowIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
  .subnav{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid var(--line);margin-bottom:20px}
  .subnav a{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;color:var(--mut);font-size:13px;border-bottom:2px solid transparent;transition:color .15s,border-color .15s}
  .subnav a i{width:16px;height:16px}
  .subnav a:hover{color:var(--txt)}
  .subnav a.on{color:var(--accent);border-bottom-color:var(--accent)}
  .btn{display:inline-block;background:var(--accent);color:#fff;border:0;padding:9px 16px;border-radius:8px;font-size:14px;cursor:pointer}
  .btn:hover{filter:brightness(1.1)} .btn.sec{background:var(--chip);color:var(--txt)} .btn.blue{background:var(--accent2)}
  table.list{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--line);border-radius:10px;overflow:hidden}
  table.list th,table.list td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--line);font-size:13px}
  table.list th{background:var(--th);color:var(--mut);font-weight:600}
  table.list tr:hover td{background:var(--rowh)}
  .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px}
  .toolbar input[type=search]{background:var(--input);border:1px solid var(--line);color:var(--txt);padding:9px 12px;border-radius:8px;width:280px}
  form.card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}
  fieldset{border:1px solid var(--line);border-radius:10px;padding:16px 18px;margin:0 0 18px}
  legend{color:var(--accent);font-weight:600;padding:0 8px;font-size:14px}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
  label.f{display:block;font-size:12px;color:var(--mut);margin-bottom:5px}
  .f input,.f select,.f textarea{width:100%;background:var(--input);border:1px solid var(--line);color:var(--txt);padding:8px 10px;border-radius:7px;font-size:14px;font-family:inherit}
  .f textarea{min-height:70px;resize:vertical}
  .yn{display:flex;gap:14px;font-size:13px;align-items:center}
  .yn label{display:flex;gap:5px;align-items:center;color:var(--txt)}
  .actions a{margin-right:8px;font-size:13px;color:var(--accent2)}
  .actions a.rbtn{display:inline-block;border:1px solid var(--line);color:var(--txt);padding:4px 12px;border-radius:6px;background:var(--chip)}
  .actions a.rbtn:hover{background:var(--hover)}
  .pager{display:flex;gap:6px;justify-content:center;align-items:center;margin-top:18px;flex-wrap:wrap}
  .pager a,.pager span{padding:6px 11px;border:1px solid var(--line);border-radius:6px;font-size:13px;background:var(--panel);color:var(--txt)}
  .pager a:hover{background:var(--hover)}
  .pager .cur{background:var(--accent);border-color:var(--accent);color:#fff}
  Xborder-color:transparent;background:transparent}
  .count{color:var(--mut);font-size:12px;margin-top:10px;text-align:center}
  .sortlink{color:var(--mut)} .sortlink:hover{color:var(--txt)}
  .filterbar{display:none;background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:16px}
  .filterbar.open{display:block}
  .fb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
  .fb-grid label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--mut)}
  .fb-grid input,.fb-grid select{background:var(--input);border:1px solid var(--line);color:var(--txt);padding:7px 9px;border-radius:7px;font-size:13px}
  .fb-actions{margin-top:12px;display:flex;gap:8px}
  .bulkbar{display:flex;align-items:center;gap:14px;margin-bottom:10px;font-size:13px;color:var(--mut);flex-wrap:wrap}
  .stagebox{display:inline-block;font-size:9px;font-weight:700;letter-spacing:.5px;padding:2px 5px;border-radius:3px;background:#3d2f0f;color:#f5d79a;border:1px solid #7a5c1c;margin:1px;line-height:1.3}
  .stagekey{font-size:11px;color:var(--mut);display:inline-flex;flex-wrap:wrap;align-items:center}
  .badge{display:inline-block;font-size:11px;padding:2px 7px;border-radius:10px;white-space:nowrap}
  .b-red{background:#3d0f0f;color:#f5a3a3;border:1px solid #7a1c1c}
  .b-amber{background:#3d2f0f;color:#f5d79a;border:1px solid #7a5c1c}
  .b-grey{background:#2b3340;color:#cdd5e0}
  .b-green{background:#0f3d24;color:#b8f5d0;border:1px solid #1c7a47}
  .b-blue{background:#0f2440;color:#a3c8f5;border:1px solid #1c4a7a}
  .colpick{position:relative;display:inline-flex;gap:8px;margin:0 0 12px}
  .colpick-btn{background:var(--panel);border:1px solid var(--line);color:var(--txt);padding:8px 14px;border-radius:8px;font-size:13px;cursor:pointer}
  .colpick-btn:hover{background:var(--hover,#2b3340)}
  table.list.compact th,table.list.compact td{padding:3px 8px;font-size:12px}
  table.list.compact .badge,table.list.compact .stagebox{font-size:10px;padding:1px 5px}
  table.list.compact .rbtn.ico{padding:2px 4px}
  table.list.compact .rbtn.ico i{width:12px;height:12px}
  .colpick-menu{display:none;position:absolute;z-index:60;top:calc(100% + 4px);left:0;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:8px;min-width:180px;max-height:340px;overflow:auto;box-shadow:0 12px 30px rgba(0,0,0,.35)}
  .colpick-menu.open{display:block}
  .colpick-menu label{display:flex;gap:8px;align-items:center;font-size:13px;padding:5px 7px;white-space:nowrap;cursor:pointer;border-radius:6px;color:var(--txt)}
  .colpick-menu label:hover{background:var(--hover,#2b3340)}
  .ppager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:14px}
  .ppager .pgb{background:var(--panel);border:1px solid var(--line);color:var(--txt);padding:6px 11px;border-radius:6px;font-size:12px;cursor:pointer}
  .ppager .pgb:hover:not(:disabled){background:var(--hover,#2b3340)} .ppager .pgb.cur{background:var(--accent);border-color:var(--accent);color:#fff} .ppager .pgb:disabled{opacity:.4;cursor:default}
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
  /* === overrides (must win over base rules above) === */
  .btn{background:linear-gradient(135deg,var(--accent),#ff5a3c)!important;font-weight:600}
  .btn.sec{background:var(--chip)!important;color:var(--txt)}
  .btn.blue{background:linear-gradient(135deg,#2563eb,#4f8bff)!important}
  table.list th{background:linear-gradient(180deg,var(--th),var(--panel))}
  table.list tbody tr:hover td{background:var(--rowh)}
  /* === mobile / responsive === */
  .menu-toggle{display:none;background:var(--chip);color:var(--txt);border:0;border-radius:8px;width:36px;height:34px;align-items:center;justify-content:center;cursor:pointer;margin-right:10px}
  .menu-toggle i{width:20px;height:20px}
  .top-l{display:flex;align-items:center;min-width:0}
  .top-l h1{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .overlay{display:none}
  .fab{display:none}
  @media(max-width:860px){
    .side{position:fixed;left:0;top:0;height:100vh;z-index:200;transform:translateX(-100%);transition:transform .25s ease;box-shadow:0 0 50px rgba(0,0,0,.55)}
    .side.open{transform:none}
    .overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150}
    .overlay.show{display:block}
    .menu-toggle{display:inline-flex}
    .content{padding:16px}
    .top{padding:12px 14px}
    .top h1{font-size:16px}
    .who{gap:6px} .who .whoami span,.who .role,.who .topbackup span{display:none}
    .who .topbackup{padding:7px 9px}
    table.list{display:block;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch}
    .toolbar{flex-direction:column;align-items:stretch}
    .toolbar > div{display:flex;gap:8px;flex-wrap:wrap}
    .toolbar input[type=search]{width:100%}
    .fb-grid{grid-template-columns:1fr 1fr}
    .fab{display:inline-flex}
    #navInstall{display:flex}
  }
  /* floating quick-action button */
  .fab{position:fixed;right:18px;bottom:18px;z-index:120;align-items:center;gap:8px;
       background:linear-gradient(135deg,var(--accent),#ff5a3c);color:#fff;padding:14px 18px;border-radius:30px;
       font-weight:700;font-size:14px;box-shadow:0 10px 30px rgba(212,29,29,.45);transition:transform .15s}
  .fab:hover{transform:translateY(-2px)} .fab i{width:20px;height:20px}
  /* === coloured icons === */
  .nav .lucide-layout-dashboard{color:#5b9bff}
  .nav .lucide-landmark{color:#ff6b6b}
  .nav .lucide-shield-check{color:#22c55e}
  .nav .lucide-cog{color:#f5b14a}
  .nav .lucide-bar-chart-3{color:#b18cff}
  .nav .lucide-settings{color:#f5b14a}
  .nav .lucide-inbox{color:#38bdf8}
  .nav a{position:relative}
  .navbadge{margin-left:auto;background:var(--accent);color:#fff;font-size:11px;font-weight:700;min-width:18px;height:18px;line-height:18px;text-align:center;border-radius:9px;padding:0 5px}
  #navInstall{display:none}
  #navInstall .lucide-download{color:#27c46f}
  #navInstall.installable{background:linear-gradient(90deg,rgba(39,196,111,.16),transparent)}
  .subnav .lucide-building-2{color:#5b9bff}.subnav .lucide-users{color:#22c55e}.subnav .lucide-shield{color:#5b9bff}
  .subnav .lucide-tags{color:#f5b14a}.subnav .lucide-user-cog{color:#b18cff}.subnav .lucide-scroll-text{color:#5b9bff}.subnav .lucide-trash-2{color:#ff6b6b}
  .rbtn .lucide-pencil{color:#5b9bff}.rbtn .lucide-copy{color:#b18cff}.rbtn .lucide-eye{color:#22c55e}.rbtn .lucide-printer{color:#7c8896}
  .actions .lucide-trash-2{color:#ff6b6b} .del-one{background:var(--chip);cursor:pointer}
  .who .lucide-sun-moon{color:#f5b14a}.who .lucide-user-round{color:#5b9bff}.who .lucide-log-out{color:#ff6b6b}
  /* keep icons readable inside solid/gradient buttons */
  .btn:not(.sec) .lucide{color:#fff}
  .btn.sec .lucide{color:var(--accent2)}
</style>
</head>
<body>
<div class="wrap">
  <aside class="side" id="sidebar">
    <div class="brand">
      <a href="<?= url('dashboard.php') ?>"><img src="<?= url('assets/logo2.png') ?>" alt="<?= e(APP_NAME) ?>" class="brand-logo"></a>
      <small>Automobile Valuers</small>
    </div>
    <nav class="nav">
      <?php foreach ($nav as $k => $item): ?>
        <a href="<?= url($item['href']) ?>" class="<?= $active===$k?'on':'' ?>" style="animation-delay:<?= (0.04 * array_search($k, array_keys($nav))) ?>s">
          <i data-lucide="<?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
          <?php if (!empty($item['badge'])): ?><b class="navbadge"><?= (int)$item['badge'] ?></b><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a href="#" id="navInstall" onclick="return kInstall();" style="animation-delay:<?= (0.04 * count($nav)) ?>s">
        <i data-lucide="download"></i><span>Install App</span>
      </a>
    </nav>
  </aside>
  <div class="overlay" id="navOverlay"></div>
  <div class="main">
    <div class="top">
      <div class="top-l"><button class="menu-toggle" id="menuToggle" title="Menu"><i data-lucide="menu"></i></button><h1><?= e($title) ?></h1></div>
      <div class="who">
        <?php if (is_admin()): ?><a href="<?= url('backup.php') ?>" class="topbackup<?= $bkOverdue ? ' overdue' : '' ?>" title="Backup database — you are responsible for keeping your own backups" onclick="return confirm('IMPORTANT: You are responsible for backing up and safely keeping your own data.\n\nDownload regular copies and store them off-site (e.g. cloud storage or another computer). No copies are retained on your behalf.\n\nDownload a backup now?');"><i data-lucide="<?= $bkOverdue ? 'alert-triangle' : 'database-backup' ?>"></i><span class="bk-txt"><span class="bk-main"><?= $bkOverdue ? 'Backup now' : 'Backup DB' ?></span><?php if ($lastBackup): ?><span class="bk-meta"><?= e($lastBackup) ?></span><?php endif; ?></span></a><?php endif; ?>
        <button type="button" id="themeBtn" class="themebtn" title="Toggle light/dark"><i data-lucide="sun-moon"></i></button>
        <div class="notif-wrap">
          <button type="button" id="notifBell" class="notif-bell" title="Notifications"><i data-lucide="bell"></i><span id="notifBadge" class="notif-badge" style="display:none">0</span></button>
          <div id="notifPanel" class="notif-panel">
            <div class="notif-head"><span>Notifications</span><button type="button" id="notifMarkAll" class="notif-markall">Mark all read</button></div>
            <div id="notifList" class="notif-list"></div>
          </div>
        </div>
        <a href="<?= url('profile.php') ?>" class="whoami"><i data-lucide="user-round"></i><span><?= e($u['name'] ?? '') ?></span></a>
        <span class="role"><?= e(ucfirst($u['role'] ?? '')) ?></span>
        <a href="<?= url('logout.php') ?>" class="muted logout" title="Log out"><i data-lucide="log-out"></i></a>
      </div>
    </div>
    <div id="topbar"><div class="topbar-fill"></div></div>
    <div id="toasts"></div>
    <div id="spinner"><div class="sp-card">
      <div class="sp-ring"></div>
      <div class="sp-bar"><span></span></div>
      <div class="sp-msg">Processing…</div>
      <div class="sp-sub">Please wait a moment</div>
    </div></div>
    <div class="content">
      <?php if (setting('banner_enabled') === '1' && setting('banner_until')): ?>
        <div class="sysbanner"><i data-lucide="alert-triangle"></i> <?= e(availability_message()) ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['avail_notice_pending']) && setting('banner_enabled') === '1' && !is_superadmin()): unset($_SESSION['avail_notice_pending']); ?>
        <div class="modal-bg open" id="availModal" style="z-index:5000">
          <div class="modal" style="max-width:440px;height:auto;background:var(--panel);color:var(--txt);border:1px solid var(--line)">
            <div style="padding:26px;text-align:center">
              <div style="font-size:34px">⚠️</div>
              <h3 style="margin:8px 0 10px">Please note</h3>
              <p style="color:var(--mut);font-size:14px;line-height:1.6"><?= e(availability_message()) ?></p>
              <button class="btn" type="button" onclick="document.getElementById('availModal').remove();document.body.style.overflow=''">I acknowledge</button>
            </div>
          </div>
        </div>
        <script>document.body.style.overflow='hidden';</script>
      <?php endif; ?>
      <?php if ($fl): ?><script>window.__flash = <?= json_encode($fl) ?>;</script><?php endif; ?>
<?php }

/** Sub-navigation for the Settings hub. $active = section key. */
function settings_nav(string $active): void {
    $tabs = [];
    if (is_admin()) $tabs['general'] = ['Company', 'settings.php', 'building-2'];
    $tabs['clients']  = ['Clients', 'clients.php', 'users'];
    $tabs['insurers'] = ['Insurers', 'insurers.php', 'shield'];
    $tabs['types']    = ['Valuation Types', 'types.php', 'tags'];
    if (is_admin()) { $tabs['users'] = ['Users', 'users.php', 'user-cog']; $tabs['portal'] = ['Portal Users', 'client_users.php', 'key-round']; $tabs['audit'] = ['Audit Log', 'audit.php', 'scroll-text']; }
    if (is_superadmin()) $tabs['email'] = ['Email', 'settings_email.php', 'mail'];
    $tabs['recycle']  = ['Recycle Bin', 'recycle.php', 'trash-2'];
    echo '<div class="subnav">';
    foreach ($tabs as $k => $t) {
        echo '<a class="' . ($k === $active ? 'on' : '') . '" href="' . url($t[1]) . '"><i data-lucide="' . $t[2] . '"></i>' . e($t[0]) . '</a>';
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
  .tabbtn.active{color:var(--txt);font-weight:700;border-bottom-color:var(--accent)}
  .tabbtn.has-invalid{color:#f5a3a3}
  .wizard-nav{display:flex;justify-content:space-between;margin:10px 0 0}
  .f.has-err input,.f.has-err select,.f.has-err textarea{border-color:#d41d1d}
  .field-err{color:#f5a3a3;font-size:11px;margin-top:3px;display:block}
  .words-preview{display:block;margin-top:4px;font-style:italic}
  .draft-bar{background:#3d2f0f;border:1px solid #7a5c1c;color:#f5d79a;padding:8px 12px;border-radius:8px;margin-bottom:14px;font-size:13px}
  .draft-bar a{color:#fff;text-decoration:underline;cursor:pointer;margin-left:8px}
  .autosave-note{display:block;margin-top:12px;font-size:12px;color:var(--mut)}
  .autosave-note.ok{color:#3ddc84}
  .autosave-note::before{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;background:currentColor;margin-right:6px;vertical-align:1px}
  .quick-add{margin-left:8px;font-size:12px;color:var(--accent2);cursor:pointer}
  .dropzone{border:2px dashed var(--line);border-radius:10px;padding:16px;text-align:center;color:var(--mut);cursor:pointer;font-size:13px}
  .dropzone.drag{border-color:var(--accent);color:var(--txt)}
  .thumbs{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
  .thumb{position:relative;width:92px;height:70px;border:1px solid var(--line);border-radius:6px;overflow:hidden;background:var(--input)}
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
    var camInput=document.querySelector('input[name="camera_photos[]"]');
    if(camInput){ camInput.addEventListener('change',function(){
      Array.prototype.slice.call(camInput.files).forEach(function(file){
        var d=document.createElement('div'); d.className='thumb cam';
        var im=document.createElement('img'); im.src=URL.createObjectURL(file); d.appendChild(im); thumbs.appendChild(d);
      });
    }); }
  }
  // existing-image remove toggles
  document.querySelectorAll('.thumb .rm').forEach(function(btn){
    btn.addEventListener('click',function(){
      var t=btn.closest('.thumb'); var cb=t.querySelector('input[type=checkbox]');
      cb.checked=!cb.checked; t.classList.toggle('removing',cb.checked); btn.textContent=cb.checked?'↺':'✕';
    });
  });

  // ---------- auto-save draft (new & edit forms; survives crashes) ----------
  var df=document.querySelector('form[data-draft]');
  if(df && window.localStorage){
    var key='draft_'+df.dataset.draft;
    var fields=function(){return df.querySelectorAll('input[name]:not([type=file]):not([type=hidden]):not([name=_csrf]),select[name],textarea[name]');};
    var pad=function(n){return (n<10?'0':'')+n;};
    var clock=function(d){return pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds());};

    // status pill
    var note=document.createElement('div'); note.className='autosave-note'; note.textContent='Autosave on';
    df.appendChild(note);

    // restore banner if a draft exists
    var saved=localStorage.getItem(key);
    if(saved){ try{
      var prev=JSON.parse(saved); var when=prev.t?new Date(prev.t):null;
      var reg=(prev.d&&prev.d.reg_no)?(''+prev.d.reg_no).replace(/[<>&"]/g,''):'';
      var bar=document.createElement('div'); bar.className='draft-bar';
      bar.innerHTML='<span><i data-lucide="rotate-ccw" style="width:15px;height:15px;vertical-align:-3px"></i> Unsaved draft found'+(reg?(' for Reg No <b>'+reg+'</b>'):'')+(when?(' from '+clock(when)):'')+'.</span> <a id="dRestore">Restore</a> <a id="dDiscard">Discard</a>';
      df.parentNode.insertBefore(bar, df); if(window.refreshIcons)refreshIcons();
      bar.querySelector('#dRestore').onclick=function(){var o=prev.d||prev;fields().forEach(function(el){if(o[el.name]!=null){if(el.type==='radio'){el.checked=(el.value===o[el.name]);}else el.value=o[el.name];}});
        df.querySelectorAll('input.money').forEach(function(m){m.dispatchEvent(new Event('input'));}); bar.remove(); note.textContent='Draft restored';};
      bar.querySelector('#dDiscard').onclick=function(){localStorage.removeItem(key);bar.remove();};
    }catch(e){} }

    function save(){
      var reg=df.querySelector('[name="reg_no"]');
      if(!reg || reg.value.trim()===''){ note.classList.remove('ok'); note.textContent='Autosave starts once you enter the Reg No'; return; }
      var o={}; fields().forEach(function(el){ if(el.type==='radio'){ if(el.checked)o[el.name]=el.value; } else o[el.name]=el.value; });
      try{ localStorage.setItem(key, JSON.stringify({t:Date.now(), d:o})); note.textContent='Draft saved '+clock(new Date()); note.classList.add('ok'); }catch(e){}
    }
    var t;
    df.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(save, 400); });
    df.addEventListener('change', save);            // selects, radios
    document.addEventListener('visibilitychange', function(){ if(document.hidden) save(); });
    window.addEventListener('pagehide', save);      // closing / navigating away
    df.addEventListener('submit', function(){ try{localStorage.removeItem(key);}catch(e){} });
  }

  // ---------- online-activity heartbeat: report the reg being worked on ----------
  var hf=document.querySelector('form[data-draft]');
  if(hf){
    var hreg=hf.querySelector('[name="reg_no"]')||hf.querySelector('[name="machine_name"]');
    if(hreg){
      var hd=hf.dataset.draft||''; var htype=hd.indexOf('machine')===0?'machine':(hd.indexOf('ins')===0?'insurance':'bank'); var hmode=/[0-9]/.test(hd)?'Editing':'New'; var hbt;
      function hping(){ var fd=new FormData(); fd.append('_csrf',CSRF); fd.append('type',htype); fd.append('mode',hmode); fd.append('reg',hreg.value.trim()); fetch('<?= url('activity.php') ?>',{method:'POST',body:fd}).catch(function(){}); }
      hreg.addEventListener('input',function(){clearTimeout(hbt);hbt=setTimeout(hping,800);});
      setInterval(hping,25000); hping();
    }
  }
})();
</script>
<?php }

function layout_footer(): void { ?>
      <div class="devcredit"><?= dev_credit() ?></div>
    </div>
  </div>
</div>
<?php if (can_edit()): ?>
<a class="fab" href="<?= url('bank_form.php') ?>" title="New valuation"><i data-lucide="plus"></i><span>New Valuation</span></a>
<?php endif; ?>
<script>
if('serviceWorker' in navigator){ navigator.serviceWorker.register('<?= url('sw.js') ?>').catch(function(){}); }
window.__dp = null;
window.addEventListener('beforeinstallprompt', function(e){
  e.preventDefault(); window.__dp = e;
  var n=document.getElementById('navInstall'); if(n) n.classList.add('installable');
  if(!window.matchMedia('(max-width:860px)').matches) return; // mobile only
  if(document.getElementById('installBtn')) return;
  var b=document.createElement('button'); b.id='installBtn'; b.textContent='⤓ Install app';
  b.style.cssText='position:fixed;left:16px;bottom:16px;z-index:130;background:#2563eb;color:#fff;border:0;padding:11px 16px;border-radius:24px;font-weight:700;font-size:13px;box-shadow:0 8px 24px rgba(37,99,235,.45);cursor:pointer';
  b.onclick=function(){ b.remove(); if(window.__dp){window.__dp.prompt();window.__dp=null;} };
  document.body.appendChild(b);
});
window.addEventListener('appinstalled', function(){ window.__dp=null; var b=document.getElementById('installBtn'); if(b)b.remove(); });
window.kInstall = function(){
  if(window.__dp){ window.__dp.prompt(); window.__dp=null; }
  else if(window.matchMedia('(display-mode: standalone)').matches || navigator.standalone){
    if(window.toast) toast('The app is already installed.');
  } else {
    if(window.toast) toast('To install: open your browser menu (⋮) and choose “Install app” or “Add to Home screen”.');
    else alert('To install: open your browser menu and choose “Install app” / “Add to Home Screen”.');
  }
  return false;
};
</script>
<script>
(function(){
  // ---- Generic client-side paginator: any <table data-paginate="25"> gets paged ----
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
(function(){
  // ---- Column chooser: any <table data-colpick> gets a "Columns" toggle (persisted) ----
  document.querySelectorAll('table[data-colpick]').forEach(function(tbl){
    if(!tbl.tHead||!tbl.tHead.rows.length) return;
    var heads=Array.prototype.slice.call(tbl.tHead.rows[0].cells);
    var key='cols:'+location.pathname;
    var hidden={}; try{ hidden=JSON.parse(localStorage.getItem(key)||'{}'); }catch(e){}
    function apply(){
      heads.forEach(function(th,i){
        var hide=!!hidden[i]; th.style.display=hide?'none':'';
        Array.prototype.forEach.call(tbl.tBodies,function(tb){
          Array.prototype.forEach.call(tb.rows,function(row){ if(row.cells[i]&&!row.cells[i].hasAttribute('colspan')) row.cells[i].style.display=hide?'none':''; });
        });
      });
    }
    var wrap=document.createElement('div'); wrap.className='colpick';
    var btn=document.createElement('button'); btn.type='button'; btn.className='colpick-btn'; btn.innerHTML='&#9881; Columns';
    // Compact/dense rows toggle (persisted per page).
    var dkey='compact:'+location.pathname;
    var dense=false; try{ dense=localStorage.getItem(dkey)==='1'; }catch(e){}
    var dbtn=document.createElement('button'); dbtn.type='button'; dbtn.className='colpick-btn';
    function dsync(){ tbl.classList.toggle('compact',dense); dbtn.innerHTML=(dense?'&#9632;':'&#9634;')+' Compact'; }
    dbtn.addEventListener('click',function(){ dense=!dense; try{localStorage.setItem(dkey,dense?'1':'0');}catch(e){} dsync(); });
    dsync();
    var menu=document.createElement('div'); menu.className='colpick-menu';
    heads.forEach(function(th,i){
      if(th.hasAttribute('data-nocolpick')) return; // e.g. Actions / checkbox column stays fixed
      var label=(th.textContent||'').trim()||('Column '+(i+1));
      var lab=document.createElement('label');
      var cb=document.createElement('input'); cb.type='checkbox'; cb.checked=!hidden[i];
      cb.addEventListener('change',function(){ if(cb.checked)delete hidden[i]; else hidden[i]=1; try{localStorage.setItem(key,JSON.stringify(hidden));}catch(e){} apply(); });
      lab.appendChild(cb); lab.appendChild(document.createTextNode(' '+label)); menu.appendChild(lab);
    });
    wrap.appendChild(btn); wrap.appendChild(menu); wrap.appendChild(dbtn);
    tbl.parentNode.insertBefore(wrap,tbl);
    btn.addEventListener('click',function(e){ e.stopPropagation(); menu.classList.toggle('open'); });
    menu.addEventListener('click',function(e){ e.stopPropagation(); });
    document.addEventListener('click',function(){ menu.classList.remove('open'); });
    apply();
  });
})();
(function(){
  var t=document.getElementById('menuToggle'), s=document.getElementById('sidebar'), o=document.getElementById('navOverlay');
  if(!t||!s||!o) return;
  function open(){s.classList.add('open');o.classList.add('show');}
  function close(){s.classList.remove('open');o.classList.remove('show');}
  t.addEventListener('click',function(){ s.classList.contains('open')?close():open(); });
  o.addEventListener('click',close);
  s.querySelectorAll('a').forEach(function(a){a.addEventListener('click',close);});
})();
</script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
(function(){
  var LABELS={'layout-dashboard':'Dashboard','landmark':'Bank Valuations','shield-check':'Insurance Valuations','bar-chart-3':'Analytics','settings':'Settings','sun-moon':'Toggle light / dark','user-round':'My profile','log-out':'Log out','sliders-horizontal':'Filters','download':'Download CSV','plus':'Add new','trash-2':'Delete','pencil':'Edit','copy':'Duplicate','eye':'Preview report','printer':'Print / PDF','save':'Save','x':'Cancel','mail':'Email','building-2':'Company settings','users':'Clients','shield':'Insurers','tags':'Valuation types','user-cog':'Users','scroll-text':'Audit log','key-round':'Portal users'};
  function tips(){
    document.querySelectorAll('.lucide').forEach(function(svg){
      var name=''; svg.classList.forEach(function(c){ if(c.indexOf('lucide-')===0) name=c.slice(7); });
      var el=svg.closest('a,button'); if(!el) return;
      if(el.getAttribute('title')) return;
      var txt=(el.textContent||'').trim();
      el.setAttribute('title', txt || LABELS[name] || name.replace(/-/g,' '));
    });
  }
  function icons(){ if(window.lucide) lucide.createIcons(); tips(); }
  icons(); document.addEventListener('DOMContentLoaded', icons);
  window.refreshIcons = icons;
})();
(function(){
  // ---- Toasts ----
  window.toast = function(msg, type){
    var box = document.getElementById('toasts'); if(!box) return alert(msg);
    var t = document.createElement('div'); t.className = 'toast' + (type==='err'?' err':''); t.textContent = msg;
    box.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(function(){t.remove();},400); }, 4000);
  };
  if (window.__flash) toast(window.__flash.msg, window.__flash.type);

  // ---- Top progress bar ----
  var TB=document.getElementById('topbar'), TBF=TB?TB.querySelector('.topbar-fill'):null, tbT, tbV=0;
  function tbStart(){ if(!TB)return; TB.classList.add('on'); tbV=10; TBF.style.width='10%'; clearInterval(tbT);
    tbT=setInterval(function(){ tbV+=Math.max(0.4,(92-tbV)*0.07); if(tbV>92)tbV=92; TBF.style.width=tbV+'%'; },180); }
  function tbDone(){ if(!TB)return; clearInterval(tbT); TBF.style.width='100%'; setTimeout(function(){ TB.classList.remove('on'); TBF.style.width='0'; },400); }

  // ---- Processing overlay ----
  var sp = document.getElementById('spinner');
  function showSpinner(msg){ if(!sp)return; if(msg){var m=sp.querySelector('.sp-msg'); if(m)m.textContent=msg;} sp.classList.add('on'); }
  function hideSpinner(){ if(sp) sp.classList.remove('on'); }

  // POST forms: bar + overlay (saving)
  document.querySelectorAll('form').forEach(function(f){
    if ((f.getAttribute('method')||'get').toLowerCase()==='post'){
      f.addEventListener('submit', function(){ tbStart(); setTimeout(function(){ showSpinner('Saving…'); }, 60); });
    }
  });
  // PDF / print: bar + overlay
  document.querySelectorAll('a[href*="print.php"],a[href*="export.php"],a[href*="backup.php"]').forEach(function(a){
    a.addEventListener('click', function(e){ if(e.defaultPrevented) return; tbStart(); showSpinner('Generating…'); setTimeout(function(){ hideSpinner(); tbDone(); }, 5000); });
  });
  // Internal navigation links: just the top bar
  document.querySelectorAll('a[href]').forEach(function(a){
    var href=a.getAttribute('href')||'';
    if(a.target==='_blank' || href.charAt(0)==='#' || href.indexOf('javascript:')===0 || a.onclick) return;
    a.addEventListener('click', function(e){ if(e.metaKey||e.ctrlKey)return; tbStart(); });
  });
  window.addEventListener('pageshow', function(){ hideSpinner(); tbDone(); });
  window.addEventListener('beforeunload', function(){ tbStart(); });

  // ---- Theme toggle ----
  var btn = document.getElementById('themeBtn');
  if (btn) btn.addEventListener('click', function(){
    var cur = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', cur);
    try { localStorage.setItem('theme', cur); } catch(e){}
  });
})();
</script>
<script>window.NOTIF_CFG={url:'<?= url('notifications.php') ?>',csrf:'<?= e(csrf_token()) ?>'};</script>
<script src="<?= url('notif.js') ?>"></script>
</body>
</html>
<?php }
