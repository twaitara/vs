<?php
require_once __DIR__ . '/portal_layout.php';
require_client();

$c   = current_client();
$cid = (int)$c['client_id'];
$me  = (int)$c['id'];

// Only portal admins may manage their company's officers.
if (!client_is_admin()) { flash('You do not have access to team management.', 'err'); redirect('portal.php'); }

/** Ensure a target user belongs to this admin's company (prevents cross-company tampering). */
function team_owns(int $uid, int $cid): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM client_users WHERE id=? AND client_id=?');
    $st->execute([$uid, $cid]);
    return (int)$st->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $uid    = (int)($_POST['id'] ?? 0);
    $errors = [];

    if ($action === 'create') {
        $pass = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!$errors) {
            $dup = db()->prepare('SELECT COUNT(*) FROM client_users WHERE email = ?'); $dup->execute([$email]);
            if ($dup->fetchColumn() > 0) $errors[] = 'That email already has a portal login.';
        }
        if (!$errors) {
            $st = db()->prepare('INSERT INTO client_users (client_id,name,email,password,role,active,created_at,updated_at) VALUES (?,?,?,?,\'officer\',1,NOW(),NOW())');
            $st->execute([$cid, $name, $email, password_hash($pass, PASSWORD_BCRYPT)]);
            audit('portal_team_create', 'client_user', (int)db()->lastInsertId(), $email);
            flash('Officer added.'); redirect('portal_team.php');
        }
    } elseif ($action === 'update' && $uid && team_owns($uid, $cid)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (!$errors) {
            db()->prepare('UPDATE client_users SET name=?, email=?, updated_at=NOW() WHERE id=? AND client_id=?')->execute([$name, $email, $uid, $cid]);
            audit('portal_team_update', 'client_user', $uid, $email);
            flash('Officer updated.'); redirect('portal_team.php');
        }
    } elseif ($action === 'toggle' && $uid && $uid !== $me && team_owns($uid, $cid)) {
        db()->prepare('UPDATE client_users SET active = 1 - active, updated_at=NOW() WHERE id=? AND client_id=?')->execute([$uid, $cid]);
        audit('portal_team_toggle', 'client_user', $uid); flash('Status changed.'); redirect('portal_team.php');
    } elseif ($action === 'resetpw' && $uid && team_owns($uid, $cid)) {
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) { flash('Password too short.', 'err'); redirect('portal_team.php'); }
        db()->prepare('UPDATE client_users SET password=?, updated_at=NOW() WHERE id=? AND client_id=?')->execute([password_hash($pass, PASSWORD_BCRYPT), $uid, $cid]);
        audit('portal_team_resetpw', 'client_user', $uid); flash('Password reset.'); redirect('portal_team.php');
    }
    if ($errors) flash(implode(' ', $errors), 'err');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) { $st = db()->prepare('SELECT * FROM client_users WHERE id=? AND client_id=?'); $st->execute([$editId, $cid]); $edit = $st->fetch() ?: null; }

$st = db()->prepare('SELECT * FROM client_users WHERE client_id=? ORDER BY role DESC, name');
$st->execute([$cid]);
$rows = $st->fetchAll();

portal_header('My Team', 'team');
?>
<h1 class="pt">My Team</h1>
<p class="psub">Add and manage officers in your company. Officers can request valuations and see only their own requests.</p>

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:18px" class="teamgrid">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <h3 style="margin:0 0 12px"><?= $edit ? 'Edit Officer' : 'Add Officer' ?></h3>
    <div class="f"><label class="f">Name</label><input name="name" value="<?= e($edit['name'] ?? '') ?>"></div>
    <div class="f"><label class="f">Email (login)</label><input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
    <?php if (!$edit): ?>
      <div class="f"><label class="f">Temporary password</label><input type="password" name="password" required minlength="6"></div>
    <?php endif; ?>
    <button class="btn" type="submit"><i data-lucide="save"></i><?= $edit ? 'Save' : 'Add officer' ?></button>
    <?php if ($edit): ?><a class="btn sec" href="<?= url('portal_team.php') ?>">Cancel</a><?php endif; ?>
  </form>

  <div class="card">
    <h3 style="margin:0 0 12px">Team members</h3>
    <table data-paginate="25" class="list">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $isAdmin = ($r['role'] ?? 'officer') === 'admin'; ?>
        <tr>
          <td><?= e($r['name'] ?: '—') ?><?= (int)$r['id'] === $me ? ' <span class="muted">(you)</span>' : '' ?></td>
          <td><?= e($r['email']) ?></td>
          <td><?= $isAdmin ? '<span class="badge b-green">Portal Admin</span>' : 'Officer' ?></td>
          <td><?= (int)$r['active'] === 1 ? '<span style="color:#3ddc84">Active</span>' : '<span style="color:#f5a3a3">Disabled</span>' ?></td>
          <td>
            <?php if (!$isAdmin): ?>
              <a class="rbtn" href="?edit=<?= (int)$r['id'] ?>"><i data-lucide="pencil"></i>Edit</a>
              <button class="rbtn" type="button" onclick="tReset(<?= (int)$r['id'] ?>,'<?= e($r['email']) ?>')"><i data-lucide="key-round"></i>Reset PW</button>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="rbtn" type="submit"><?= (int)$r['active'] === 1 ? 'Disable' : 'Enable' ?></button></form>
            <?php else: ?>
              <span class="muted" style="font-size:12px">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<form method="post" id="tResetForm" style="display:none"><?= csrf_field() ?><input type="hidden" name="action" value="resetpw"><input type="hidden" name="id" id="tId"><input type="hidden" name="password" id="tPw"></form>
<script>
function tReset(id,email){var p=prompt('New password for '+email+' (min 6):');if(p===null)return;if(p.length<6){alert('Too short');return;}document.getElementById('tId').value=id;document.getElementById('tPw').value=p;document.getElementById('tResetForm').submit();}
</script>
<style>@media(max-width:860px){.teamgrid{grid-template-columns:1fr!important}}</style>
<?php portal_footer();
