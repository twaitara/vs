<?php
require_once __DIR__ . '/layout.php';
require_login();

$q       = trim($_GET['q'] ?? '');
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));

$where = '';
$params = [];
if ($q !== '') {
    $where = " WHERE reg_no LIKE ? OR make LIKE ? OR customer_name LIKE ?";
    $like = "%$q%"; $params = [$like, $like, $like];
}

// Total count for pagination.
$cst = db()->prepare("SELECT COUNT(*) FROM bankvaluations" . $where);
$cst->execute($params);
$total = (int)$cst->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $perPage;

$sql = "SELECT id, reg_no, make, customer_name, client, created_at FROM bankvaluations"
     . $where . " ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$st = db()->prepare($sql); $st->execute($params); $rows = $st->fetchAll();

layout_header('Bank Valuations', 'bank');
?>
<div class="toolbar">
  <form method="get" style="margin:0" id="searchForm">
    <input type="search" name="q" id="quickSearch" placeholder="Quick search reg no, make, customer…" value="<?= e($q) ?>" autocomplete="off">
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
<?php pagination_bar($page, $pages, 'bank_list.php', $q !== '' ? ['q' => $q] : []); ?>
<div class="count"><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?> · page <?= $page ?> of <?= $pages ?></div>
<?php quick_search_script(); ?>
<?php layout_footer();
