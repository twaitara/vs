<?php
require_once __DIR__ . '/layout.php';
require_admin();

$bankWhere = not_deleted_sql('bankvaluations');
function q_all(string $sql, array $p = []): array {
    try { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); } catch (Throwable $e) { return []; }
}
function q_one(string $sql, array $p = []) {
    try { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); } catch (Throwable $e) { return 0; }
}

$cur = setting('currency', CURRENCY);
$totBank = (int)q_one("SELECT COUNT(*) FROM bankvaluations" . $bankWhere);
$sumVal  = (float)q_one("SELECT COALESCE(SUM(market_value),0) FROM bankvaluations" . $bankWhere);
$avgVal  = (float)q_one("SELECT COALESCE(AVG(market_value),0) FROM bankvaluations" . $bankWhere);

$monthly = q_all("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c, COALESCE(SUM(market_value),0) s
                  FROM bankvaluations" . ($bankWhere ? $bankWhere . " AND" : " WHERE") . " created_at IS NOT NULL
                  GROUP BY m ORDER BY m DESC LIMIT 12");
$monthly = array_reverse($monthly);

$byClient = q_all("SELECT client, COUNT(*) c, COALESCE(SUM(market_value),0) s FROM bankvaluations" .
    ($bankWhere ? $bankWhere . " AND" : " WHERE") . " client IS NOT NULL GROUP BY client ORDER BY c DESC LIMIT 8");

$byValuer = q_all("SELECT principal_valuer v, COUNT(*) c FROM bankvaluations" .
    ($bankWhere ? $bankWhere . " AND" : " WHERE") . " principal_valuer IS NOT NULL AND principal_valuer<>'' GROUP BY principal_valuer ORDER BY c DESC LIMIT 8");

/** Horizontal bar chart from [label => value]. */
function bars(array $data, string $unit = ''): void {
    $max = 0; foreach ($data as $v) $max = max($max, $v);
    if ($max == 0) { echo '<p class="muted">No data.</p>'; return; }
    echo '<div class="bars">';
    foreach ($data as $label => $v) {
        $pct = $max ? round($v / $max * 100) : 0;
        echo '<div class="bar-row"><span class="bar-lbl">' . e($label) . '</span>'
           . '<span class="bar-track"><span class="bar-fill" style="width:' . $pct . '%"></span></span>'
           . '<span class="bar-val">' . ($unit === 'money' ? number_format($v) : number_format($v)) . '</span></div>';
    }
    echo '</div>';
}

layout_header('Analytics', '');
?>
<div class="kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:22px">
  <div class="kpi"><div class="kpi-label">Bank Valuations</div><div class="kpi-num"><?= number_format($totBank) ?></div></div>
  <div class="kpi"><div class="kpi-label">Total Value (<?= e($cur) ?>)</div><div class="kpi-num sm"><?= number_format($sumVal) ?></div></div>
  <div class="kpi"><div class="kpi-label">Average Value (<?= e($cur) ?>)</div><div class="kpi-num sm"><?= number_format($avgVal) ?></div></div>
</div>

<div class="panel"><div class="panel-h">Valuations per Month (last 12)</div>
  <?php $m = []; foreach ($monthly as $r) $m[$r['m']] = (int)$r['c']; bars($m); ?>
</div>
<div class="panel" style="margin-top:18px"><div class="panel-h">Total Value per Month (<?= e($cur) ?>)</div>
  <?php $m2 = []; foreach ($monthly as $r) $m2[$r['m']] = (float)$r['s']; bars($m2, 'money'); ?>
</div>
<div class="dash-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px">
  <div class="panel"><div class="panel-h">Top Clients (by count)</div>
    <?php $c = []; foreach ($byClient as $r) $c[lookup_name('clients', $r['client']) ?: '—'] = (int)$r['c']; bars($c); ?>
  </div>
  <div class="panel"><div class="panel-h">Top Valuers (by count)</div>
    <?php $v = []; foreach ($byValuer as $r) $v[$r['v']] = (int)$r['c']; bars($v); ?>
  </div>
</div>

<style>
  .kpi{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:18px}
  .kpi-label{color:var(--mut);font-size:13px;margin-bottom:8px}.kpi-num{font-size:30px;font-weight:700}.kpi-num.sm{font-size:20px}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:16px}
  .panel-h{font-weight:600;margin-bottom:14px}
  .bars{display:flex;flex-direction:column;gap:9px}
  .bar-row{display:grid;grid-template-columns:120px 1fr 90px;align-items:center;gap:10px;font-size:12px}
  .bar-lbl{color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .bar-track{background:#0f1419;border-radius:6px;height:18px;overflow:hidden}
  .bar-fill{display:block;height:100%;background:linear-gradient(90deg,#2563eb,#d41d1d)}
  .bar-val{text-align:right;color:var(--txt)}
</style>
<?php layout_footer();
