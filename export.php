<?php
require_once __DIR__ . '/lib.php';
require_login();

$type  = $_GET['type'] ?? 'bank';
if (!in_array($type, ['bank', 'insurance', 'machine'], true)) $type = 'bank';
$table = type_table($type);
$vf    = $type === 'insurance' ? 'assessed_value' : 'market_value';
$isMachine = $type === 'machine';

// Same filters as the list view.
$q=trim($_GET['q']??''); $client=$_GET['client']??''; $vtype=$_GET['vtype']??''; $status=$_GET['status']??'';
$dfrom=$_GET['dfrom']??''; $dto=$_GET['dto']??''; $vmin=$_GET['vmin']??''; $vmax=$_GET['vmax']??'';

$cond=[]; $params=[];
if (column_exists($table,'deleted_at')) $cond[]='deleted_at IS NULL';
if (!sees_all_valuations() && column_exists($table,'created_by')){ $cond[]='created_by = ?'; $params[]=(int)(current_user()['id']??0); }
if ($q!==''){
    if ($isMachine){ $cond[]='(machine_name LIKE ? OR customer_name LIKE ? OR serial_no LIKE ?)'; }
    else           { $cond[]='(reg_no LIKE ? OR make LIKE ? OR customer_name LIKE ?)'; }
    $l="%$q%"; array_push($params,$l,$l,$l);
}
if ($client!==''){ $cond[]='client = ?'; $params[]=$client; }
if (!$isMachine && $vtype!==''){ $cond[]='valuation_type = ?'; $params[]=$vtype; }
if ($status!=='' && column_exists($table,'status')){ $cond[]='status = ?'; $params[]=$status; }
if ($dfrom!==''){ $cond[]='created_at >= ?'; $params[]=$dfrom.' 00:00:00'; }
if ($dto!==''){ $cond[]='created_at <= ?'; $params[]=$dto.' 23:59:59'; }
if ($vmin!==''){ $cond[]="$vf >= ?"; $params[]=(float)$vmin; }
if ($vmax!==''){ $cond[]="$vf <= ?"; $params[]=(float)$vmax; }
$where = $cond ? ' WHERE '.implode(' AND ',$cond) : '';

$repSel = column_exists($table,'report_no') ? 'report_no' : "'' AS report_no";
$serSel = column_exists($table,'serial_no') ? 'serial_no' : "'' AS serial_no";
$staSel = column_exists($table,'status') ? 'status' : "'' AS status";

$filename = $type . '-valuations-' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');

if ($isMachine) {
    $sql = "SELECT id, $repSel, $serSel, machine_name, colour, customer_name, phone_no, client,
            officer, `$vf` AS value, forced_value, $staSel, created_at
            FROM `$table`$where ORDER BY id DESC";
    $st = db()->prepare($sql); $st->execute($params);
    fputcsv($out, ['ID','Report No','Serial','Machine','Colour','Customer','Phone','Client','Officer','Market Value','Forced Value','Status','Created']);
    while ($r = $st->fetch()) {
        fputcsv($out, [
            $r['id'], $r['report_no'], serial_display($r['serial_no']), $r['machine_name'], $r['colour'],
            $r['customer_name'], $r['phone_no'], lookup_name('clients', $r['client']), $r['officer'],
            $r['value'], $r['forced_value'], $r['status'], $r['created_at'],
        ]);
    }
} else {
    $sql = "SELECT id, $repSel, reg_no, make, customer_name, phone_no, client, valuation_type,
            manufacture_year, chasis_no, engine_no, mileage, `$vf` AS value, $staSel, created_at
            FROM `$table`$where ORDER BY id DESC";
    $st = db()->prepare($sql); $st->execute($params);
    fputcsv($out, ['ID','Report No','Reg No','Make/Model','Customer','Phone','Client','Type','YOM','Chassis','Engine','Mileage','Value','Status','Created']);
    while ($r = $st->fetch()) {
        fputcsv($out, [
            $r['id'], $r['report_no'], $r['reg_no'], $r['make'], $r['customer_name'], $r['phone_no'],
            lookup_name('clients', $r['client']), lookup_name('types', $r['valuation_type']),
            $r['manufacture_year'], $r['chasis_no'], $r['engine_no'], $r['mileage'],
            $r['value'], $r['status'], $r['created_at'],
        ]);
    }
}
fclose($out);
audit('export_csv', $type);
