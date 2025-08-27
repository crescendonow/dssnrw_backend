<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Content-Type: application/json; charset=utf-8');
error_reporting(~E_NOTICE);
@ini_set('display_errors', '0'); //ไม่แสดงerror

//set connection postgresql replace connection.php 
$connection = pg_Connect("host=192.168.242.38 port=5432 dbname=pgweb_gis2 user=gispwadb password=pwa@gis#2016");
pg_set_client_encoding($connection, 'utf8');

$zone = $arrReg[substr($_REQUEST['pwa_code'], 0, 4)];
$act = $_REQUEST['act'];
$pwa_code = $_REQUEST['pwa_code'];

//--------------------------------------- array and global variable ---------------------------------------------- >> 
$age_data = array(
    'max' => '-',
    'min' => '-',
    'sd' => '-',
    'mean' => '-',
    'value_1' => 10,
    'value_2' => 19
);

//-----------------function zone--------------------------------------->>

// function return from data summary 
function pipe_summary($zone, $pwa_code)
{
    $str_pipeall = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT * FROM dssnrw.data_summary 
                WHERE zone = '{$zone}' AND pwa_code = '{$pwa_code}' ";

    $result = pg_exec($connection, $str_pipeall);

    $numrows = pg_numrows($result);
    if (!$result) {
        $pipeall_data = array();
    } else {

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);
            $pipeall_data = array(
                'update_data' => $row['update_data'],
                'c_pipe' => $row['c_pipe'],
                'l_pipe' => $row['l_pipe'],
                'c_mt' => $row['c_mt'],
                'c_valve' => $row['c_valve'],
                'c_fire' => $row['c_fire'],
                'c_leak' => $row['c_leak'],
            );
        }
    }
    return $pipeall_data;
}

//function push leakdata 
function get_leak_summary($pwa_code, $connection)
{
    $strLeak = "SELECT MAX(c_leak) max, 
                MIN(c_leak) min, 
                ROUND(STDDEV(c_leak), 2) AS sd, 
                ROUND(AVG(c_leak), 2) AS mean,  
                        CASE WHEN CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) <= 0 THEN 1 
                            ELSE CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) END value_1, 
                        CAST(AVG(c_leak) + (1*STDDEV(c_leak)) AS INTEGER) value_2 FROM 
                        dssnrw.pipe_summary 
                        WHERE pwa_code = '{$pwa_code}' ";

    $leak_result = pg_exec($connection, $strLeak);

    $numrows = pg_numrows($leak_result);

    if (!$leak_result) {
        $leak_data = array();
    } else {

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($leak_result, $ri);
            $leak_data = array(
                'max' => $row['max'],
                'min' => $row['min'],
                'sd' => $row['sd'],
                'mean' => $row['mean'],
                'value_1' => $row['value_1'],
                'value_2' => $row['value_2'],
            );
        }
    }
    return $leak_data;
}
//function for get repair cost 
function get_repair_summary($pwa_code, $connection)
{
    $strCost = "SELECT MAX(cost_repair) max, 
                MIN(cost_repair) min, 
                ROUND(STDDEV(cost_repair), 2) AS sd, 
                ROUND(AVG(cost_repair), 2) mean,  
                CASE WHEN CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) <= 0 THEN 1  
                    ELSE CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) END value_1, 
                CAST(AVG(cost_repair) + (1*STDDEV(cost_repair)) AS INTEGER) value_2 FROM 
                dssnrw.pipe_summary 
                        WHERE pwa_code = '{$pwa_code}' ";

    $cost_result = pg_exec($connection, $strCost);

    $numrows = pg_numrows($cost_result);

    if (!$cost_result) {
        $cost_data = array();
    } else {
        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($cost_result, $ri);
            $cost_data = array(
                'max' => $row['max'],
                'min' => $row['min'],
                'sd' => $row['sd'],
                'mean' => $row['mean'],
                'value_1' => $row['value_1'],
                'value_2' => $row['value_2'],
            );
        }
    }
    return $cost_data;

}

//function create temp 
function create_ptemp($pwa_code, $connection)
{
    $strTemp = " DROP INDEX IF EXISTS dssnrw.p_temp_gengeometry_idx_{$pwa_code} CASCADE; COMMIT;
                        DROP INDEX IF EXISTS dssnrw.p_temp_geom_idx_{$pwa_code} CASCADE; COMMIT;
                        DROP TABLE IF EXISTS dssnrw.p_temp_{$pwa_code} CASCADE; COMMIT;

                        CREATE TABLE dssnrw.p_temp_{$pwa_code} (
                        pipe_id numeric(10) NOT NULL DEFAULT NULL,
                        pwa_code varchar(10) COLLATE pg_catalog.default DEFAULT NULL,
                        wkb_geometry public.geometry DEFAULT NULL,
                        idx serial,
                        gen_geometry public.geometry DEFAULT NULL,
                        CONSTRAINT p_temp_pkey_{$pwa_code} PRIMARY KEY (idx)
                        )
                        ;

                        ALTER TABLE dssnrw.p_temp_{$pwa_code}
                        OWNER TO gispwadb;

                        CREATE INDEX p_temp_gengeometry_idx_{$pwa_code} ON dssnrw.p_temp_{$pwa_code} USING gist (
                        gen_geometry public.gist_geometry_ops_2d
                        );

                        CREATE INDEX p_temp_geom_idx_{$pwa_code} ON dssnrw.p_temp_{$pwa_code} USING gist (
                        wkb_geometry public.gist_geometry_ops_2d
                        );  " ; 

    $create_result = pg_exec($connection, $strTemp);

    return $create_result;
} 
