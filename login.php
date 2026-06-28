<?php
require_once __DIR__ . '/lib.php';
if (current_user()) redirect('bank_list.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $res = attempt_login($email, $pass);
    if ($res === true) redirect('dashboard.php');
    $error = $res === 'locked'
        ? 'Too many failed attempts. Please wait 15 minutes and try again.'
        : 'Invalid email or password.';
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(APP_NAME) ?></title>
<style>
 body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#0f1216;color:#e6e9ee;display:flex;min-height:100vh;align-items:center;justify-content:center}
 .box{background:#171c22;border:1px solid #262d36;border-radius:12px;padding:30px;width:340px}
 h1{font-size:20px;margin:0 0 4px} p.sub{color:#9aa4b2;margin:0 0 22px;font-size:13px}
 label{display:block;font-size:12px;color:#9aa4b2;margin:14px 0 5px}
 input{width:100%;background:#0f1419;border:1px solid #262d36;color:#e6e9ee;padding:10px;border-radius:8px;font-size:14px}
 button{width:100%;margin-top:20px;background:#d41d1d;color:#fff;border:0;padding:11px;border-radius:8px;font-size:15px;cursor:pointer}
 .err{background:#3d0f0f;border:1px solid #7a1c1c;color:#f5c0c0;padding:9px 12px;border-radius:8px;font-size:13px;margin-top:14px}
</style></head>
<body>
<form class="box" method="post">
  <?= csrf_field() ?>
  <h1><?= e(APP_NAME) ?></h1>
  <p class="sub">Automobile Valuers & Assessors</p>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <label>Email</label>
  <input type="email" name="email" required autofocus>
  <label>Password</label>
  <input type="password" name="password" required>
  <button type="submit">Sign in</button>
</form>
</body></html>
