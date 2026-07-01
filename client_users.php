<?php
require_once __DIR__ . '/layout.php';
require_admin();

// Graceful guard: the portal needs schema_v4.sql to have been run.
if (!column_exists('client_users', 'id')) {
    layout_header('Portal Users', 'settings');
    settings_nav('portal');
    echo '<div class="card" style="max-width:640px"><h3 style="margin-top:0">One step needed</h3>'
       . '<p class="muted">The client portal table hasn\'t been created yet. In phpMyAdmin, open your database, go to the <b>SQL</b> tab, and run the contents of <code>schema_v4.sql</code>. Then reload this page.</p></div>';
    layout_footer();
    exit;
}

/** Email portal credentials to a client contact. Returns true if accepted by the mailer. */
function portal_email(string $to, ?string $name, string $password): bool {
    $company = setting('company_name', 'Kennet Automobile Valuers');
    $from    = setting('company_email', 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $portal  = $scheme . '://' . $host . url('portal_login.php');
    $hi  = $name ? 'Dear ' . $name . ',' : 'Hello,';
    $subject = 'Your ' . $company . ' Client Portal Login';
    $body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.6">'
        . '<p>' . htmlspecialchars($hi) . '</p>'
        . '<p>A client portal account has been created for you to view the vehicle valuations carried out for your company by ' . htmlspecialchars($company) . '.</p>'
        . '<table cellpadding="0" cellspacing="0" style="margin:14px 0"><tr><td style="padding:4px 0"><b>Portal&nbsp;link:</b></td><td style="padding:4px 0 4px 12px"><a href="' . $portal . '">' . $portal . '</a></td></tr>'
        . '<tr><td style="padding:4px 0"><b>Username:</b></td><td style="padding:4px 0 4px 12px">' . htmlspecialchars($to) . '</td></tr>'
        . '<tr><td style="padding:4px 0"><b>Password:</b></td><td style="padding:4px 0 4px 12px">' . htmlspecialchars($password) . '</td></tr></table>'
        . '<p>Please keep these details secure. You can sign in any time using the link above.</p>'
        . '<p>Regards,<br>' . htmlspecialchars($company) . '</p></div>';
    $headers = "From: $company <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    return @mail($to, $subject, $body, $headers);
}

$clients = lookup('clients'); // id => name

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $cid    = (int)($_POST['client_id'] ?? 0);
    $uid    = (int)($_POST['id'] ?? 0);
    $prole  = ($_POST['role'] ?? 'officer') === 'admin' ? 'admin' : 'officer';
    $errors = [];

    if ($action === 'create') {
        $pass = $_POST['password'] ?? '';
        if (!$cid || !isset($clients[$cid])) $errors[] = 'Choose a client.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!$errors) {
            $dup = db()->prepare('SELECT COUNT(*) FROM client_users WHERE email = ?'); $dup->execute([$email]);
            if ($dup->fetchColumn() > 0) $errors[] = 'That email already has a portal login.';
        }
        if (!$errors) {
            $st = db()->prepare('INSERT INTO client_users (client_id,name,email,password,active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())');
            $st->execute([$cid, $name, $email, password_hash($pass, PASSWORD_BCRYPT)]);
            $nid = (int)db()->lastInsertId();
            if (column_exists('client_users', 'role')) db()->prepare('UPDATE client_users SET role=? WHERE id=?')->execute([$prole, $nid]);
            audit('create', 'client_user', $nid, $email);
            $msg = 'Portal login created.';
            if (!empty($_POST['sendmail'])) {
                $msg .= portal_email($email, $name, $pass) ? ' Credentials emailed to ' . $email . '.' : ' (Email could not be sent — check mail settings.)';
            }
            flash($msg); redirect('client_users.php');
        }
    } elseif ($action === 'update' && $uid) {
        if (!$cid || !isset($clients[$cid])) $errors[] = 'Choose a client.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (!$errors) {
            $st = db()->prepare('UPDATE client_users SET client_id=?, name=?, email=?, updated_at=NOW() WHERE id=?');
            $st->execute([$cid, $name, $email, $uid]);
            if (column_exists('client_users', 'role')) db()->prepare('UPDATE client_users SET role=? WHERE id=?')->execute([$prole, $uid]);
            audit('update', 'client_user', $uid, $email);
            flash('Portal login updated.'); redirect('client_users.php');
        }
    } elseif ($action === 'toggle' && $uid) {
        db()->prepare('UPDATE client_users SET active = 1 - active, updated_at=NOW() WHERE id=?')->execute([$uid]);
        audit('toggle_active', 'client_user', $uid); flash('Status changed.'); redirect('client_users.php');
    } elseif ($action === 'resetpw' && $uid) {
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) { flash('Password too short.', 'err'); redirect('client_users.php'); }
        db()->prepare('UPDATE client_users SET password=?, updated_at=NOW() WHERE id=?')->execute([password_hash($pass, PASSWORD_BCRYPT), $uid]);
        audit('reset_password', 'client_user', $uid); flash('Password reset.'); redirect('client_users.php');
    } elseif ($action === 'emaillogin' && $uid) {
        $st = db()->prepare('SELECT * FROM client_users WHERE id=?'); $st->execute([$uid]); $cu = $st->fetch();
        if ($cu) {
            $new = bin2hex(random_bytes(4)); // fresh 8-char password (old one is unrecoverable)
            db()->prepare('UPDATE client_users SET password=?, updated_at=NOW() WHERE id=?')->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            $ok = portal_email($cu['email'], $cu['name'], $new);
            audit('email_login', 'client_user', $uid, $cu['email']);
            flash($ok ? ('New password generated and emailed to ' . $cu['email'] . '.') : 'Password was reset but the email failed to send.', $ok ? 'ok' : 'err');
        }
        redirect('client_users.php');
    } elseif ($action === 'delete' && $uid) {
        db()->prepare('DELETE FROM client_users WHERE id=?')->execute([$uid]);
        audit('delete', 'client_user', $uid); flash('Portal login deleted.'); redirect('client_users.php');
    }
    if ($errors) flash(implode(' ', $errors), 'err');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) { $st = db()->prepare('SELECT * FROM client_users WHERE id=?'); $st->execute([$editId]); $edit = $st->fetch() ?: null; }

$rows = db()->query('SELECT * FROM client_users ORDER BY name')->fetchAll();

layout_header('Portal Users', 'settings');
settings_nav('portal');
?>
<p class="muted" style="margin-top:-8px">Give a client (e.g. a bank) a login to the customer portal. They will only see valuations belonging to their company.</p>
<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:18px">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <h3 style="margin-top:0"><?= $edit ? 'Edit Portal Login' : 'Add Portal Login' ?></h3>
    <div class="f"><label class="f">Client (company)</label><select name="client_id">
      <option value="">-- select --</option>
      <?php foreach ($clients as $id => $nm): ?><option value="<?= $id ?>" <?= (int)($edit['client_id'] ?? 0) === $id ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
    </select></div>
    <div class="f"><label class="f">Contact Name</label><input name="name" value="<?= e($edit['name'] ?? '') ?>"></div>
    <div class="f"><label class="f">Portal Role</label><select name="role">
      <option value="officer" <?= ($edit['role'] ?? 'officer') === 'officer' ? 'selected' : '' ?>>Officer (requests &amp; sees own)</option>
      <option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Portal Admin (manages company)</option>
    </select></div>
    <div class="f"><label class="f">Email (login)</label><input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
    <?php if (!$edit): ?>
      <div class="f"><label class="f">Password</label><input type="password" name="password" required minlength="6"></div>
      <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--mut);margin-top:4px"><input type="checkbox" name="sendmail" value="1" checked> Email these login details to the address above</label>
    <?php endif; ?>
    <div style="margin-top:12px">
      <button class="btn" type="submit"><i data-lucide="save"></i><?= $edit ? 'Save' : 'Create' ?></button>
      <?php if ($edit): ?><a class="btn sec" href="<?= url('client_users.php') ?>">Cancel</a><?php endif; ?>
    </div>
  </form>

  <div class="card">
    <h3 style="margin-top:0">Portal Logins</h3>
    <table data-paginate="25" class="list">
      <thead><tr><th>Client</th><th>Contact</th><th>Role</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">No portal logins yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($clients[$r['client_id']] ?? ('#' . $r['client_id'])) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= ($r['role'] ?? 'officer') === 'admin' ? '<span class="badge b-green">Portal Admin</span>' : 'Officer' ?></td>
          <td><?= e($r['email']) ?></td>
          <td><?= ((int)$r['active'] === 1) ? '<span style="color:#36a35f">Active</span>' : '<span style="color:#d41d1d">Disabled</span>' ?></td>
          <td class="actions">
            <a class="rbtn" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="rbtn" type="submit"><?= (int)$r['active'] === 1 ? 'Disable' : 'Enable' ?></button></form>
            <button class="rbtn" type="button" onclick="cuReset(<?= (int)$r['id'] ?>,'<?= e($r['email']) ?>')">Reset PW</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Generate a new password and email the login to <?= e($r['email']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="emaillogin"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="rbtn" type="submit"><i data-lucide="mail"></i>Email login</button></form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<form method="post" id="cuResetForm" style="display:none"><?= csrf_field() ?><input type="hidden" name="action" value="resetpw"><input type="hidden" name="id" id="cuId"><input type="hidden" name="password" id="cuPw"></form>
<script>
function cuReset(id,email){var p=prompt('New portal password for '+email+' (min 6):');if(p===null)return;if(p.length<6){alert('Too short');return;}document.getElementById('cuId').value=id;document.getElementById('cuPw').value=p;document.getElementById('cuResetForm').submit();}
</script>
<?php layout_footer();
