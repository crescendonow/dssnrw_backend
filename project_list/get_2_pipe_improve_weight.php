<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
error_reporting(~E_NOTICE);
@ini_set('display_errors', '0'); //ไม่แสดงerror

include '../connect.php';
include '../basic_data.php';

//if ($_SERVER['REQUEST_METHOD'] === "REQUEST") { check session 

$act = $_POST['act'];
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
$proj_fiscal = $_POST['proj_fiscal'];
$proj_budget = $_POST['proj_budget'];
$proj_name = $_POST['proj_name'];
$proj_remark = $_POST['proj_remark'];
$project_prov_id = $_POST['proj_prov_id'];



$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';


$leak_data = get_leak_summary($pwa_code, $connection);
$cost_data = get_repair_summary($pwa_code, $connection);

//$project_prov_name =iconv( "utf-8", "windows-874",$_POST['project_prov_name']);
//$remark =iconv( "utf-8", "windows-874",$_POST['remark']);

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

        $data['status'] = 'true';
        $data['message'] = "There are {$rec['cnt']} records found. ";

        //get conclusion data 
        $sqlPipesumfreq = "SET CLIENT_ENCODING TO 'utf-8' ; 
                        SELECT COUNT( DISTINCT pipe_id) as c_pipe, 
                        SUM(DISTINCT pipe_long) AS l_pipe, 
                        SUM(c_leak) AS c_leak,
                        SUM(CASE WHEN cost_repair IS NOT NULL THEN cost_repair ELSE 0 END) as cost_repair, 
                        ROUND(SUM(c_leak)/SUM(DISTINCT pipe_long)) avg_lk
                                FROM dssnrw.pipe_improve pt
                        WHERE project_prov_id = '{$project_prov_id}'      ";


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

                        $data['pw_sum_freq'] = array(
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
                                pp.w_age w_age,
                                MAX(pp.pipe_age_ww) pipe_age_ww, SUM(pp.c_pipe) c_pipe, SUM(pp.l_pipe) l_pipe, SUM(pp.c_leak) c_leak, MAX(pp.w_leak) w_leak, MAX(pp.c_leak_ww) c_leak_ww, 
                                SUM(pp.cost_repair) cost_repair, MAX(pp.w_cost) w_cost, MAX(pp.cost_repair_ww) cost_repair_ww, MAX(pp.elev) elev, MAX(pp.w_elev) w_elev, MAX(pp.elev_ww) elev_ww,
                                MAX(pp.ptype) ptype, MAX(pp.w_ptype) w_ptype, MAX(pp.ptype_ww) ptype_ww, MAX(pp.pressure) pressure, MAX(pp.w_pressure) w_pressure, MAX(pp.pressure_ww) pressure_ww,
                                {$w_branch} w_branch, MAX(pp.branch_ww) branch_ww, MAX(pp.w_dma) w_dma, MAX(pp.dma_ww) dma_ww,   
                                SUM(CASE WHEN pp.sum_ww >= 0 AND pp.sum_ww <= 1 THEN c_pipe ELSE 0 END) lr,
                                SUM(CASE WHEN pp.sum_ww >= 0 AND pp.sum_ww <= 1 THEN l_pipe ELSE 0 END) long_lr,
                                SUM(CASE WHEN pp.sum_ww > 1 AND pp.sum_ww <= 2 THEN c_pipe ELSE 0 END) mr,
                                SUM(CASE WHEN pp.sum_ww > 1 AND pp.sum_ww <= 2 THEN l_pipe ELSE 0 END) long_mr,
                                SUM(CASE WHEN pp.sum_ww > 2 AND pp.sum_ww <= 3 THEN c_pipe ELSE 0 END) hr,
                                SUM(CASE WHEN pp.sum_ww > 2 AND pp.sum_ww <= 3 THEN l_pipe ELSE 0 END) long_hr  
                                FROM 
                        (SELECT ROUND(SUM(c_leak)/SUM(DISTINCT pipe_long), 2) avg_lk,
                        pipe_type, pipe_size, pipe_age,
                        {$w_age} w_age, {$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                WHEN  pipe_age >= {$age_data['value_1']}
                                                AND  pipe_age <= {$age_data['value_2']} THEN 2 
                                                WHEN   pipe_age >  {$age_data['value_2']} THEN 3 
                                                ELSE 0 END) pipe_age_ww, 
                        COUNT(pipe_id) as c_pipe, SUM(DISTINCT pipe_long) AS l_pipe, 
                        SUM(c_leak) AS c_leak, {$w_leak} AS w_leak, 
                        {$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                                WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                        AND SUM(c_leak) <= {$leak_data['value_2']} THEN 2
                                WHEN  SUM(c_leak) > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                        SUM(CASE WHEN cost_repair IS NOT NULL THEN cost_repair ELSE 0 END) AS cost_repair, 
                        {$w_cost} w_cost, 
                        {$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                WHEN  SUM(cost_repair) >= {$cost_data['value_1']}  
                                AND  SUM(cost_repair) <= {$cost_data['value_2']}  THEN 2
                                WHEN  SUM(cost_repair) > {$cost_data['value_2']}  THEN 3 
                                ELSE 1 END) cost_repair_ww, 
                        {$w_branch} * r_score , 
                        0 elev, 0 w_elev, 0 elev_ww, 
                        0 ptype, 
                        {$w_ptype} w_ptype, 
                        {$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)  ptype_ww, 
                        0 pressure, 0 w_pressure, 0 pressure_ww, {$w_branch} w_branch, {$w_branch} * r_score branch_ww, 
                        {$w_dma} w_dma, {$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                        WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                        WHEN dma_nrw > 40 THEN 3 ELSE 0 END) dma_ww, 
                        ({$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                        WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                AND SUM(c_leak) <= {$leak_data['value_2']} THEN 2
                        WHEN  SUM(c_leak) > {$leak_data['value_2']} THEN 3 END)) 
                        + ({$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                        WHEN  SUM(cost_repair) >= {$cost_data['value_1']} 
                        AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                        WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                        ELSE 1 END)) 
                        + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                WHEN pipe_age >= {$age_data['value_1']}
                                                AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                ELSE 0 END)) 
                        + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) +  
                                ({$w_branch} * r_score )  
                                +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                        WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                        WHEN dma_nrw > 40 THEN 3 ELSE 0 END))  sum_ww
                        FROM dssnrw.pipe_improve 
                        WHERE project_prov_id = '{$project_prov_id}' 
                        GROUP BY pipe_type, pipe_size,pipe_age, cost_repair, r_score, dma_nrw
                        ORDER BY pipe_type, pipe_size, pipe_age, cost_repair) pp 
                        GROUP BY pp.pipe_type, pp.pipe_size, pp.w_age 
                        ORDER BY pp.pipe_type, pp.pipe_size ";

        $result_freq = pg_exec($connection, $sqlPipetypesizefreq_temp);
        $numrows = pg_numrows($result_freq);


        if (!$result_freq) {
                $data['status'] = 'false';
                $data['message'] = json_encode(pg_last_error($db));
                $data['pw_typesize_freq'] = array();
        } else {
                $pwt_typesize = array();
                $data['status'] = 'true';
                $data['message'] = 'Complete get conclusion data from pipe target with weight';

                for ($ri = 0; $ri < $numrows; $ri++) {
                        $row = pg_fetch_array($result_freq, $ri);

                        array_push($pwt_typesize, array(
                                'avg_lk' => $row['avg_lk'],
                                'pipe_type' => $row['pipe_type'],
                                'pipe_size' => $row['pipe_size'],
                                'age_max' => $row['age_max'],
                                'age_min' => $row['age_min'],
                                'w_age' => $row['w_age'],
                                'pipe_age_ww' => $row['pipe_age_ww'],
                                'c_pipe' => $row['c_pipe'],
                                'l_pipe' => $row['l_pipe'],
                                'c_leak' => $row['c_leak'],
                                'w_leak' => $w_leak,
                                'c_leak_ww' => $row['c_leak_ww'],
                                'cost_repair' => $row['cost_repair'],
                                'w_cost' => $row['w_cost'],
                                'cost_repair_ww' => $row['cost_repair_ww'],
                                'elev' => $row['elev'],
                                'w_elev' => $row['w_elev'],
                                'elev_ww' => $row['elev_ww'],
                                'ptype' => $row['ptype'],
                                'w_ptype' => $row['w_ptype'],
                                'ptype_ww' => $row['ptype_ww'],
                                'pressure' => $row['pressure'],
                                'w_pressure' => $row['w_pressure'],
                                'pressure_ww' => $row['pressure_ww'],
                                'w_branch' => $row['w_branch'],
                                'branch_ww' => $row['branch_ww'],
                                'lr' => $row['lr'],
                                'long_lr' => $row['long_lr'],
                                'mr' => $row['mr'],
                                'long_mr' => $row['long_mr'],
                                'hr' => $row['hr'],
                                'long_hr' => $row['long_hr'],
                        ));
                }

                $data['pw_typesize_freq'] = $pwt_typesize;
        }

        $sqlPipetypesizefreq_dma = " SET CLIENT_ENCODING TO 'utf-8';
                                        SELECT 
                                        CASE WHEN SUM(pp.l_pipe) <> 0
                                        THEN ROUND(SUM(pp.c_leak) / SUM(pp.l_pipe), 2)
                                        ELSE 0
                                        END AS avg_lk,

                                        pp.dma_no,
                                        pp.dma_name,
                                        pp.pipe_type,
                                        pp.pipe_size,

                                        MAX(pp.pipe_age) AS age_max,
                                        MIN(pp.pipe_age) AS age_min,

                                        pp.w_age,
                                        MAX(pp.pipe_age_ww)     AS pipe_age_ww,

                                        SUM(pp.c_pipe)          AS c_pipe,
                                        SUM(pp.l_pipe)          AS l_pipe,
                                        SUM(pp.c_leak)          AS c_leak,

                                        MAX(pp.w_leak)          AS w_leak,
                                        MAX(pp.c_leak_ww)       AS c_leak_ww,

                                        SUM(pp.cost_repair)     AS cost_repair,
                                        MAX(pp.w_cost)          AS w_cost,
                                        MAX(pp.cost_repair_ww)  AS cost_repair_ww,

                                        MAX(pp.elev)            AS elev,
                                        MAX(pp.w_elev)          AS w_elev,
                                        MAX(pp.elev_ww)         AS elev_ww,

                                        MAX(pp.ptype)           AS ptype,
                                        MAX(pp.w_ptype)         AS w_ptype,
                                        MAX(pp.ptype_ww)        AS ptype_ww,

                                        MAX(pp.pressure)        AS pressure,
                                        MAX(pp.w_pressure)      AS w_pressure,
                                        MAX(pp.pressure_ww)     AS pressure_ww,

                                        {$w_branch}             AS w_branch,    
                                        MAX(pp.branch_ww)       AS branch_ww,
                                        MAX(pp.w_dma)           AS w_dma,
                                        MAX(pp.dma_ww)          AS dma_ww,

                                        SUM(CASE WHEN pp.sum_ww >= 0 AND pp.sum_ww <= 1 THEN pp.c_pipe ELSE 0 END) AS lr,
                                        SUM(CASE WHEN pp.sum_ww >= 0 AND pp.sum_ww <= 1 THEN pp.l_pipe ELSE 0 END) AS long_lr,
                                        SUM(CASE WHEN pp.sum_ww > 1 AND pp.sum_ww <= 2 THEN pp.c_pipe ELSE 0 END)  AS mr,
                                        SUM(CASE WHEN pp.sum_ww > 1 AND pp.sum_ww <= 2 THEN pp.l_pipe ELSE 0 END)  AS long_mr,
                                        SUM(CASE WHEN pp.sum_ww > 2 AND pp.sum_ww <= 3 THEN pp.c_pipe ELSE 0 END)  AS hr,
                                        SUM(CASE WHEN pp.sum_ww > 2 AND pp.sum_ww <= 3 THEN pp.l_pipe ELSE 0 END)  AS long_hr

                                        FROM (
                                        SELECT
                                        ROUND(SUM(c_leak)/SUM(DISTINCT pipe_long), 2) AS avg_lk,
                                        pipe_type, pipe_size, pipe_age,

                                        {$w_age} AS w_age,
                                        {$w_age} * (
                                        CASE WHEN pipe_age < {$age_data['value_1']} THEN 1
                                                WHEN pipe_age BETWEEN {$age_data['value_1']} AND {$age_data['value_2']} THEN 2
                                                WHEN pipe_age > {$age_data['value_2']} THEN 3
                                                ELSE 0 END
                                        ) AS pipe_age_ww,

                                        COUNT(pipe_id)            AS c_pipe,
                                        SUM(DISTINCT pipe_long)   AS l_pipe,
                                        SUM(c_leak)               AS c_leak,

                                        {$w_leak} AS w_leak,
                                        {$w_leak} * (
                                        CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1
                                                WHEN SUM(c_leak) BETWEEN {$leak_data['value_1']} AND {$leak_data['value_2']} THEN 2
                                                WHEN SUM(c_leak) > {$leak_data['value_2']} THEN 3 END
                                        ) AS c_leak_ww,

                                        SUM(CASE WHEN cost_repair IS NOT NULL THEN cost_repair ELSE 0 END) AS cost_repair,

                                        {$w_cost} AS w_cost,
                                        {$w_cost} * (
                                        CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                                WHEN SUM(cost_repair) BETWEEN {$cost_data['value_1']} AND {$cost_data['value_2']} THEN 2
                                                WHEN SUM(cost_repair) > {$cost_data['value_2']} THEN 3
                                                ELSE 1 END
                                        ) AS cost_repair_ww,

                                        {$w_branch} * r_score AS branch_ww,

                                        0 AS elev, 0 AS w_elev, 0 AS elev_ww,
                                        0 AS ptype,
                                        {$w_ptype} AS w_ptype,
                                        {$w_ptype} * (
                                        CASE WHEN pipe_type IN ('ST','ST_UN','ST_ON','ST_CV','GS','DI','CI') THEN 1
                                                WHEN pipe_type IN ('PVC','PVC_O','HDPE','PB') THEN 2
                                                WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END
                                        ) AS ptype_ww,

                                        0 AS pressure, 0 AS w_pressure, 0 AS pressure_ww,

                                        {$w_branch} AS w_branch,
                                        {$w_dma}    AS w_dma,
                                        {$w_dma} * (
                                        CASE WHEN dma_nrw <= 30 THEN 1
                                                WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                WHEN dma_nrw > 40 THEN 3 ELSE 0 END
                                        ) AS dma_ww,

                                        ({$w_leak} * (
                                        CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1
                                                WHEN SUM(c_leak) BETWEEN {$leak_data['value_1']} AND {$leak_data['value_2']} THEN 2
                                                WHEN SUM(c_leak) > {$leak_data['value_2']} THEN 3 END))
                                        +
                                        ({$w_cost} * (
                                        CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                                WHEN SUM(cost_repair) BETWEEN {$cost_data['value_1']} AND {$cost_data['value_2']} THEN 2
                                                WHEN SUM(cost_repair) > {$cost_data['value_2']} THEN 3 ELSE 1 END))
                                        +
                                        ({$w_age} * (
                                        CASE WHEN pipe_age < {$age_data['value_1']} THEN 1
                                                WHEN pipe_age BETWEEN {$age_data['value_1']} AND {$age_data['value_2']} THEN 2
                                                WHEN pipe_age > {$age_data['value_2']} THEN 3 ELSE 0 END))
                                        +
                                        ({$w_ptype} * (
                                        CASE WHEN pipe_type IN ('ST','ST_UN','ST_ON','ST_CV','GS','DI','CI') THEN 1
                                                WHEN pipe_type IN ('PVC','PVC_O','HDPE','PB') THEN 2
                                                WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END))
                                        +
                                        ({$w_branch} * r_score)
                                        +
                                        ({$w_dma} * (
                                        CASE WHEN dma_nrw <= 30 THEN 1
                                                WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                WHEN dma_nrw > 40 THEN 3 ELSE 0 END))
                                        AS sum_ww,

                                        dma_no,
                                        dma_name

                                        FROM dssnrw.pipe_improve
                                        WHERE project_prov_id = '{$project_prov_id}'
                                        GROUP BY
                                        pipe_type, pipe_size, pipe_age, cost_repair, r_score, dma_nrw, dma_no, dma_name
                                        ) pp
                                        GROUP BY
                                        pp.dma_no, pp.dma_name,
                                        pp.pipe_type, pp.pipe_size,
                                        pp.w_age
                                        ORDER BY
                                        pp.dma_no, pp.dma_name, pp.pipe_type, pp.pipe_size; "; 

     $Pipetypesizefreq_dma_result = pg_exec($connection, $sqlPipetypesizefreq_dma);

    $numrows = pg_numrows($Pipetypesizefreq_dma_result);

    if (!$Pipetypesizefreq_dma_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['pw_typesize_freq_dma'] = array();
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for pipe type size with weight and dma.';

        $pw_typesize_dma = array();

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($Pipetypesizefreq_dma_result, $ri);

            array_push($pw_typesize_dma, array(
                'avg_lk' => $row['avg_lk'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'age_max' => $row['age_max'],
                'age_min' => $row['age_min'],
                'w_age' => $row['w_age'],
                'pipe_age_ww' => $row['pipe_age_ww'],
                'c_pipe' => $row['c_pipe'],
                'l_pipe' => $row['l_pipe'],
                'c_leak' => $row['c_leak'],
                'w_leak' => $w_leak,
                'c_leak_ww' => $row['c_leak_ww'],
                'cost_repair' => $row['cost_repair'],
                'w_cost' => $row['w_cost'],
                'cost_repair_ww' => $row['cost_repair_ww'],
                'elev' => $row['elev'],
                'w_elev' => $row['w_elev'],
                'elev_ww' => $row['elev_ww'],
                'ptype' => $row['ptype'],
                'w_ptype' => $row['w_ptype'],
                'ptype_ww' => $row['ptype_ww'],
                'pressure' => $row['pressure'],
                'w_pressure' => $row['w_pressure'],
                'pressure_ww' => $row['pressure_ww'],
                'w_branch' => $row['w_branch'],
                'branch_ww' => $row['branch_ww'],
                'dma_no' => $row['dma_no'],
                'dma_name' => $row['dma_name'],
                'lr' => $row['lr'],
                'long_lr' => $row['long_lr'],
                'mr' => $row['mr'],
                'long_mr' => $row['long_mr'],
                'hr' => $row['hr'],
                'long_hr' => $row['long_hr'],
            ));
        }

        $data['pw_typesize_freq_dma'] = $pw_typesize_dma;
    }

        //group of weight in 3 level 
        $sqlGroupWeight =  " SET CLIENT_ENCODING TO 'utf-8'; 
                        SELECT 
                        SUM(CASE WHEN sw.sum_ww >= 0 AND sw.sum_ww <= 1 THEN 1 ELSE 0 END) lr,
                        SUM(CASE WHEN sw.sum_ww >= 0 AND sw.sum_ww <= 1 THEN pipe_long ELSE 0 END) long_lr,
                        SUM(CASE WHEN sw.sum_ww > 1 AND sw.sum_ww <= 2 THEN 1 ELSE 0 END) mr,
                        SUM(CASE WHEN sw.sum_ww > 1 AND sw.sum_ww <= 2 THEN pipe_long ELSE 0 END) long_mr,
                        SUM(CASE WHEN sw.sum_ww > 2 AND sw.sum_ww <= 3 THEN 1 ELSE 0 END) hr,
                        SUM(CASE WHEN sw.sum_ww > 2 AND sw.sum_ww <= 3 THEN pipe_long ELSE 0 END) long_hr
                        FROM 
                        (SELECT pipe_id, pipe_long,
                        ({$w_leak} * 
                        (CASE WHEN c_leak < {$leak_data['value_1']} THEN 1 
                        WHEN  c_leak >= {$leak_data['value_1']}
                                AND c_leak <= {$leak_data['value_2']} THEN 2
                        WHEN  c_leak > {$leak_data['value_2']} THEN 3 END )
                        + ({$w_cost} * 
                        (CASE WHEN cost_repair < {$cost_data['value_1']} THEN 1
                        WHEN  cost_repair >= {$cost_data['value_1']}
                        AND  cost_repair <= {$cost_data['value_2']} THEN 2
                        WHEN  cost_repair > {$cost_data['value_2']} THEN 3 
                        ELSE 1 END)) 
                        + ({$w_age} * 
                        (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                WHEN pipe_age >= {$age_data['value_1']}
                                AND pipe_age <= {$age_data['value_2']} THEN 2 
                                WHEN  pipe_age > {$age_data['value_2']} THEN 3 
                                ELSE 0 END) ) 
                        + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END) )
                        + ({$w_branch} * rg.r_score) 
                        +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                WHEN dma_nrw > 40 THEN 3 ELSE 0 END))
                        ) sum_ww 
                                FROM dssnrw.pipe_improve ps 
                                LEFT JOIN 
                                (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group ) rg 
                                ON ps.pwa_code = rg.pwa_code 
                                WHERE ps.pwa_code = '{$pwa_code}' AND ps.project_prov_id = '{$project_prov_id}' ) sw ";

                                //echo $sqlGroupWeight;
                                //exit();

        $sqlGroupWeight_result = pg_exec($connection, $sqlGroupWeight);

        $numrows = pg_numrows($sqlGroupWeight_result);

        if (!$sqlGroupWeight_result) {
                $data['status'] = 'false';
                $data['message'] = json_encode(pg_last_error($db));
                $data['pw_group_weight'] = array();
        } else {

                $data['status'] = 'true';
                $data['message'] = 'complete for pw_group_weight.';

                for ($ri = 0; $ri < $numrows; $ri++) {
                        $row = pg_fetch_array($sqlGroupWeight_result, $ri);

                        $data['pw_group_weight'] = array(
                                'lr' => $row['lr'],
                                'long_lr' => $row['long_lr'],
                                'mr' => $row['mr'],
                                'long_mr' => $row['long_mr'],
                                'hr' => $row['hr'],
                                'long_hr' => $row['long_hr'],
                        );
                }
        }


        //get feature by geojson 
        $sql_get_project = "SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                    FROM (SELECT 'Feature' As type 
                    , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                    , row_to_json((SELECT l FROM (SELECT pipe_id, avg_lk, c_leak, w_leak, c_leak_ww,
                                    cost_repair, w_cost, cost_repair_ww, pipe_type, pipe_size, pipe_age, 
                                    w_age, pipe_age_ww, elev, w_elev, elev_ww, ptype, w_ptype, ptype_ww,
                                    pressure, w_pressure, pressure_ww, w_dma, dma_ww, w_branch, branch_ww,
                                    sum_ww, pipe_long, project_no, contrac_date,   
                                    asset_code, pipe_func, laying, product, depth, locate, dma_no, dma_name ) As l 
                    )) As properties 
                    FROM (SELECT pipe_id, 
                                    CASE WHEN sum(DISTINCT pipe_long) != 0 
                                    THEN ROUND(SUM(DISTINCT c_leak)/SUM(DISTINCT pipe_long),
                2) ELSE 0 END avg_lk,
                                    SUM(DISTINCT c_leak) AS c_leak,
                {$w_leak} w_leak,
                {$w_leak} * 
                                    (CASE WHEN SUM(DISTINCT c_leak) < {$leak_data['value_1']} THEN 1 
                                        WHEN  SUM(DISTINCT c_leak) >= {$leak_data['value_1']}
                                                AND SUM(DISTINCT c_leak) <= {$leak_data['value_2']} THEN 2
                                        WHEN  SUM(DISTINCT c_leak) > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                                        SUM(DISTINCT cost_repair) cost_repair,
                {$w_cost} w_cost,
                {$w_cost} * 
                                    (CASE WHEN SUM(DISTINCT cost_repair) < {$cost_data['value_1']} THEN 1
                                        WHEN  SUM(DISTINCT cost_repair) >= {$cost_data['value_1']} 
                                        AND  SUM(DISTINCT cost_repair) <= {$cost_data['value_2']} THEN 2
                                        WHEN  SUM(DISTINCT cost_repair) > {$cost_data['value_2']} THEN 3 
                                        ELSE 1 END) cost_repair_ww, 
                                    pipe_type, pipe_size, 
                                        SUM(DISTINCT pipe_age) AS pipe_age,
                {$w_age} w_age,
                {$w_age} * (CASE WHEN  SUM(DISTINCT pipe_age) < {$age_data['value_1']} THEN 1 
                                        WHEN SUM(DISTINCT pipe_age) >= {$age_data['value_1']}
                                        AND SUM(DISTINCT pipe_age) <= {$age_data['value_2']} THEN 2 
                                        WHEN SUM(DISTINCT pipe_age) >  {$age_data['value_2']} THEN 3 
                                        ELSE 0 END) pipe_age_ww,
                {$w_branch} w_branch, 
                {$w_branch} * r_score branch_ww, 
                0 elev,
                0 w_elev,
                0 elev_ww,
                pipe_type ptype,
                {$w_ptype} w_ptype,
                {$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END) ptype_ww,
                0 pressure,
                0 w_pressure,
                0 pressure_ww,
                {$w_dma} w_dma, {$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                    WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                    WHEN dma_nrw > 40 THEN 3 ELSE 0 END) dma_ww, 

                                    ({$w_leak} * 
                                    (CASE WHEN SUM(DISTINCT c_leak) < {$leak_data['value_1']} THEN 1 
                                        WHEN  SUM(DISTINCT c_leak) >= {$leak_data['value_1']}
                                                AND SUM(DISTINCT c_leak) <= {$leak_data['value_2']} THEN 2
                                        WHEN SUM(DISTINCT c_leak) > {$leak_data['value_2']} THEN 3 END )
                                        + ({$w_cost} * 
                                        (CASE WHEN SUM(DISTINCT cost_repair) < {$cost_data['value_1']} THEN 1
                                        WHEN  SUM(DISTINCT cost_repair) >= {$cost_data['value_1']} 
                                        AND  SUM(DISTINCT cost_repair) <= {$cost_data['value_2']} THEN 2
                                        WHEN  SUM(DISTINCT cost_repair) > {$cost_data['value_2']} THEN 3 
                                        ELSE 1 END)) 
                                        + ({$w_age} * 
                                        (CASE WHEN  SUM(DISTINCT pipe_age) < {$age_data['value_1']} THEN 1 
                                            WHEN SUM(DISTINCT pipe_age) >= {$age_data['value_1']}
                                            AND SUM(DISTINCT pipe_age) <= {$age_data['value_2']} THEN 2 
                                            WHEN  SUM(DISTINCT pipe_age) >  {$age_data['value_2']} THEN 3 
                                            ELSE 0 END) ) 
                                            + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                                            WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                                            WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) 
                                        + ({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                    WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                    WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) 
                                                    + ({$w_branch} * r_score) ) sum_ww, 
                                    SUM(DISTINCT pipe_long) AS pipe_long, 
                                    CASE WHEN project_no IS NULL THEN '' ELSE project_no END project_no, 
                                    contrac_date, 
                                    CASE WHEN asset_code IS NULL THEN '' ELSE asset_code END asset_code, 
                                    pipe_func, laying, product, depth, locate, wkb_geometry, dma_no, dma_name  
                            FROM dssnrw.pipe_improve 
                            WHERE project_prov_id = '{$project_prov_id}' 
                            GROUP BY pipe_id, pipe_type, pipe_size, yearinstall, r_score, wkb_geometry, 
                                    contrac_date, asset_code, pipe_func, laying, product, 
                                    depth, locate, project_no, dma_nrw, dma_no, dma_name  
                            ORDER BY pipe_id ) As lg ) As f )  As fc  ";

        //echo $sql_get_project;
        //exit();

        $resultmap = pg_exec($connection, $sql_get_project);

        if (!$resultmap) {
                $data['status'] = 'false';
                $data['message'] = 'There have some problem in database.';
                $data['geo_map']  =  array();
        } else {
                $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
                $data['geo_map']  =  $arr[0];
        }
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode($data);

//}
