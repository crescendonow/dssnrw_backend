<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Content-Type: application/json; charset=utf-8');
error_reporting(~E_NOTICE);
@ini_set('display_errors', '1'); //ไม่แสดงerror


try {

    include '../connect.php';
    include '../basic_data.php';
} catch (Exception $e) {
    echo 'Message: ' . $e->getMessage();
}

//if ($_SERVER['REQUEST_METHOD'] === "REQUEST") { check session 

$pwa_code = $_POST['pwa_code'];
$arrReg = array("5511" => "9", "5512" => "10", "5521" => "6", "5522" => "7", "5531" => "1", "5532" => "8", "5541" => "2", "5542" => "3", "5551" => "4", "5552" => "5");
$zone = $arrReg[substr($_POST['pwa_code'], 0, 4)];

//input weight from user 
$w_age = $_REQUEST['w_age'];
$w_leak = $_REQUEST['w_leak'];
$w_cost = $_REQUEST['w_cost'];
$w_elev = $_REQUEST['w_elev'];
$w_ptype = $_REQUEST['w_ptype'];
$w_pressure = $_REQUEST['w_pressure'];
$w_type = $_REQUEST['w_type'];
$w_branch = $_REQUEST['w_branch'];
$w_dma = $_REQUEST['w_dma'];

//input for pipe project save 
$project_prov_id = $_POST['proj_prov_id'];

$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';

//$project_prov_name =iconv( "utf-8", "windows-874",$_POST['project_prov_name']);
//$remark =iconv( "utf-8", "windows-874",$_POST['remark']);

$leak_data = get_leak_summary($pwa_code, $connection);
$cost_data = get_repair_summary($pwa_code, $connection);

//check count of result 
$sql_check = " SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT COUNT(*) AS cnt FROM dssnrw.pipe_improve 
                    WHERE project_prov_id = '{$project_prov_id}'
                    ";

$check_num_id = pg_exec($connection, $sql_check);
$rec = pg_fetch_assoc($check_num_id);

$numrows = pg_numrows($check_num_id);

if (!$check_num_id) {
    $data['status'] = 'false';
    $data['message'] = 'Project id not found in database.';
} else {

    $sqlPipesumfreq = "SET CLIENT_ENCODING TO 'utf-8' ; 
                       SELECT COUNT( DISTINCT pipe_id) as c_pipe, 
                        SUM(DISTINCT pipe_long) AS l_pipe, 
                        SUM(c_leak) AS c_leak,
                        SUM(CASE WHEN cost_repair IS NOT NULL THEN cost_repair ELSE 0 END) as cost_repair, 
                        ROUND(SUM(c_leak)/SUM(DISTINCT pipe_long)) avg_lk
                        FROM dssnrw.pipe_improve 
						WHERE project_prov_id = '{$project_prov_id}' ";

    $Psumfreq_result = pg_exec($connection, $sqlPipesumfreq);

    $numrows = pg_numrows($Psumfreq_result);

    if (!$Psumfreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['pw_sum_freq'] = array();
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for pipe_sum_freq.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($Psumfreq_result, $ri);

            $data['pid_sum_freq'] = array(
                'c_pipe' => $row['c_pipe'],
                'l_pipe' => $row['l_pipe'],
                'c_leak' => $row['c_leak'],
                'cost_repair' => $row['cost_repair'],
                'avg_lk' => $row['avg_lk'],
            );
        }
    }

    $sqlPipetypesizefreq_temp =  " SET CLIENT_ENCODING TO 'utf-8';  
                                SELECT CASE WHEN sum(pp.l_pipe)!=0 THEN ROUND(SUM(pp.c_leak)/sum(pp.l_pipe),
                                2)ELSE 0 END avg_lk, pp.pipe_type, pp.pipe_size, MAX(pp.pipe_age) age_max, MIN(pp.pipe_age) age_min,
                                SUM(pp.c_pipe) c_pipe, SUM(pp.l_pipe) l_pipe, SUM(pp.c_leak) c_leak, 
                                SUM(pp.cost_repair) cost_repair, MAX(pp.elev) elev,
                                MAX(pp.ptype) ptype, MAX(pp.pressure) pressure
                                FROM 
                                    (SELECT CASE WHEN sum(DISTINCT pipe_long) != 0 THEN ROUND(c_leak/sum(DISTINCT pipe_long),2) ELSE 0 END avg_lk,
                                    pipe_type, pipe_size, pipe_age,
                                    COUNT(DISTINCT pipe_id) as c_pipe, sum(DISTINCT pipe_long) AS l_pipe, 
                                    c_leak,  0 ptype, 0 pressure, 0 elev,   
                                    cost_repair, dma_nrw
                                    FROM dssnrw.pipe_improve pm                      
                                    WHERE pwa_code = '{$pwa_code}' AND project_prov_id = '{$project_prov_id}' 
                                    GROUP BY pipe_type, pipe_size,pipe_age, cost_repair, r_score, dma_nrw, c_leak
                                    ORDER BY pipe_type, pipe_size, pipe_age, cost_repair) pp 
                                    GROUP BY pp.pipe_type, pp.pipe_size
                                    ORDER BY pipe_type, pipe_size  ";

    //echo  $sqlPipetypesizefreq_temp;
    //exit() ;

    $result_freq = pg_exec($connection, $sqlPipetypesizefreq_temp);
    $numrows = pg_numrows($result_freq);


    if (!$result_freq) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['pw_typesize_freq'] = array();
    } else {
        $pw_typesize = array();
        $data['status'] = 'true';
        $data['message'] = 'Complete get conclusion data from pipe target with weight';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result_freq, $ri);

            array_push($pw_typesize, array(
                'avg_lk' => $row['avg_lk'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'age_max' => $row['age_max'],
                'age_min' => $row['age_min'],
                'c_pipe' => $row['c_pipe'],
                'l_pipe' => $row['l_pipe'],
                'c_leak' => $row['c_leak'],
                'cost_repair' => $row['cost_repair'],
                'elev' => $row['elev'],
                'ptype' => $row['ptype'],
                'pressure' => $row['pressure'],
            ));
        }

        $data['pid_typesize_freq'] = $pw_typesize;
    }

    $data['status'] = 'true';
    $data['message'] = "There are {$rec['cnt']} records found. ";

    $sqlPipetypesizefreq_dma = " SET CLIENT_ENCODING TO 'utf-8';
                                SELECT 
                                    CASE 
                                        WHEN SUM(pp.l_pipe) != 0 
                                            THEN ROUND(SUM(pp.c_leak) / SUM(pp.l_pipe), 2) 
                                        ELSE 0 
                                    END AS avg_lk,
                                    pp.dma_no,
                                    pp.dma_name,
                                    pp.pipe_type,
                                    pp.pipe_size,
                                    MAX(pp.pipe_age) AS age_max,
                                    MIN(pp.pipe_age) AS age_min,
                                    SUM(pp.c_pipe)   AS c_pipe,
                                    SUM(pp.l_pipe)   AS l_pipe,
                                    SUM(pp.c_leak)   AS c_leak,
                                    SUM(pp.cost_repair) AS cost_repair,
                                    MAX(pp.elev)     AS elev,
                                    MAX(pp.ptype)    AS ptype,
                                    MAX(pp.pressure) AS pressure
                                FROM (
                                    SELECT 
                                        CASE 
                                            WHEN SUM(DISTINCT pipe_long) != 0 
                                                THEN ROUND(c_leak / SUM(DISTINCT pipe_long), 2) 
                                            ELSE 0 
                                        END AS avg_lk,
                                        pipe_type,
                                        pipe_size,
                                        pipe_age,
                                        COUNT(DISTINCT pipe_id) AS c_pipe,
                                        SUM(DISTINCT pipe_long) AS l_pipe,
                                        c_leak,
                                        0 AS ptype,
                                        0 AS pressure,
                                        0 AS elev,
                                        cost_repair,
                                        dma_nrw,
                                        dma_no,
                                        dma_name
                                    FROM dssnrw.pipe_improve pm
                                    WHERE pwa_code = '{$pwa_code}'
                                    AND project_prov_id = '{$project_prov_id}' 
                                    GROUP BY 
                                        pipe_type, pipe_size, pipe_age,
                                        cost_repair, r_score, dma_nrw, c_leak,
                                        dma_no, dma_name
                                ) pp
                                GROUP BY 
                                    pp.dma_no, pp.dma_name, 
                                    pp.pipe_type, pp.pipe_size
                                ORDER BY 
                                    pp.dma_no, pp.dma_name, 
                                    pp.pipe_type, pp.pipe_size; ";

    $Pipetypesizefreq_result = pg_exec($connection, $sqlPipetypesizefreq_dma);

        $numrows = pg_numrows($Pipetypesizefreq_result);

        if (!$Pipetypesizefreq_result) {
            $data['status'] = 'false';
            $data['message'] = json_encode(pg_last_error($db));
            $data['pid_typesize_freq_dma'] = array();
        } else {

            $data['status'] = 'true';
            $data['message'] = 'complete for pipe type size with weight.';

            $pw_typesize_dma = array();

            for ($ri = 0; $ri < $numrows; $ri++) {
                $row = pg_fetch_array($Pipetypesizefreq_result, $ri);

                array_push($pw_typesize_dma, array(
                    'avg_lk' => $row['avg_lk'],
                    'dma_no' => $row['dma_no'],
                    'dma_name' => $row['dma_name'],
                    'pipe_type' => $row['pipe_type'],
                    'pipe_size' => $row['pipe_size'],
                    'age_max' => $row['age_max'],
                    'age_min' => $row['age_min'],
                    'c_pipe' => $row['c_pipe'],
                    'l_pipe' => $row['l_pipe'],
                    'c_leak' => $row['c_leak'],
                    'cost_repair' => $row['cost_repair'],
                    'elev' => $row['elev'],
                    'ptype' => $row['ptype'],
                    'pressure' => $row['pressure'],
                ));
            }

            $data['pid_typesize_freq_dma'] = $pw_typesize_dma;
        }

    //get feature by geojson 
    $sql_get_project = "SET CLIENT_ENCODING TO 'utf-8' ; 
                        SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                        FROM (SELECT 'Feature' As type 
                        , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                        , row_to_json((SELECT l FROM (SELECT pipe_id, fiscal_year, budget_type, project_prov_name, pipe_size, 
                                                        pipe_long, created_date, remark, project_prov_id, 
                                                        pipeold_long, pipe_type, pipe_size, 
                                                        yearinstall, contrac_date, asset_code, pipe_func, laying, product, 
                                                        depth, locate, project_no, dma_no, dma_name) As l 
                        )) As properties 
                        FROM (SELECT * FROM dssnrw.pipe_improve pm
                        WHERE project_prov_id = '{$project_prov_id}' ) As lg ) As f )  As fc   ";

    $resultmap = pg_exec($connection, $sql_get_project);

    if (!$resultmap) {
        $data['status'] = 'false';
        $data['message'] = 'There have some problem in database.';
        $data['geo_map']  =  array();
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
        $data['geo_map']  =  $arr[0];
    }

    $sql_get_ref_project = "SET CLIENT_ENCODING TO 'utf-8' ; 
                            SELECT fiscal_year, budget_type, project_prov_name, proj_cost, remark 
                            FROM dssnrw.ref_pipe_improve 
                            WHERE project_prov_id = '{$project_prov_id}'  ";

    $result = pg_exec($connection, $sql_get_ref_project);
    $numrows = pg_numrows($result);

    for ($ri = 0; $ri < $numrows; $ri++) {
        $row = pg_fetch_array($result, $ri);
        $data['proj_fiscal'] = $row['fiscal_year'];
        $data['proj_budget'] = $row['budget_type'];
        $data['proj_name'] = $row['project_prov_name'];
        $data['proj_cost'] = $row['proj_cost'];
        $data['proj_remark'] = $row['remark'];
    }
}

echo json_encode($data);
pg_close($connection);
//}
