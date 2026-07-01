<?php
/**
 * Shared, feature-rich valuation list: search, filters, sorting, pagination,
 * per-page, bulk soft-delete, status badges. Used by bank & insurance lists.
 *
 * $cfg keys: table, title, nav, type ('bank'|'insurance'), value_field,
 *            value_label, form_page, can_preview (bool)
 */
require_once __DIR__ . '/layout.php';

function render_list(array $cfg): void {
    require_login();
    $table = $cfg['table'];
    $vf    = $cfg['value_field'];
    $soft  = column_exists($table, 'deleted_at');

    // ---- Bulk actions (POST): delete or sign ----
    $bulk = $_POST['bulk'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($bulk, ['delete', 'sign'], true)) {
        csrf_verify();
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
        if ($bulk === 'delete') {
            if (!can_edit()) { http_response_code(403); exit('View-only access.'); }
            if ($ids && $soft) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                db()->prepare("UPDATE `$table` SET deleted_at = NOW() WHERE id IN ($in)")->execute($ids);
                audit('bulk_delete', $cfg['type'], implode(',', $ids), count($ids) . ' records');
                flash(count($ids) . ' record(s) deleted.');
            }
        } elseif ($bulk === 'sign') {
            if (!can_sign()) { http_response_code(403); exit('You do not have a signing mandate.'); }
            if ($ids) {
                $n = sign_records($table, $ids);
                audit('sign', $cfg['type'], implode(',', $ids), $n . ' signed');
                flash($n ? ($n . ' report(s) signed and marked Complete.') : 'Could not sign (run schema_v6.sql).', $n ? 'ok' : 'err');
            }
        }
        redirect($cfg['nav'] === 'bank' ? 'bank_list.php' : 'insurance_list.php');
    }

    // ---- Inputs ----
    $q      = trim($_GET['q'] ?? '');
    $client = $_GET['client'] ?? '';
    $vtype  = $_GET['vtype'] ?? '';
    $status = $_GET['status'] ?? '';
    $ins    = $_GET['ins'] ?? '';
    $hasStatus = column_exists($table, 'status');
    $dfrom  = $_GET['dfrom'] ?? '';
    $dto    = $_GET['dto'] ?? '';
    $vmin   = $_GET['vmin'] ?? '';
    $vmax   = $_GET['vmax'] ?? '';

    $sortable = ['id'=>'id','reg_no'=>'reg_no','make'=>'make','customer_name'=>'customer_name','created'=>'created_at','value'=>$vf];
    $sort = $_GET['sort'] ?? 'id';
    if (!isset($sortable[$sort])) $sort = 'id';
    $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

    $allowedPp = [25, 50, 100];
    $pp = (int)($_GET['pp'] ?? setting('per_page', '25'));
    if (!in_array($pp, $allowedPp, true)) $pp = 25;
    $page = max(1, (int)($_GET['page'] ?? 1));

    // ---- WHERE ----
    $cond = [];  $params = [];
    if ($soft) $cond[] = 'deleted_at IS NULL';
    if ($q !== '')      { $cond[] = '(reg_no LIKE ? OR make LIKE ? OR customer_name LIKE ?)'; $l="%$q%"; array_push($params,$l,$l,$l); }
    if ($client !== '') { $cond[] = 'client = ?';         $params[] = $client; }
    if ($vtype !== '')  { $cond[] = 'valuation_type = ?'; $params[] = $vtype; }
    if ($status !== '' && $hasStatus) { $cond[] = 'status = ?'; $params[] = $status; }
    if ($ins === 'expired')   $cond[] = "insurance_exp IS NOT NULL AND insurance_exp <> '0000-00-00' AND insurance_exp < CURDATE()";
    elseif ($ins === 'soon')  $cond[] = "insurance_exp >= CURDATE() AND insurance_exp <= (CURDATE() + INTERVAL 30 DAY)";
    elseif ($ins === 'valid') $cond[] = "insurance_exp > (CURDATE() + INTERVAL 30 DAY)";
    if ($dfrom !== '')  { $cond[] = 'created_at >= ?';    $params[] = $dfrom . ' 00:00:00'; }
    if ($dto !== '')    { $cond[] = 'created_at <= ?';    $params[] = $dto . ' 23:59:59'; }
    if ($vmin !== '')   { $cond[] = "$vf >= ?";           $params[] = (float)$vmin; }
    if ($vmax !== '')   { $cond[] = "$vf <= ?";           $params[] = (float)$vmax; }
    $where = $cond ? ' WHERE ' . implode(' AND ', $cond) : '';

    $total = (int)(function() use ($table,$where,$params){ $s=db()->prepare("SELECT COUNT(*) FROM `$table`$where"); $s->execute($params); return $s->fetchColumn(); })();
    $pages = max(1, (int)ceil($total / $pp));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $pp;

    $orderCol = $sortable[$sort];
    $sql = "SELECT * FROM `$table`$where ORDER BY `$orderCol` $dir LIMIT $pp OFFSET $offset";
    $st = db()->prepare($sql); $st->execute($params); $rows = $st->fetchAll();

    // Completion stages: a stage is "pending" if any of its key fields is empty.
    $STAGES = $cfg['nav'] === 'insurance' ? [
        'VEH' => ['reg_no','make','customer_name','chasis_no'],
        'REG' => ['registration_date','manufacture_year','insurer'],
        'ENG' => ['mileage','colour','engine_no','engine_capacity','fuel_type'],
        'VAL' => ['assessed_value','inspection_date'],
    ] : [
        'VEH' => ['reg_no','make','customer_name','chasis_no','body_type','country_of_origin','phone_no','inspection_location'],
        'REG' => ['registration_date','manufacture_year','insurer','insurance_pol_no'],
        'ENG' => ['mileage','colour','engine_no','engine_capacity','fuel_type'],
        'CON' => ['coach_condition','electrical','mechanical_condition','general_condition','tyres'],
        'VAL' => ['market_value','forced_value'],
        'OFF' => ['principal_valuer','assesment_date','bank_officer','officer_phone'],
    ];
    $STAGE_NAMES = ['VEH'=>'Vehicle','REG'=>'Registration & Insurance','ENG'=>'Engine & Mileage','CON'=>'Condition','VAL'=>'Valuation','OFF'=>'Officer'];
    $pendingStages = function(array $row) use ($STAGES): array {
        $p = [];
        foreach ($STAGES as $ab => $fields) {
            foreach ($fields as $f) { if (trim((string)($row[$f] ?? '')) === '') { $p[] = $ab; break; } }
        }
        return $p;
    };

    // Params to preserve across sort/pagination links.
    $base = $cfg['nav'] === 'bank' ? 'bank_list.php' : 'insurance_list.php';
    $keep = array_filter(['q'=>$q,'client'=>$client,'vtype'=>$vtype,'status'=>$status,'ins'=>$ins,'dfrom'=>$dfrom,'dto'=>$dto,'vmin'=>$vmin,'vmax'=>$vmax,'pp'=>$pp,'sort'=>$sort,'dir'=>$dir], fn($v)=>$v!=='' && $v!==null);

    // Sortable header link.
    $sortHeader = function(string $key, string $label) use ($sort,$dir,$base,$keep) {
        $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
        $p = array_merge($keep, ['sort'=>$key,'dir'=>$nextDir,'page'=>1]);
        $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
        return '<th><a class="sortlink" href="' . url($base.'?'.http_build_query($p)) . '">' . e($label) . $arrow . '</a></th>';
    };

    layout_header($cfg['title'], $cfg['nav']);
    $hasFilters = ($client||$vtype||$status||$ins||$dfrom||$dto||$vmin!==''||$vmax!=='');
    ?>
    <div class="toolbar">
      <form method="get" style="margin:0" id="searchForm">
        <?php foreach (['client','vtype','status','ins','dfrom','dto','vmin','vmax','pp','sort','dir'] as $k) if (($keep[$k] ?? '')!=='') echo '<input type="hidden" name="'.$k.'" value="'.e($keep[$k]).'">'; ?>
        <input type="search" name="q" id="quickSearch" placeholder="Quick search reg no, make, customer…" value="<?= e($q) ?>" autocomplete="off">
      </form>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn sec" onclick="document.getElementById('filterBar').classList.toggle('open')"><i data-lucide="sliders-horizontal"></i>Filters<?= $hasFilters ? ' •' : '' ?></button>
        <a class="btn sec" href="<?= url('export.php?type=' . $cfg['nav'] . '&' . http_build_query($keep)) ?>"><i data-lucide="download"></i>CSV</a>
        <?php if (can_edit()): ?><a class="btn" href="<?= url($cfg['form_page']) ?>"><i data-lucide="plus"></i>New</a><?php endif; ?>
      </div>
    </div>

    <form method="get" id="filterBar" class="filterbar <?= $hasFilters ? 'open' : '' ?>">
      <?php if ($q!=='') echo '<input type="hidden" name="q" value="'.e($q).'">'; ?>
      <div class="fb-grid">
        <label>Client<select name="client"><?= options('clients', $client) ?></select></label>
        <label>Type<select name="vtype"><?= options('types', $vtype) ?></select></label>
        <?php if ($hasStatus): ?><label>Workflow Status<select name="status"><option value="">-- any --</option><?php foreach (valuation_statuses() as $sk=>$sl): ?><option value="<?= $sk ?>" <?= $status===$sk?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label>Status<select name="ins">
          <option value="">-- any --</option>
          <option value="expired" <?= $ins==='expired'?'selected':'' ?>>Expired</option>
          <option value="soon" <?= $ins==='soon'?'selected':'' ?>>Expiring soon (30d)</option>
          <option value="valid" <?= $ins==='valid'?'selected':'' ?>>Valid</option>
        </select></label>
        <label>From<input type="date" name="dfrom" value="<?= e($dfrom) ?>"></label>
        <label>To<input type="date" name="dto" value="<?= e($dto) ?>"></label>
        <label>Min value<input type="number" name="vmin" value="<?= e($vmin) ?>" step="0.01"></label>
        <label>Max value<input type="number" name="vmax" value="<?= e($vmax) ?>" step="0.01"></label>
      </div>
      <div class="fb-actions">
        <button class="btn" type="submit">Apply</button>
        <a class="btn sec" href="<?= url($base) ?>">Clear</a>
      </div>
    </form>

    <form method="post" id="bulkForm">
      <?= csrf_field() ?>
      <?php if (can_edit()): ?>
      <div class="bulkbar">
        <label><input type="checkbox" id="selAll"> Select all</label>
        <button class="btn" type="submit" name="bulk" value="delete" onclick="return confirmBulk('delete')" style="background:#d41d1d"><i data-lucide="trash-2"></i>Delete selected</button>
        <?php if (can_sign()): ?><button class="btn" type="submit" name="bulk" value="sign" onclick="return confirmBulk('sign')" style="background:#1c9c5d"><i data-lucide="pen-tool"></i>Sign selected</button><?php endif; ?>
        <span class="muted" id="selCount"></span>
        <span class="stagekey">Key:&nbsp;<?php foreach (array_keys($STAGES) as $ab) echo '<span class="stagebox">' . $ab . '</span>&nbsp;' . e($STAGE_NAMES[$ab] ?? $ab) . '&nbsp;&nbsp;'; ?></span>
      </div>
      <?php endif; ?>
      <table class="list">
        <thead><tr>
          <?php if (can_edit()): ?><th style="width:28px"></th><?php endif; ?>
          <?= $sortHeader('id','ID') ?>
          <?= $sortHeader('reg_no','Reg No.') ?>
          <?= $sortHeader('make','Make/Model') ?>
          <?= $sortHeader('customer_name','Customer') ?>
          <th>Client</th>
          <th>Status</th>
          <?= $sortHeader('value', $cfg['value_label']) ?>
          <?= $sortHeader('created','Created') ?>
          <th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="10" class="muted">No valuations found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <?php if (can_edit()): ?><td><input type="checkbox" class="rowchk" name="ids[]" value="<?= (int)$r['id'] ?>"></td><?php endif; ?>
            <td><?= e($r['id']) ?></td>
            <td><?= e($r['reg_no']) ?></td>
            <td><?= e($r['make']) ?></td>
            <td><?= e($r['customer_name']) ?></td>
            <td><?= e(lookup_name('clients', $r['client'])) ?></td>
            <td><?php
              $coreOk = trim((string)($r['reg_no'] ?? '')) !== '' && trim((string)($r['make'] ?? '')) !== ''
                     && trim((string)($r['customer_name'] ?? '')) !== '' && trim((string)($r[$vf] ?? '')) !== '';
              $pend = $coreOk ? [] : $pendingStages($r);
              if (!$pend) { echo '<span class="badge b-green">Complete</span>'; }
              else { foreach ($pend as $ab) echo '<span class="stagebox" title="' . e($STAGE_NAMES[$ab] ?? $ab) . ' — incomplete">' . $ab . '</span>'; } ?></td>
            <td><?= isset($r[$vf]) && $r[$vf] !== null && $r[$vf] !== '' ? number_format((float)$r[$vf]) : '' ?></td>
            <td class="muted"><?= e(ddate($r['created_at'])) ?></td>
            <td class="actions">
              <?php if (can_edit()): ?>
                <a class="rbtn ico" href="<?= url($cfg['form_page'].'?id='.$r['id']) ?>" title="Edit"><i data-lucide="pencil"></i></a>
              <?php endif; ?>
              <a class="rbtn ico" href="#" onclick="openPreview(<?= (int)$r['id'] ?>,'<?= e($cfg['nav']) ?>');return false;" title="Preview report"><i data-lucide="eye"></i></a>
              <a class="rbtn ico" href="<?= url('print.php?type='.$cfg['nav'].'&id='.$r['id']) ?>" title="Print / Download PDF"><i data-lucide="printer"></i></a>
              <?php if (can_sign() && empty($r['signed_at'])): ?>
                <a class="rbtn ico sign-due" href="<?= url('sign.php?type='.$cfg['nav'].'&id='.$r['id'].'&_csrf='.csrf_token()) ?>" onclick="return confirm('Sign this report? It will be marked Complete and stamped with today’s date.')" title="Sign report"><i data-lucide="pen-tool"></i></a>
              <?php elseif (!empty($r['signed_at'])): ?>
                <span class="rbtn ico" style="border-color:#1c7a47;color:#3ddc84;cursor:default" title="Signed <?= e(ddate($r['signed_at'])) ?>"><i data-lucide="check"></i></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </form>

    <div class="listfoot">
      <div class="count"><?= number_format($total) ?> record<?= $total===1?'':'s' ?> · page <?= $page ?> of <?= $pages ?></div>
      <label class="pp">Per page
        <select onchange="location.href='<?= url($base) ?>?<?= http_build_query(array_diff_key($keep,['pp'=>1])) ?>&pp='+this.value">
          <?php foreach ($allowedPp as $opt): ?><option value="<?= $opt ?>" <?= $pp===$opt?'selected':'' ?>><?= $opt ?></option><?php endforeach; ?>
        </select>
      </label>
    </div>
    <?php pagination_bar($page, $pages, $base, $keep); ?>

    <?php preview_modal(); ?>
    <script>
      // bulk select
      var selAll=document.getElementById('selAll');
      function chks(){return Array.prototype.slice.call(document.querySelectorAll('.rowchk'));}
      function upd(){var n=chks().filter(c=>c.checked).length;var el=document.getElementById('selCount');if(el)el.textContent=n?n+' selected':'';}
      if(selAll)selAll.addEventListener('change',function(){chks().forEach(c=>c.checked=selAll.checked);upd();});
      chks().forEach(c=>c.addEventListener('change',upd));
      function confirmBulk(verb){var n=chks().filter(c=>c.checked).length;if(!n){alert('Select at least one row.');return false;}
      var msg = verb==='sign' ? ('Sign '+n+' selected report(s)? This marks them Complete and stamps today’s date.') : ('Delete '+n+' selected record(s)?');
      return confirm(msg);}
    </script>
    <?php quick_search_script(); ?>
    <?php layout_footer();
}

/** Small status badges for a list row. */
function status_badges(array $r): string {
    $out = [];
    $exp = $r['insurance_exp'] ?? '';
    if ($exp && $exp !== '0000-00-00') {
        $t = strtotime($exp);
        if ($t) {
            $days = floor(($t - time()) / 86400);
            if ($days < 0)      $out[] = '<span class="badge b-red">Insurance expired</span>';
            elseif ($days <= 30) $out[] = '<span class="badge b-amber">Expires soon</span>';
        }
    }
    $imgs = !empty($r['images']) ? (json_decode($r['images'], true) ?: []) : [];
    if ($imgs) $out[] = '<span class="badge b-grey">📷 ' . count($imgs) . '</span>';
    return implode(' ', $out);
}
