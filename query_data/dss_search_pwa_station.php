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

$table_pwa = "pwa_office.pwa_waterworks_2024 pw
                LEFT JOIN pwa_office.ref_pwastation_id rf
                ON pw.pwa_station::TEXT = rf.pwastation_id " ;

$sql ="SET CLIENT_ENCODING TO 'utf-8'; SELECT row_to_json(fc) ";
$sql.= "FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features ";
$sql.= "FROM (SELECT 'Feature' As type ";
$sql.= ", ST_AsGeoJSON(ST_Transform(ST_SetSRID(wkb_geometry, 32647), 4326))::json AS geometry ";
$sql.= ", row_to_json((SELECT l FROM (SELECT pwa_code, name, pwa_station, pwa_address, water_resource, pwastation_desc) As l ";
$sql.= ")) As properties ";
$sql.= "FROM ".$table_pwa ;
$sql.= ") As f )  As fc ";

//echo $sql;
//exit() ;
   
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

