<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Content-Type: application/json; charset=utf-8');
error_reporting(~E_NOTICE);
@ini_set('display_errors', '1'); //แสดงerror

include '../connect.php';

$arrReg = array("5511" => "9", "5512" => "10", "5521" => "6", "5522" => "7", "5531" => "1", "5532" => "8", "5541" => "2", "5542" => "3", "5551" => "4", "5552" => "5");
$pwa_code = $_REQUEST['pwa_code'];
$zone = $arrReg[substr($_REQUEST['pwa_code'], 0, 4)];
$pipe_id = $_REQUEST['pipe_id'];
$table_pipe = "dssnrw.pipe_summary";

$sql ="SET CLIENT_ENCODING TO 'utf-8'; SELECT row_to_json(fc) ";
$sql.= "FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features ";
$sql.= "FROM (SELECT 'Feature' As type ";
$sql.= ", ST_AsGeoJSON(lg.wkb_geometry)::json As geometry ";
$sql.= ", row_to_json((SELECT l FROM (SELECT lg.pipe_id) As l ";
$sql.= ")) As properties ";
$sql.= "FROM ".$table_pipe." As lg WHERE pwa_code='".$pwa_code."'";
$sql.= ") As f )  As fc ";
   
$result = pg_exec($connection, $sql);

  
$numrows = pg_numrows($result);

if(!$result){
   echo pg_last_error($db);
} else {
$arr = pg_fetch_array($result, 0, PGSQL_NUM);
}

echo $arr[0];
pg_close($connection);

?>

