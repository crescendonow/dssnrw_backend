<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
error_reporting(~E_NOTICE);
@ini_set('display_errors', '0'); //ไม่แสดงerror
include 'connect.php';
include 'basic_data.php';

//if ($_SERVER['REQUEST_METHOD'] === "REQUEST") { check session 

$arrReg = array("5511" => "9", "5512" => "10", "5521" => "6", "5522" => "7", "5531" => "1", "5532" => "8", "5541" => "2", "5542" => "3", "5551" => "4", "5552" => "5");
$zone = $arrReg[substr($_POST['pwa_code'], 0, 4)];
$act = $_POST['act'];
$pwa_code = $_POST['pwa_code'];
$pipe_id_form = $_POST['pipe_id_form'];

//input weight from user 
$w_age = $_POST['w_age'];
$w_leak = $_POST['w_leak'];
$w_cost = $_POST['w_cost'];
$w_elev = $_POST['w_elev'];
$w_ptype = $_POST['w_ptype'];
$w_pressure = $_POST['w_pressure'];

//input for pipe project save 
$proj_fiscal = $_POST['proj_fiscal'];
$proj_budget = $_POST['proj_budget'];
$proj_name = $_POST['proj_name'];
$proj_remark = $_POST['proj_remark'];

//$project_prov_name =iconv( "utf-8", "windows-874",$_POST['project_prov_name']);
//$remark =iconv( "utf-8", "windows-874",$_POST['remark']);

//basic data from branch 

$data['age_data'] = $age_data;

$leak_data = get_leak_summary($pwa_code, $connection);
$data['leak_data'] = $leak_data;

$cost_data = get_repair_summary($pwa_code, $connection);
$data['cost_data'] = $cost_data;


if ($act == 'pipe_project_freq_map') {

    $sqlProvName = " SET CLIENT_ENCODING TO 'utf-8'; 
                            SELECT * FROM 
                            dssnrw.ref_pipe_improve 
                            WHERE budget_type = '{$proj_budget}' 
                                AND fiscal_year = '{$proj_fiscal}' 
                                AND pwa_code = '{$pwa_code}'
                            ORDER BY project_prov_name 
                            ";
    //echo $sqlProvName;
    //exit() ;

    $prov_name = pg_exec($connection, $sqlProvName);

    $numrows = pg_numrows($prov_name);

    if (!$prov_name) {
        $data['status'] = 'false';
        $data['message'] = 'Project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get project name.';

        $prov_name_arr = array();
        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($prov_name, $ri);

            array_push($prov_name_arr, array(
                'proj_name' => $row['project_prov_name'],
                'proj_prov_id' => $row['project_prov_id'],
                'proj_fiscal' => $row['fiscal_year'],
                'proj_budget' => $row['budget_type'],
                'proj_budget' => $row['budget_type'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
            ));
        }
        $data['project_prov_name'] = $prov_name_arr;
    }

    $geoSQL = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                FROM (SELECT 'Feature' As type 
                , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                , row_to_json((SELECT l FROM (SELECT pipe_id, budget_type, fiscal_year, avg_lk, c_leak, w_leak, c_leak_ww,
                                cost_repair, w_cost, cost_repair_ww, pipe_type, pipe_size, pipe_age, 
                                w_age, pipe_age_ww, elev, w_elev, elev_ww, ptype, w_ptype, ptype_ww,
                                pressure, w_pressure, pressure_ww, sum_ww, pipe_long, project_no, contrac_date,   
                                asset_code, pipe_func, laying, product, depth, locate) As l 
                )) As properties 
                FROM (SELECT pipe_id, budget_type, fiscal_year, 
                                CASE WHEN sum(DISTINCT pipe_long) != 0 
                                THEN ROUND(c_leak/sum(DISTINCT pipe_long),
            2) ELSE 0 END avg_lk,
                    c_leak,
            {$w_leak} w_leak,
            {$w_leak} * 
                                (CASE WHEN c_leak < {$leak_data['value_1']} THEN 1 
                                    WHEN  c_leak >= {$leak_data['value_1']}
                                            AND c_leak <= {$leak_data['value_2']} THEN 2
                                    WHEN  c_leak > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                                    SUM(cost_repair) cost_repair,
            {$w_cost} w_cost,
            {$w_cost} * 
                                (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                    WHEN  SUM(cost_repair) >= {$cost_data['value_1']} 
                                    AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                                    WHEN  SUM(l.repaircost) > {$cost_data['value_2']} THEN 3 
                                    ELSE 0 END) cost_repair_ww, 
                                pp.pipe_type, pp.pipe_size, 
                                pipe_age,
            {$w_age} w_age,
            {$w_age} * 
                                (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                    WHEN pipe_age >= {$age_data['value_1']}
                                    AND pipe_age <= {$age_data['value_2']} THEN 2 
                                    WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                    ELSE 0 END) pipe_age_ww,
            0 elev,
            0 w_elev,
            0 elev_ww,
            0 ptype,
            0 w_ptype,
            0 ptype_ww,
            0 pressure,
            0 w_pressure,
            0 pressure_ww,
                                ({$w_leak} * 
                                (CASE WHEN c_leak < {$leak_data['value_1']} THEN 1 
                                    WHEN c_leak >= {$leak_data['value_1']}
                                            AND c_leak <= {$leak_data['value_2']} THEN 2
                                    WHEN  c_leak > {$leak_data['value_2']} THEN 3 END )
                                    + ({$w_cost} * 
                                    (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                    WHEN  SUM(cost_repair) >= {$cost_data['value_1']} 
                                    AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                                    WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                                    ELSE 0 END)) 
                                    + ({$w_age} * 
                                    (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                        WHEN pipe_age >= {$age_data['value_1']}
                                        AND pipe_age <= {$age_data['value_2']} THEN 2 
                                        WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                        ELSE 0 END) ) ) sum_ww, 
                                 pipe_long, 
                                CASE WHEN project_no IS NULL THEN '' ELSE project_no END project_no, 
                                contrac_date, 
                                CASE WHEN asset_code IS NULL THEN '' ELSE asset_code END asset_code, 
                                pipe_func, laying, product, depth, locate, wkb_geometry 
                        FROM dssnrw.pipe_improve pt 
                        WHERE pt.fiscal_year = '{$proj_fiscal}' AND pt.budget_type = '{$proj_budget}' AND pt.pwa_code = '{$pwa_code}'
                        GROUP BY pt.pipe_id, pt.budget_type, pt.fiscal_year, pt.id_no, pt.created_date, pt.wkb_geometry, 
                                pp.pipe_type, pp.pipe_size, pp.yearinstall,
                                pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                                pp.depth, pp.locate, pp.project_no
                        ORDER BY pt.created_date DESC, pt.id_no DESC, pt.pipe_id ) As lg ) As f )  As fc  ";

    //echo $geoSQL;
    //exit() ;

    $resultmap = pg_exec($connection, $geoSQL);

    if (!$resultmap) {
        $data['status'] = 'false';
        $data['message'] = 'No data in pg.';
        $data['pipe_project_freq_map']  =  array();
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
        $data['pipe_project_freq_map']  =  $arr[0];
    }
    //echo $arr[0];          
    //pg_close($connection);

    //exit();
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode($data);

//}
