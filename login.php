<?php
require_once __DIR__ . '/lib.php';
if (current_user()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $res = attempt_login($email, $pass);
    if ($res === true) {
        if (system_locked() && !is_superadmin()) { logout(); $error = denied_message(); }
        else { if (setting('banner_enabled') === '1' && !is_superadmin()) $_SESSION['avail_notice_pending'] = 1; redirect('dashboard.php'); }
    } else {
        $error = $res === 'locked'
            ? 'Too many failed attempts. Please wait 15 minutes and try again.'
            : 'Invalid email or password.';
    }
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/logo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--accent:#d41d1d;--accent2:#2563eb}
  *{box-sizing:border-box}
  html,body{height:100%}
  body{margin:0;font-family:'Plus Jakarta Sans',system-ui,Segoe UI,Roboto,Arial,sans-serif;-webkit-font-smoothing:antialiased;
       background:#070a0e;color:#e6e9ee;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
  /* animated aurora background */
  .aurora{position:fixed;inset:0;overflow:hidden;z-index:0}
  .blob{position:absolute;border-radius:50%;filter:blur(70px);opacity:.55;animation:float 18s ease-in-out infinite}
  .b1{width:520px;height:520px;background:radial-gradient(circle,#d41d1d,transparent 70%);top:-140px;left:-120px}
  .b2{width:480px;height:480px;background:radial-gradient(circle,#2563eb,transparent 70%);bottom:-160px;right:-120px;animation-delay:-6s}
  .b3{width:380px;height:380px;background:radial-gradient(circle,#7a1cff,transparent 70%);top:40%;left:55%;animation-delay:-11s}
  @keyframes float{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(40px,-30px) scale(1.08)}66%{transform:translate(-30px,25px) scale(.95)}}
  .grid-ovl{position:fixed;inset:0;z-index:1;background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:42px 42px;mask-image:radial-gradient(circle at 50% 50%,#000,transparent 80%)}
  /* glass card */
  .card{position:relative;z-index:2;width:390px;max-width:92vw;padding:34px 32px 30px;border-radius:22px;
        background:rgba(20,25,32,.62);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
        border:1px solid rgba(255,255,255,.10);box-shadow:0 30px 80px rgba(0,0,0,.55);
        animation:cardIn .7s cubic-bezier(.2,.8,.2,1)}
  .card.shake{animation:shake .45s}
  @keyframes cardIn{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:none}}
  @keyframes shake{10%,90%{transform:translateX(-2px)}20%,80%{transform:translateX(4px)}30%,50%,70%{transform:translateX(-8px)}40%,60%{transform:translateX(8px)}}
  .logo{display:block;max-width:170px;width:100%;margin:0 auto 14px;background:#fff;padding:10px 12px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.3)}
  .sub{text-align:center;color:#9aa4b2;margin:0 0 22px;font-size:13px;letter-spacing:.2px}
  .field{position:relative;margin-bottom:14px;opacity:0;animation:rise .6s ease forwards}
  .field:nth-of-type(1){animation-delay:.15s}.field:nth-of-type(2){animation-delay:.25s}
  @keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  .field i{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#7d8794;pointer-events:none;transition:color .2s}
  .field input{width:100%;background:rgba(10,14,19,.8);border:1px solid rgba(255,255,255,.10);color:#e6e9ee;
        padding:13px 14px 13px 42px;border-radius:12px;font-size:14px;font-family:inherit;transition:border-color .2s,box-shadow .2s}
  .field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,29,29,.18)}
  .field input:focus + i{color:var(--accent)}
  .btn{width:100%;margin-top:8px;background:linear-gradient(100deg,#d41d1d,#ff4242);color:#fff;border:0;
        padding:14px;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
        box-shadow:0 10px 28px rgba(212,29,29,.35);transition:transform .15s,box-shadow .15s,filter .15s;opacity:0;animation:rise .6s .35s ease forwards}
  .btn:hover{transform:translateY(-2px);box-shadow:0 14px 36px rgba(212,29,29,.5);filter:brightness(1.06)}
  .btn:active{transform:translateY(0)}
  .btn i{width:18px;height:18px;transition:transform .2s}.btn:hover i{transform:translateX(4px)}
  .btn.loading{pointer-events:none}.btn.loading .lbl{opacity:.6}
  .err{display:flex;align-items:center;gap:8px;background:rgba(122,28,28,.35);border:1px solid #7a1c1c;color:#f5c0c0;
       padding:10px 12px;border-radius:10px;font-size:13px;margin-bottom:16px;animation:rise .3s ease}
  .err i{width:16px;height:16px;flex:0 0 16px}
  .foot{text-align:center;color:#5b6573;font-size:11px;margin-top:18px}
  h1.t{text-align:center;font-size:20px;font-weight:800;margin:0 0 2px;letter-spacing:-.02em}
</style>
</head>
<body>
<div class="aurora"><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div></div>
<div class="grid-ovl"></div>

<form class="card <?= $error ? 'shake' : '' ?>" method="post" id="loginForm">
  <?= csrf_field() ?>
  <img class="logo" src="<?= url('assets/logo2.png') ?>" alt="<?= e(APP_NAME) ?>">
  <p class="sub">Automobile Valuers &amp; Assessors</p>

  <?php if ($error): ?>
    <div class="err"><i data-lucide="alert-triangle"></i><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <div class="field">
    <input type="email" name="email" placeholder="Email address" required autofocus>
    <i data-lucide="mail"></i>
  </div>
  <div class="field">
    <input type="password" name="password" placeholder="Password" required>
    <i data-lucide="lock"></i>
  </div>
  <button class="btn" type="submit" id="loginBtn"><span class="lbl">Sign in</span><i data-lucide="arrow-right"></i></button>

  <div class="foot">© <?= date('Y') ?> <?= e(APP_NAME) ?> · Secure access</div>
  <div class="foot" style="margin-top:6px"><a href="<?= url('portal_login.php') ?>" style="color:#9aa4b2;text-decoration:underline">Client portal →</a></div>
</form>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
  if (window.lucide) lucide.createIcons();
  document.getElementById('loginForm').addEventListener('submit', function(){
    var b = document.getElementById('loginBtn'); b.classList.add('loading'); b.querySelector('.lbl').textContent = 'Signing in…';
  });
</script>
<div style="position:fixed;left:0;right:0;bottom:0;text-align:center;padding:10px;font-size:12px;color:rgba(255,255,255,.7)"><?= dev_credit() ?></div>
</body></html>
