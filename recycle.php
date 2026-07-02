<?php
require_once __DIR__ . '/layout.php';
require_admin();

$tables = ['bank' => 'bankvaluations', 'insurance' => 'valuations', 'machine' => 'machinevaluations'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $type   = $_POST['type'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if (isset($tables[$type]) && $id) {
        $t = $tables[$type];
        if ($action === 'restore') {
            db()->prepare("UPDATE `$t` SET deleted_at = NULL WHERE id = ?")->execute([$id]);
            audit('restore', $type, $id);
            flash('Record restored.');
        } elseif ($action === 'purge' && is_admin()) {
            db()->prepare("DELETE FROM `$t` WHERE id = ?")->execute([$id]);
            audit('purge', $type, $id);
            flash('Record permanently deleted.');
        }
    }
    redirect('recycle.php');
}

function deleted_rows(string $table): array {
    if (!column_exists($table, 'deleted_at')) return [];
    // Machines have no reg_no/make columns — alias so the shared markup works.
    $reg  = $table === 'machinevaluations' ? 'machine_name AS reg_no' : 'reg_no';
    $make = $table === 'machinevaluations' ? "'' AS make" : 'make';
    try {
        return db()->query("SELECT id, $reg, $make, customer_name, deleted_at FROM `$table`
                            WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 200")->fetchAll();
    } catch (Throwable $e) { return []; }
}

layout_header('Recycle Bin', 'settings');
settings_nav('recycle');
?>
<p class="muted">Deleted valuations are kept here. Restore them, or (admins) delete permanently.</p>
<?php foreach (['bank' => 'Bank Valuations', 'insurance' => 'Insurance Valuations', 'machine' => 'Machine Valuations'] as $type => $label):
    if (!column_exists($tables[$type], 'id')) continue;
    $rows = deleted_rows($tables[$type]); $isMachine = $type === 'machine'; ?>
  <h3><?= e($label) ?></h3>
  <table data-paginate="25" class="list" style="margin-bottom:24px">
    <thead><tr><th>ID</th><th><?= $isMachine ? 'Machine' : 'Reg No.' ?></th><th>Make/Model</th><th>Customer</th><th>Deleted</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">Nothing here.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['id']) ?></td><td><?= e($r['reg_no']) ?></td><td><?= e($r['make']) ?></td>
        <td><?= e($r['customer_name']) ?></td><td class="muted"><?= e($r['deleted_at']) ?></td>
        <td class="actions">
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="rbtn" type="submit">Restore</button>
          </form>
          <?php if (is_admin()): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete? This cannot be undone.')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="purge"><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="rbtn" type="submit" style="color:#d41d1d">Delete forever</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php endforeach; ?>
<?php layout_footer();
