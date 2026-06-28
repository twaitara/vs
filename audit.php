<?php
require_once __DIR__ . '/layout.php';
require_admin();

$q       = trim($_GET['q'] ?? '');
$action  = $_GET['action'] ?? '';
$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));

$cond = []; $params = [];
if ($q !== '')      { $cond[] = '(user_name LIKE ? OR entity LIKE ? OR details LIKE ?)'; $l="%$q%"; array_push($params,$l,$l,$l); }
if ($action !== '') { $cond[] = 'action = ?'; $params[] = $action; }
$where = $cond ? ' WHERE ' . implode(' AND ', $cond) : '';

$total = 0; $rows = []; $actions = [];
try {
    $c = db()->prepare("SELECT COUNT(*) FROM audit_log$where"); $c->execute($params); $total = (int)$c->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $off = ($page - 1) * $perPage;
    $st = db()->prepare("SELECT * FROM audit_log$where ORDER BY id DESC LIMIT $perPage OFFSET $off");
    $st->execute($params); $rows = $st->fetchAll();
    $actions = db()->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $pages = 1; }

layout_header('Audit Log', 'settings');
settings_nav('audit');
?>
<div class="toolbar">
  <form method="get" style="margin:0;display:flex;gap:8px">
    <input type="search" name="q" placeholder="Search user, entity, details…" value="<?= e($q) ?>" style="width:260px">
    <select name="action" onchange="this.form.submit()">
      <option value="">All actions</option>
      <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
    <button class="btn sec" type="submit">Filter</button>
  </form>
</div>
<table class="list">
  <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Ref</th><th>Details</th><th>IP</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">No audit entries.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td class="muted" style="white-space:nowrap"><?= e($r['created_at']) ?></td>
      <td><?= e($r['user_name'] ?: '—') ?></td>
      <td><?= e($r['action']) ?></td>
      <td><?= e($r['entity']) ?></td>
      <td><?= e($r['entity_id']) ?></td>
      <td><?= e($r['details']) ?></td>
      <td class="muted"><?= e($r['ip']) ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<?php pagination_bar($page, $pages, 'audit.php', array_filter(['q'=>$q,'action'=>$action])); ?>
<div class="count"><?= number_format($total) ?> entries</div>
<?php layout_footer();
