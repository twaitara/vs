<?php
require_once __DIR__ . '/layout.php';
require_login();

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $errors = [];

    if ($action === 'profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '') $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (!$errors) {
            $dup = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $dup->execute([$email, $me['id']]);
            if ($dup->fetchColumn() > 0) $errors[] = 'That email is already in use.';
        }
        if (!$errors) {
            $st = db()->prepare('UPDATE users SET name=?, email=?, updated_at=NOW() WHERE id=?');
            $st->execute([$name, $email, $me['id']]);
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            audit('update_profile', 'user', $me['id']);
            flash('Profile updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $cur = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        $cf  = $_POST['confirm'] ?? '';
        $st = db()->prepare('SELECT password FROM users WHERE id=?'); $st->execute([$me['id']]);
        $hash = $st->fetchColumn();
        if (!password_verify($cur, $hash)) $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($new !== $cf) $errors[] = 'New passwords do not match.';
        if (!$errors) {
            $st = db()->prepare('UPDATE users SET password=?, updated_at=NOW() WHERE id=?');
            $st->execute([password_hash($new, PASSWORD_BCRYPT), $me['id']]);
            audit('change_password', 'user', $me['id']);
            flash('Password changed.');
            redirect('profile.php');
        }
    }
    if ($errors) flash(implode(' ', $errors), 'err');
}

layout_header('My Profile', '');
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;max-width:760px">
  <form class="card" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h3 style="margin-top:0">Profile</h3>
    <div class="f"><label class="f">Name</label><input name="name" required value="<?= e($me['name'] ?? '') ?>"></div>
    <div class="f"><label class="f">Email</label><input type="email" name="email" required value="<?= e($me['email'] ?? '') ?>"></div>
    <div class="f"><label class="f">Role</label><input value="<?= e(ucfirst($me['role'] ?? '')) ?>" disabled></div>
    <button class="btn" type="submit" style="margin-top:10px">Save profile</button>
  </form>

  <form class="card" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h3 style="margin-top:0">Change Password</h3>
    <div class="f"><label class="f">Current password</label><input type="password" name="current" required></div>
    <div class="f"><label class="f">New password</label><input type="password" name="new" required minlength="6"></div>
    <div class="f"><label class="f">Confirm new password</label><input type="password" name="confirm" required minlength="6"></div>
    <button class="btn" type="submit" style="margin-top:10px">Change password</button>
  </form>
</div>
<?php layout_footer();
