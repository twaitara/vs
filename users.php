<?php
require_once __DIR__ . '/layout.php';
require_admin();

$ROLES = ['admin' => 'Admin', 'valuer' => 'Valuer', 'viewer' => 'Viewer (read-only)'];
$me = current_user();

// ---- Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = ($_POST['role'] ?? 'valuer');
    if (!isset($ROLES[$role])) $role = 'valuer';
    $uid   = (int)($_POST['id'] ?? 0);
    $errors = [];

    // Protect the super admin account: only the super admin may change it.
    if ($uid && in_array($action, ['toggle', 'update', 'resetpw'], true)) {
        $tm = db()->prepare('SELECT email FROM users WHERE id=?'); $tm->execute([$uid]);
        if (strtolower(trim((string)$tm->fetchColumn())) === strtolower(SUPERADMIN_EMAIL) && !is_superadmin()) {
            flash('The super admin account is protected and can only be managed by the super admin.', 'err');
            redirect('users.php');
        }
    }

    if ($action === 'create') {
        $pass = $_POST['password'] ?? '';
        if ($name === '') $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!$errors) {
            $dup = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?'); $dup->execute([$email]);
            if ($dup->fetchColumn() > 0) $errors[] = 'That email is already in use.';
        }
        if (!$errors) {
            $st = db()->prepare('INSERT INTO users (name,email,role,active,password,created_at,updated_at) VALUES (?,?,?,1,?,NOW(),NOW())');
            $st->execute([$name, $email, $role, password_hash($pass, PASSWORD_BCRYPT)]);
            $newId = (int)db()->lastInsertId();
            if (column_exists('users', 'can_sign')) db()->prepare('UPDATE users SET can_sign=? WHERE id=?')->execute([!empty($_POST['can_sign']) ? 1 : 0, $newId]);
            audit('create', 'user', $newId, $email);
            flash('User created.');
            redirect('users.php');
        }
    } elseif ($action === 'update' && $uid) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        // Don't let an admin lock themselves out.
        if ($uid === (int)$me['id'] && $role !== 'admin') $errors[] = 'You cannot remove your own admin role.';
        if (!$errors) {
            $st = db()->prepare('UPDATE users SET name=?, email=?, role=?, updated_at=NOW() WHERE id=?');
            $st->execute([$name, $email, $role, $uid]);
            if (column_exists('users', 'can_sign')) db()->prepare('UPDATE users SET can_sign=? WHERE id=?')->execute([!empty($_POST['can_sign']) ? 1 : 0, $uid]);
            audit('update', 'user', $uid, $email);
            flash('User updated.');
            redirect('users.php');
        }
    } elseif ($action === 'toggle' && $uid) {
        if ($uid === (int)$me['id']) { flash('You cannot deactivate yourself.', 'err'); redirect('users.php'); }
        $st = db()->prepare('UPDATE users SET active = 1 - active, updated_at=NOW() WHERE id=?');
        $st->execute([$uid]);
        audit('toggle_active', 'user', $uid);
        flash('User status changed.');
        redirect('users.php');
    } elseif ($action === 'resetpw' && $uid) {
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) { flash('Password must be at least 6 characters.', 'err'); redirect('users.php'); }
        $st = db()->prepare('UPDATE users SET password=?, updated_at=NOW() WHERE id=?');
        $st->execute([password_hash($pass, PASSWORD_BCRYPT), $uid]);
        audit('reset_password', 'user', $uid);
        flash('Password reset.');
        redirect('users.php');
    }
    if ($errors) { flash(implode(' ', $errors), 'err'); }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) { $st = db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$editId]); $edit = $st->fetch() ?: null; }

$users = db()->query('SELECT * FROM users ORDER BY name')->fetchAll();

layout_header('Users', 'settings');
settings_nav('users');
?>
<div class="dash-grid" style="display:grid;grid-template-columns:1fr 1.4fr;gap:18px">
  <!-- Add / Edit form -->
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <h3 style="margin-top:0"><?= $edit ? 'Edit User' : 'Add User' ?></h3>
    <div class="f"><label class="f">Name</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
    <div class="f"><label class="f">Email</label><input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
    <div class="f"><label class="f">Role</label><select name="role">
      <?php foreach ($ROLES as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= ($edit['role'] ?? 'valuer') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select></div>
    <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--mut);margin:2px 0 4px"><input type="checkbox" name="can_sign" value="1" <?= !empty($edit['can_sign']) ? 'checked' : '' ?>> Signing mandate — can sign &amp; finalise reports</label>
    <?php if (!$edit): ?>
      <div class="f"><label class="f">Password</label><input type="password" name="password" required minlength="6"></div>
    <?php endif; ?>
    <div style="margin-top:12px">
      <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Create user' ?></button>
      <?php if ($edit): ?><a class="btn sec" href="<?= url('users.php') ?>">Cancel</a><?php endif; ?>
    </div>
  </form>

  <!-- Users list -->
  <div class="card">
    <h3 style="margin-top:0">All Users</h3>
    <table class="list">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Signing</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $usr): ?>
        <tr>
          <td><?= e($usr['name']) ?></td>
          <td><?= e($usr['email']) ?></td>
          <td><?= e(ucfirst($usr['role'] ?? '')) ?></td>
          <td><?= (!empty($usr['can_sign']) || ($usr['role'] ?? '') === 'admin') ? '<span class="badge b-green">Can sign</span>' : '<span class="muted">—</span>' ?></td>
          <td><?= ((int)($usr['active'] ?? 1) === 1)
                ? '<span style="color:#36a35f">Active</span>'
                : '<span style="color:#d41d1d">Disabled</span>' ?></td>
          <?php $rowSuper = strtolower(trim((string)($usr['email'] ?? ''))) === strtolower(SUPERADMIN_EMAIL);
                $locked = $rowSuper && !is_superadmin(); ?>
          <td class="actions">
            <?php if ($locked): ?>
              <span class="badge b-grey" title="Protected super-admin account"><i data-lucide="shield"></i> Protected</span>
            <?php else: ?>
              <a class="rbtn" href="?edit=<?= (int)$usr['id'] ?>">Edit</a>
              <?php if ((int)$usr['id'] !== (int)$me['id']): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                  <button class="rbtn" type="submit" style="cursor:pointer"><?= ((int)($usr['active'] ?? 1) === 1) ? 'Disable' : 'Enable' ?></button>
                </form>
              <?php endif; ?>
              <button class="rbtn" type="button" style="cursor:pointer" onclick="resetPw(<?= (int)$usr['id'] ?>,'<?= e($usr['name']) ?>')">Reset PW</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Reset password (hidden) -->
<form method="post" id="resetForm" style="display:none">
  <?= csrf_field() ?><input type="hidden" name="action" value="resetpw"><input type="hidden" name="id" id="resetId"><input type="hidden" name="password" id="resetPwVal">
</form>
<script>
function resetPw(id,name){
  var p = prompt('Set a new password for ' + name + ' (min 6 chars):');
  if(p===null) return;
  if(p.length<6){ alert('Password too short.'); return; }
  document.getElementById('resetId').value = id;
  document.getElementById('resetPwVal').value = p;
  document.getElementById('resetForm').submit();
}
</script>
<?php layout_footer();
