<?php
require_once __DIR__ . '/layout.php';
require_login();

$q = trim($_GET['q'] ?? '');
$sql = "SELECT id, reg_no, make, customer_name, client, created_at FROM bankvaluations";
$params = [];
if ($q !== '') {
    $sql .= " WHERE reg_no LIKE ? OR make LIKE ? OR customer_name LIKE ?";
    $like = "%$q%"; $params = [$like, $like, $like];
}
$sql .= " ORDER BY id DESC";
$st = db()->prepare($sql); $st->execute($params); $rows = $st->fetchAll();

layout_header('Bank Valuations', 'bank');
?>
<div class="toolbar">
  <form method="get" style="margin:0">
    <input type="search" name="q" placeholder="Search reg no, make, customer…" value="<?= e($q) ?>">
  </form>
  <a class="btn" href="<?= url('bank_form.php') ?>">+ New Bank Valuation</a>
</div>
<table class="list">
  <thead><tr><th>ID</th><th>Reg No.</th><th>Make/Model</th><th>Customer</th><th>Client</th><th>Created</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="muted">No valuations found.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['id']) ?></td>
      <td><?= e($r['reg_no']) ?></td>
      <td><?= e($r['make']) ?></td>
      <td><?= e($r['customer_name']) ?></td>
      <td><?= e(lookup_name('clients', $r['client'])) ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td class="actions">
        <a class="rbtn" href="<?= url('bank_form.php?id=' . $r['id']) ?>">Edit</a>
        <a class="rbtn" href="<?= url('preview.php?id=' . $r['id']) ?>" target="_blank">Preview</a>
        <a class="rbtn" href="<?= url('print.php?id=' . $r['id']) ?>">Print</a>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<?php layout_footer();
