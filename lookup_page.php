<?php
require_once __DIR__ . '/layout.php';
require_login();

/** Render a simple add/edit/delete admin page for a lookup table (id, name). */
function lookup_admin(string $table, string $title, string $active): void {
    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $eid  = $_POST['id'] ?? '';
        if ($name !== '') {
            if ($eid) {
                $st = db()->prepare("UPDATE `$table` SET name = ?, updated_at = ? WHERE id = ?");
                $st->execute([$name, date('Y-m-d H:i:s'), $eid]);
                flash(ucfirst(rtrim($title,'s')) . ' updated.');
            } else {
                $now = date('Y-m-d H:i:s');
                $st = db()->prepare("INSERT INTO `$table` (name, created_at, updated_at) VALUES (?,?,?)");
                $st->execute([$name, $now, $now]);
                flash(ucfirst(rtrim($title,'s')) . ' added.');
            }
        }
        redirect(basename($_SERVER['PHP_SELF']));
    }
    if (isset($_GET['delete'])) {
        $st = db()->prepare("DELETE FROM `$table` WHERE id = ?");
        $st->execute([$_GET['delete']]);
        flash('Deleted.');
        redirect(basename($_SERVER['PHP_SELF']));
    }

    $editId = $_GET['edit'] ?? '';
    $editRow = $editId ? load_row_simple($table, $editId) : [];
    $rows = db()->query("SELECT * FROM `$table` ORDER BY name")->fetchAll();

    layout_header($title, $active);
    ?>
    <form class="card" method="post" style="max-width:480px">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?= e($editRow['id']) ?>"><?php endif; ?>
      <div class="f">
        <label class="f"><?= $editRow ? 'Edit' : 'Add' ?> name</label>
        <input type="text" name="name" required value="<?= e($editRow['name'] ?? '') ?>">
      </div>
      <div style="margin-top:12px">
        <button class="btn" type="submit"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a class="btn sec" href="<?= url(basename($_SERVER['PHP_SELF'])) ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
    <table class="list">
      <thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="3" class="muted">None yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['id']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td class="actions">
            <a href="?edit=<?= e($r['id']) ?>">Edit</a>
            <a href="?delete=<?= e($r['id']) ?>" onclick="return confirm('Delete this entry?')" style="color:#d41d1d">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <?php
    layout_footer();
}

function load_row_simple(string $table, $id): array {
    $st = db()->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch() ?: [];
}
