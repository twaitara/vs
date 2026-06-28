<?php
require_once __DIR__ . '/lib.php';
require_login();

$type  = ($_GET['type'] ?? 'bank') === 'insurance' ? 'insurance' : 'bank';
$table = $type === 'insurance' ? 'valuations' : 'bankvaluations';
$vf    = $type === 'insurance' ? 'assessed_value' : 'market_value';

// Same filters as the list view.
$q=trim($_GET['q']??''); $client=$_GET['client']??''; $vtype=$_GET['vtype']??''; $status=$_GET['status']??'';
$dfrom=$_GET['dfrom']??''; $dto=$_GET['dto']??''; $vmin=$_GET['vmin']??''; $vmax=$_GET['vmax']??'';

$cond=[]; $params=[];
if (column_exists($table,'deleted_at')) $cond[]='deleted_at IS NULL';
if ($q!==''){ $cond[]='(reg_no LIKE ? OR make LIKE ? OR customer_name LIKE ?)'; $l="%$q%"; array_push($params,$l,$l,$l); }
if ($client!==''){ $cond[]='client = ?'; $params[]=$client; }
if ($vtype!==''){ $cond[]='valuation_type = ?'; $params[]=$vtype; }
if ($status!=='' && column_exists($table,'status')){ $cond[]='status = ?'; $params[]=$status; }
if ($dfrom!==''){ $cond[]='created_at >= ?'; $params[]=$dfrom.' 00:00:00'; }
if ($dto!==''){ $cond[]='created_at <= ?'; $params[]=$dto.' 23:59:59'; }
if ($vmin!==''){ $cond[]="$vf >= ?"; $params[]=(float)$vmin; }
if ($vmax!==''){ $cond[]="$vf <= ?"; $params[]=(float)$vmax; }
$where = $cond ? ' WHERE '.implode(' AND ',$cond) : '';

$repSel = column_exists($table,'report_no') ? 'report_no' : "'' AS report_no";
$staSel = column_exists($table,'status') ? 'status' : "'' AS status";
$sql = "SELECT id, $repSel, reg_no, make, customer_name, phone_no, client, valuation_type,
        manufacture_year, chasis_no, engine_no, mileage, `$vf` AS value, $staSel, created_at
        FROM `$table`$where ORDER BY id DESC";
$st = db()->prepare($sql); $st->execute($params);

$filename = $type . '-valuations-' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Report No','Reg No','Make/Model','Customer','Phone','Client','Type','YOM','Chassis','Engine','Mileage','Value','Status','Created']);
while ($r = $st->fetch()) {
    fputcsv($out, [
        $r['id'], $r['report_no'], $r['reg_no'], $r['make'], $r['customer_name'], $r['phone_no'],
        lookup_name('clients', $r['client']), lookup_name('types', $r['valuation_type']),
        $r['manufacture_year'], $r['chasis_no'], $r['engine_no'], $r['mileage'],
        $r['value'], $r['status'], $r['created_at'],
    ]);
}
fclose($out);
audit('export_csv', $type);
