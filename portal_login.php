<?php
require_once __DIR__ . '/lib.php';
if (current_client()) redirect('portal.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $res = attempt_client_login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    if ($res === true) {
        if (system_locked()) { client_logout(); $error = denied_message(); }
        else { if (setting('banner_enabled') === '1') $_SESSION['avail_notice_pending'] = 1; redirect('portal.php'); }
    } else {
        $error = $res === 'locked' ? 'Too many attempts. Try again in 15 minutes.' : 'Invalid email or password.';
    }
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Portal · Sign in</title>
<link rel="icon" type="image/png" href="<?= url('assets/logo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box}html,body{height:100%}
  body{margin:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased;background:#070a0e;color:#e6e9ee;display:flex;align-items:center;justify-content:center;overflow:hidden}
  .aurora{position:fixed;inset:0;overflow:hidden;z-index:0}
  .blob{position:absolute;border-radius:50%;filter:blur(70px);opacity:.5;animation:float 18s ease-in-out infinite}
  .b1{width:520px;height:520px;background:radial-gradient(circle,#2563eb,transparent 70%);top:-140px;left:-120px}
  .b2{width:480px;height:480px;background:radial-gradient(circle,#d41d1d,transparent 70%);bottom:-160px;right:-120px;animation-delay:-7s}
  @keyframes float{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(40px,-30px) scale(1.08)}}
  .card{position:relative;z-index:2;width:380px;max-width:92vw;padding:34px 30px;border-radius:20px;background:rgba(20,25,32,.65);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.10);box-shadow:0 30px 80px rgba(0,0,0,.55);animation:in .6s cubic-bezier(.2,.8,.2,1)}
  .card.shake{animation:shake .45s}
  @keyframes in{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:none}}
  @keyframes shake{20%,80%{transform:translateX(4px)}30%,50%,70%{transform:translateX(-8px)}40%,60%{transform:translateX(8px)}}
  .logo{display:block;max-width:160px;margin:0 auto 8px;background:#fff;padding:9px;border-radius:10px}
  .tag{text-align:center;color:#9aa4b2;font-size:13px;margin:0 0 22px}.tag b{color:#fff;display:block;font-size:15px;margin-bottom:2px}
  .field{position:relative;margin-bottom:13px}
  .field i{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#7d8794}
  .field input{width:100%;background:rgba(10,14,19,.8);border:1px solid rgba(255,255,255,.10);color:#e6e9ee;padding:13px 14px 13px 42px;border-radius:11px;font-size:14px;font-family:inherit}
  .field input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.2)}
  .btn{width:100%;margin-top:6px;background:linear-gradient(100deg,#2563eb,#4f8bff);color:#fff;border:0;padding:14px;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 10px 28px rgba(37,99,235,.35);transition:transform .15s}
  .btn:hover{transform:translateY(-2px)} .btn i{width:18px;height:18px}
  .err{display:flex;gap:8px;align-items:center;background:rgba(122,28,28,.35);border:1px solid #7a1c1c;color:#f5c0c0;padding:10px 12px;border-radius:10px;font-size:13px;margin-bottom:14px}.err i{width:16px;height:16px}
  .foot{text-align:center;color:#5b6573;font-size:11px;margin-top:16px}
</style></head>
<body>
<div class="aurora"><div class="blob b1"></div><div class="blob b2"></div></div>
<form class="card <?= $error ? 'shake' : '' ?>" method="post">
  <?= csrf_field() ?>
  <img class="logo" src="<?= url('assets/logo2.png') ?>" alt="Kennet">
  <p class="tag"><b>Client Portal</b>Access your vehicle valuations</p>
  <?php if ($error): ?><div class="err"><i data-lucide="alert-triangle"></i><span><?= e($error) ?></span></div><?php endif; ?>
  <div class="field"><input type="email" name="email" placeholder="Email address" required autofocus><i data-lucide="mail"></i></div>
  <div class="field"><input type="password" name="password" placeholder="Password" required><i data-lucide="lock"></i></div>
  <button class="btn" type="submit"><span>Sign in</span><i data-lucide="arrow-right"></i></button>
  <div class="foot">© <?= date('Y') ?> Kennet Automobile Valuers · Secure portal</div>
</form>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script><script>if(window.lucide)lucide.createIcons();</script>
</body></html>
