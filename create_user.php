<?php
/**
 * One-off admin user creator.
 * Usage: open  https://yoursite/create_user.php  in a browser, fill the form.
 * DELETE THIS FILE after creating your user(s).
 */
require_once __DIR__ . '/lib.php';

$done = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($name && $email && $pass) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $now  = date('Y-m-d H:i:s');
        $st = db()->prepare('INSERT INTO users (name,email,password,created_at,updated_at) VALUES (?,?,?,?,?)');
        try {
            $st->execute([$name, $email, $hash, $now, $now]);
            $done = "User '$email' created. DELETE this file now, then go to login.php";
        } catch (Throwable $ex) {
            $done = 'Error: ' . $ex->getMessage();
        }
    }
}
?><!doctype html><meta charset="utf-8">
<title>Create user</title>
<body style="font-family:system-ui;max-width:420px;margin:60px auto">
<h2>Create admin user</h2>
<?php if ($done): ?><p style="background:#e7f7ec;border:1px solid #36a35f;padding:10px;border-radius:6px"><?= e($done) ?></p><?php endif; ?>
<form method="post">
  <p><label>Name<br><input name="name" required style="width:100%;padding:8px"></label></p>
  <p><label>Email<br><input type="email" name="email" required style="width:100%;padding:8px"></label></p>
  <p><label>Password<br><input type="password" name="password" required style="width:100%;padding:8px"></label></p>
  <button style="padding:10px 18px">Create</button>
</form>
<p style="color:#b00"><strong>Security:</strong> delete this file (create_user.php) immediately after use.</p>
</body>
