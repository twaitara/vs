<?php
require_once __DIR__ . '/layout.php';
require_admin(); // only admins enter valuation figures

$rows = pending_figures_rows();

// Valuer names/initials for display.
$uNames = [];
try { foreach (db()->query('SELECT id,name FROM users')->fetchAll() as $u) $uNames[$u['id']] = $u['name']; } catch (Throwable $e) {}

layout_header('Pending Figures', 'pending');
?>
<p class="muted" style="margin-top:-6px">Valuations saved by valuers that still need you to enter the value. Open one, add the figures and save.</p>

<table data-paginate="25" data-colpick class="list">
  <thead><tr>
    <th>Reg / Machine</th><th>Type</th><th>Prepared by</th><th>Saved</th><th data-nocolpick>Action</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="5" class="muted">Nothing awaiting figures. 🎉</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['label']) ?></b></td>
      <td><?= ucfirst($r['type']) ?></td>
      <td class="muted"><?= e($uNames[$r['created_by']] ?? '—') ?></td>
      <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
      <td class="actions">
        <a class="btn" href="<?= url($r['form'] . '?id=' . (int)$r['id']) ?>"><i data-lucide="calculator"></i>Add figures</a>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<style>.list .lucide-calculator{color:#f5b14a}</style>
<?php layout_footer();
