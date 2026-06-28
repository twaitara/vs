<?php
require_once __DIR__ . '/layout.php';
require_login();

/** Helper: scalar query with graceful fallback. */
function dash_scalar(string $sql, array $p = []) {
    try { $st = db()->prepare($sql); $st->execute($p); return $st->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
$bankWhere = not_deleted_sql('bankvaluations');
$insWhere  = not_deleted_sql('valuations');

$bankTotal = (int)dash_scalar("SELECT COUNT(*) FROM bankvaluations" . $bankWhere);
$insTotal  = (int)dash_scalar("SELECT COUNT(*) FROM valuations" . $insWhere);
$monthCond = "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
$bankMonth = (int)dash_scalar("SELECT COUNT(*) FROM bankvaluations" . ($bankWhere ? $bankWhere . " AND " : " WHERE ") . $monthCond);
$totalValue = (float)dash_scalar("SELECT COALESCE(SUM(market_value),0) FROM bankvaluations" . $bankWhere);

// Recent valuations (bank).
$recent = [];
try {
    $st = db()->query("SELECT id, reg_no, make, customer_name, market_value, created_at
                       FROM bankvaluations" . $bankWhere . " ORDER BY id DESC LIMIT 8");
    $recent = $st->fetchAll();
} catch (Throwable $e) {}

// Top clients by count.
$topClients = [];
try {
    $st = db()->query("SELECT client, COUNT(*) c FROM bankvaluations" . $bankWhere .
        ($bankWhere ? " AND" : " WHERE") . " client IS NOT NULL GROUP BY client ORDER BY c DESC LIMIT 5");
    $topClients = $st->fetchAll();
} catch (Throwable $e) {}

$cur = setting('currency', CURRENCY);

layout_header('Dashboard', 'dashboard');
?>
<div class="kpis">
  <div class="kpi"><div class="kpi-label">Bank Valuations</div><div class="kpi-num"><?= number_format($bankTotal) ?></div></div>
  <div class="kpi"><div class="kpi-label">Insurance Valuations</div><div class="kpi-num"><?= number_format($insTotal) ?></div></div>
  <div class="kpi"><div class="kpi-label">New This Month</div><div class="kpi-num"><?= number_format($bankMonth) ?></div></div>
  <div class="kpi"><div class="kpi-label">Total Market Value (<?= e($cur) ?>)</div><div class="kpi-num sm"><?= number_format($totalValue) ?></div></div>
</div>

<div class="dash-grid">
  <div class="panel">
    <div class="panel-h">Recent Bank Valuations <a href="<?= url('bank_list.php') ?>" class="lnk">View all →</a></div>
    <table class="list">
      <thead><tr><th>Reg No.</th><th>Make/Model</th><th>Customer</th><th>Value</th><th></th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="5" class="muted">No valuations yet.</td></tr>
      <?php else: foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['reg_no']) ?></td>
          <td><?= e($r['make']) ?></td>
          <td><?= e($r['customer_name']) ?></td>
          <td><?= number_format((float)$r['market_value']) ?></td>
          <td class="actions"><a class="rbtn" href="#" onclick="openPreview(<?= (int)$r['id'] ?>);return false;">Preview</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-h">Top Clients</div>
    <table class="list">
      <thead><tr><th>Client</th><th>Valuations</th></tr></thead>
      <tbody>
      <?php if (!$topClients): ?><tr><td colspan="2" class="muted">No data.</td></tr>
      <?php else: foreach ($topClients as $c): ?>
        <tr><td><?= e(lookup_name('clients', $c['client'])) ?: '<span class="muted">—</span>' ?></td><td><?= (int)$c['c'] ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <?php if (can_edit()): ?>
    <div class="quick">
      <a class="btn" href="<?= url('bank_form.php') ?>">+ New Bank Valuation</a>
      <a class="btn blue" href="<?= url('insurance_form.php') ?>">+ New Insurance Valuation</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<style>
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:22px}
  .kpi{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:18px}
  .kpi-label{color:var(--mut);font-size:13px;margin-bottom:8px}
  .kpi-num{font-size:30px;font-weight:700} .kpi-num.sm{font-size:22px}
  .dash-grid{display:grid;grid-template-columns:2fr 1fr;gap:18px}
  @media(max-width:900px){.dash-grid{grid-template-columns:1fr}}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:16px}
  .panel-h{display:flex;justify-content:space-between;align-items:center;font-weight:600;margin-bottom:12px}
  .panel-h .lnk{font-size:13px;color:var(--accent2)}
  .quick{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
</style>
<?php preview_modal(); ?>
<?php layout_footer();
