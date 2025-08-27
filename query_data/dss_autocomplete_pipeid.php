<?php

@ini_set('display_errors', '0'); //ไม่แสดงerror
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
date_default_timezone_set("Asia/Bangkok");

//echo '11';
include '../connect.php';
//$keyword = ($_REQUEST['term']);
$keyword = iconv("utf-8", "windows-874", $_REQUEST['term']);
$pwa_code = $_REQUEST['pwa_code'];

$arrReg = array("5511" => "9", "5512" => "10", "5521" => "6", "5522" => "7", "5531" => "1", "5532" => "8", "5541" => "2", "5542" => "3", "5551" => "4", "5552" => "5");
$reg = $arrReg[substr($pwacode, 0, 4)];

$sql = " SELECT pipe_id FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}' AND pipe_id::TEXT LIKE " . " '%{$keyword}%' ORDER BY pipe_id LIMIT 10 ";
//echo $sql;
//exit();

$result = pg_exec($connection, $sql);

$numrows = pg_numrows($result);
$data = array();

for ($ri = 0; $ri < $numrows; $ri++) {
    $row = pg_fetch_array($result, $ri);
    array_push($data, array(
        'pipe_id' => $row['pipe_id'],
    ));
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
pg_close($connection);
