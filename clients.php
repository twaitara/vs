<?php
require_once __DIR__ . '/layout.php';
require_admin();

$TYPES = ['bank' => 'Bank', 'client' => 'Client (insurance)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $type = ($_POST['type'] ?? 'client') === 'bank' ? 'bank' : 'client';
    $id   = (int)($_POST['id'] ?? 0);
    $hasType = column_exists('clients', 'type');
    if ($name !== '') {
        if ($id) {
            if ($hasType) db()->prepare('UPDATE clients SET name=?, type=?, updated_at=NOW() WHERE id=?')->execute([$name, $type, $id]);
            else          db()->prepare('UPDATE clients SET name=?, updated_at=NOW() WHERE id=?')->execute([$name, $id]);
            flash('Client updated.');
        } else {
            $now = date('Y-m-d H:i:s');
            if ($hasType) db()->prepare('INSERT INTO clients (name,type,created_at,updated_at) VALUES (?,?,?,?)')->execute([$name, $type, $now, $now]);
            else          db()->prepare('INSERT INTO clients (name,created_at,updated_at) VALUES (?,?,?)')->execute([$name, $now, $now]);
            flash('Client added.');
        }
    }
    redirect('clients.php');
}
if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM clients WHERE id=?')->execute([(int)$_GET['delete']]);
    flash('Deleted.'); redirect('clients.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) { $st = db()->prepare('SELECT * FROM clients WHERE id=?'); $st->execute([$editId]); $edit = $st->fetch() ?: null; }
$hasType = column_exists('clients', 'type');
$rows = db()->query('SELECT * FROM clients ORDER BY name')->fetchAll();

layout_header('Clients', 'settings');
settings_nav('clients');
?>
<form class="card" method="post" style="max-width:480px">
  <?= csrf_field() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <div class="f"><label class="f"><?= $edit ? 'Edit' : 'Add' ?> client name</label><input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
  <div class="f"><label class="f">Type</label><select name="type">
    <?php foreach ($TYPES as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['type'] ?? 'client') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
  </select></div>
  <div style="margin-top:12px">
    <button class="btn" type="submit"><?= $edit ? 'Update' : 'Add' ?></button>
    <?php if ($edit): ?><a class="btn sec" href="<?= url('clients.php') ?>">Cancel</a><?php endif; ?>
  </div>
</form>
<table data-paginate="25" class="list">
  <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="4" class="muted">None yet.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['id']) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= e($TYPES[$hasType ? ($r['type'] ?? 'client') : 'client'] ?? 'Client') ?></td>
      <td class="actions">
        <a class="rbtn" href="?edit=<?= e($r['id']) ?>">Edit</a>
        <a class="rbtn" href="?delete=<?= e($r['id']) ?>" onclick="return confirm('Delete this client?')" style="color:#d41d1d">Delete</a>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<?php layout_footer();
