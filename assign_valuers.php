<?php
/**
 * ONE-TIME TOOL (admin): create a Valuer account for each historical valuer name
 * and assign each existing valuation (bank & insurance) to the matching account.
 * Open in a browser, review, click Run. DELETE this file afterwards.
 */
require_once __DIR__ . '/layout.php';
require_admin();

// Canonical valuer -> the raw name spellings found in the data.
$defs = [
    ['name' => 'John',     'aliases' => ['JOHN']],
    ['name' => 'Martin',   'aliases' => ['MARTIN']],
    ['name' => 'James',    'aliases' => ['JAMES']],
    ['name' => 'Denis',    'aliases' => ['DENIS', 'DENNIS']],
    ['name' => 'Caleb',    'aliases' => ['CALEB']],
    ['name' => 'Ken',      'aliases' => ['KEN']],
    ['name' => 'Jonah',    'aliases' => ['JONAH']],
    ['name' => 'Mark',     'aliases' => ['MARK']],
    ['name' => 'Simon',    'aliases' => ['SIMON']],
    ['name' => 'Derrick',  'aliases' => ['DERRICK']],
    ['name' => 'Sam',      'aliases' => ['SAM']],
    ['name' => 'Frank',    'aliases' => ['FRANK']],
    ['name' => 'Kevin',    'aliases' => ['KEVIN']],
    ['name' => 'Mary',     'aliases' => ['MARY']],
    ['name' => 'Edwin',    'aliases' => ['EDWIN']],
    ['name' => 'Peter',    'aliases' => ['PETER']],
    ['name' => 'Wallace',  'aliases' => ['WALLACE']],
    ['name' => 'Brian',    'aliases' => ['BRIAN']],
    ['name' => 'David',    'aliases' => ['DAVID']],
    ['name' => 'Ezra',     'aliases' => ['EZRA']],
    ['name' => 'Felix',    'aliases' => ['FELIX']],
    ['name' => 'Geoffrey', 'aliases' => ['GEOFFREY']],
    ['name' => 'Jackson',  'aliases' => ['JACKSON']],
    ['name' => 'L Muthe',  'aliases' => ['L MUTHE']],
    ['name' => 'Moses',    'aliases' => ['MOSES']],
    ['name' => 'Nelson',   'aliases' => ['NELSON']],
    ['name' => 'Walter',   'aliases' => ['WALTER']],
];
$emailOf = fn($n) => strtolower(str_replace(' ', '', $n)) . '@kennetvaluers.com';
$iniOf   = fn($n) => strtoupper(substr(str_replace(' ', '', $n), 0, 10));

$hasInitials  = column_exists('users', 'initials');
$hasCreatedBy = column_exists('bankvaluations', 'created_by');
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $results = ['created' => [], 'existing' => [], 'assigned' => []];
    foreach ($defs as $d) {
        $email = $emailOf($d['name']);
        $st = db()->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?)'); $st->execute([$email]);
        $uid = (int)($st->fetchColumn() ?: 0);
        if (!$uid) {
            $pw = bin2hex(random_bytes(4)); // 8-char random password
            $ins = db()->prepare('INSERT INTO users (name,email,role,active,password,created_at,updated_at) VALUES (?,?,?,1,?,NOW(),NOW())');
            $ins->execute([$d['name'], $email, 'valuer', password_hash($pw, PASSWORD_BCRYPT)]);
            $uid = (int)db()->lastInsertId();
            if ($hasInitials) db()->prepare('UPDATE users SET initials=? WHERE id=?')->execute([$iniOf($d['name']), $uid]);
            $results['created'][] = ['name' => $d['name'], 'email' => $email, 'pw' => $pw, 'ini' => $iniOf($d['name'])];
        } else {
            $results['existing'][] = ['name' => $d['name'], 'email' => $email];
        }
        // Assign valuations to this account.
        $n = 0;
        if ($hasCreatedBy) {
            $in = implode(',', array_fill(0, count($d['aliases']), '?'));
            foreach (['bankvaluations' => 'principal_valuer', 'valuations' => 'inspection_officer'] as $tbl => $col) {
                if (!column_exists($tbl, $col) || !column_exists($tbl, 'created_by')) continue;
                $up = db()->prepare("UPDATE `$tbl` SET created_by=? WHERE UPPER(TRIM(`$col`)) IN ($in)");
                $up->execute(array_merge([$uid], $d['aliases']));
                $n += $up->rowCount();
            }
        }
        $results['assigned'][$d['name']] = $n;
    }
    audit('assign_valuers', 'system', null, count($results['created']) . ' created');
}

layout_header('Assign Valuers', '');
?>
<div class="card" style="max-width:760px">
  <h3 style="margin-top:0">Create valuer accounts &amp; assign historical valuations</h3>
  <?php if (!$hasCreatedBy || !$hasInitials): ?>
    <div class="flash flash-err">Run <code>schema_v8.sql</code> (and earlier migrations) first — required columns are missing.</div>
  <?php endif; ?>
  <?php if ($results === null): ?>
    <p class="muted" style="font-size:13px">This will create a <b>Valuer</b> account (random password) for each of the <?= count($defs) ?> names found in your data, then set the <b>Valuer</b> on every matching valuation. Existing accounts are reused. Passwords are shown once — copy them.</p>
    <form method="post"><?= csrf_field() ?><button class="btn" type="submit" onclick="return confirm('Create accounts and assign all matching valuations?')"><i data-lucide="users"></i> Run now</button></form>
  <?php else: ?>
    <div class="flash">Done. <?= count($results['created']) ?> account(s) created, <?= count($results['existing']) ?> already existed.</div>
    <p class="muted" style="font-size:12px"><b>Copy these passwords now — they are not stored anywhere and won't be shown again.</b></p>
    <table class="list">
      <thead><tr><th>Name</th><th>Initials</th><th>Username (email)</th><th>Password</th><th>Valuations assigned</th></tr></thead>
      <tbody>
      <?php foreach ($defs as $d): $c = null; foreach ($results['created'] as $x) if ($x['name'] === $d['name']) $c = $x; ?>
        <tr>
          <td><?= e($d['name']) ?></td>
          <td><?= e($c['ini'] ?? '—') ?></td>
          <td><?= e($emailOf($d['name'])) ?></td>
          <td><?= $c ? '<code>' . e($c['pw']) . '</code>' : '<span class="muted">existing</span>' ?></td>
          <td><?= (int)($results['assigned'][$d['name']] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="flash flash-err" style="margin-top:14px">⚠ Now DELETE <code>assign_valuers.php</code> from the server. You can send each valuer their login from Settings → Users → “Email login”.</p>
  <?php endif; ?>
</div>
<?php layout_footer();
