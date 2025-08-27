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

if ($act == "group_project_weight") {
    $group_project_weight_improve = array();
    $group_project_weight_approve = array();

    $q_project_weight_improve = " SET CLIENT_ENCODING TO 'utf-8'; 
                            SELECT imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, SUM(imp.pipe_long) AS sum_long, imp.proj_cost, 
                            imp.remark, imp.uid_insert, 
            ROUND(SUM(imp.sum_ww)/COUNT(*), 2) AS avg_ww FROM 
        (SELECT zone, pwa_code, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, proj_cost, project_prov_id, 
                            remark, uid_insert, 
                            ({$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                                    WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                            AND SUM(c_leak) <= {$leak_data['value_2']}  THEN 2
                                    WHEN  SUM(c_leak) > {$leak_data['value_2']}  THEN 3 END)) 
                                    + ({$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                    WHEN  SUM(cost_repair) >= {$cost_data['value_1']} 
                                    AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                                    WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                                    ELSE 0 END)) 
                                    + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                            WHEN pipe_age >= {$age_data['value_1']}
                                                            AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                            WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                            ELSE 0 END)) 
                                    + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                        WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                        WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) +  
                                        ({$w_branch} * r_score 
                                        +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                    WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                    WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww
                            FROM dssnrw.pipe_improve 
                            WHERE pwa_code = '{$pwa_code}' AND fiscal_year = '{$proj_fiscal}' AND budget_type = '{$proj_budget}' 
                                AND pm.project_prov_id NOT IN (SELECT project_prov_id FROM dssnrw.pipe_approve)   
                            GROUP BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size, 
                            pipe_long, pipe_age, r_score, dma_nrw, proj_cost, remark, uid_insert 
                            ORDER BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size ) imp 
							GROUP BY imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, imp.proj_cost, imp.remark, imp.uid_insert  ";


    $result = pg_exec($connection,  $q_project_weight_improve);

    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'Project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get project name.';


        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_project_weight_improve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'sum_long' => $row['sum_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
                'avg_ww' => $row['avg_ww'],
            ));
        }
    }

    $data['group_project_weight_improve'] = $group_project_weight_improve;

    $q_project_weight_approve = " SET CLIENT_ENCODING TO 'utf-8'; 
                            SELECT imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, SUM(imp.pipe_long) AS sum_long, imp.proj_cost, 
                            imp.remark, imp.uid_insert, 
            ROUND(SUM(imp.sum_ww)/COUNT(*), 2) AS avg_ww FROM 
        (SELECT zone, pwa_code, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, proj_cost, project_prov_id, 
                            remark, uid_insert, 
                            ({$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                                    WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                            AND SUM(c_leak) <= {$leak_data['value_2']}  THEN 2
                                    WHEN  SUM(c_leak) > {$leak_data['value_2']}  THEN 3 END)) 
                                    + ({$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                    WHEN  SUM(cost_repair) >= {$cost_data['value_1']} 
                                    AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                                    WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                                    ELSE 0 END)) 
                                    + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                            WHEN pipe_age >= {$age_data['value_1']}
                                                            AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                            WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                            ELSE 0 END)) 
                                    + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                        WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                        WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) +  
                                        ({$w_branch} * r_score 
                                        +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                    WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                    WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww
                            FROM dssnrw.pipe_approve 
                            WHERE pwa_code = '{$pwa_code}' AND fiscal_year = '{$proj_fiscal}' AND budget_type = '{$proj_budget}' 
                            GROUP BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size, 
                            pipe_long, pipe_age, r_score, dma_nrw, proj_cost, remark, uid_insert 
                            ORDER BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size ) imp 
							GROUP BY imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, imp.proj_cost, imp.remark, imp.uid_insert  ";


    $result = pg_exec($connection,  $q_project_weight_approve);

    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'Project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get project name.';


        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_project_weight_approve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'sum_long' => $row['sum_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
                'avg_ww' => $row['avg_ww'],
            ));
        }
    }

    $data['group_project_weight_approve'] = $group_project_weight_approve;

    $data['message'] = 'Complete for get project and weight.';
}

if ($act == "group_pipetype_weight") {
    $group_pipetype_weight_improve = array();
    $group_pipetype_weight_approve = array();

    $q_pipe_type_weight_improve = "  SET CLIENT_ENCODING TO 'utf-8'; 
                             SELECT imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, SUM(imp.pipe_long) AS sum_long, imp.pipe_type, imp.pipe_size, 
                             imp.remark, imp.uid_insert,  
                    ROUND(CAST(SUM(imp.sum_ww)/COUNT(*) AS numeric), 2) AS avg_ww FROM 
                (SELECT zone, pwa_code, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, proj_cost, project_prov_id, 
                remark, uid_insert,  
                                    ({$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                                            WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                                    AND SUM(c_leak) <= {$leak_data['value_2']}  THEN 2
                                            WHEN  SUM(c_leak) > {$leak_data['value_2']}  THEN 3 END)) 
                                            + ({$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                            WHEN  SUM(cost_repair) >= {$cost_data['value_1']}
                                            AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                                            WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                                            ELSE 0 END)) 
                                            + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                                    WHEN pipe_age >= {$age_data['value_1']}
                                                                    AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                                    WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                                    ELSE 0 END)) 
                                            + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) +  
                                                ({$w_branch} * r_score 
                                                +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww
                                    FROM dssnrw.pipe_improve pm
                                    WHERE pwa_code = '{$pwa_code}' AND fiscal_year = '{$proj_fiscal}' AND budget_type = '{$proj_budget}'  
                                        AND pm.project_prov_id NOT IN (SELECT project_prov_id FROM dssnrw.pipe_approve) 
                        GROUP BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size, 
                            pipe_long, pipe_age, r_score, dma_nrw, proj_cost, remark, uid_insert  
                                    ORDER BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size ) imp 
                                                    GROUP BY imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, imp.pipe_type, imp.pipe_size, imp.remark, imp.uid_insert 
                                                    ORDER BY imp.project_prov_id, imp.project_prov_name, imp.pipe_type, imp.pipe_size       ";

    $result = pg_exec($connection, $q_pipe_type_weight_improve);

    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipetype in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipetype.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipetype_weight_improve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'sum_long' => $row['sum_long'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
                'avg_ww' => $row['avg_ww'],
            ));
        }
    }
    $data['group_pipetype_weight_improve'] = $group_pipetype_weight_improve;

    $q_pipe_type_weight_approve = "  SET CLIENT_ENCODING TO 'utf-8'; 
                             SELECT imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, SUM(imp.pipe_long) AS sum_long, imp.pipe_type, imp.pipe_size, 
                             imp.remark, imp.uid_insert,  
                    ROUND(CAST(SUM(imp.sum_ww)/COUNT(*) AS numeric), 2) AS avg_ww FROM 
                (SELECT zone, pwa_code, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, proj_cost, project_prov_id, 
                remark, uid_insert,  
                                    ({$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                                            WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                                    AND SUM(c_leak) <= {$leak_data['value_2']}  THEN 2
                                            WHEN  SUM(c_leak) > {$leak_data['value_2']}  THEN 3 END)) 
                                            + ({$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                            WHEN  SUM(cost_repair) >= {$cost_data['value_1']}
                                            AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                                            WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                                            ELSE 0 END)) 
                                            + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                                    WHEN pipe_age >= {$age_data['value_1']}
                                                                    AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                                    WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                                    ELSE 0 END)) 
                                            + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) +  
                                                ({$w_branch} * r_score 
                                                +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww
                                    FROM dssnrw.pipe_approve pm
                                    WHERE pwa_code = '{$pwa_code}' AND fiscal_year = '{$proj_fiscal}' AND budget_type = '{$proj_budget}'  
                        GROUP BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size, 
                            pipe_long, pipe_age, r_score, dma_nrw, proj_cost, remark, uid_insert  
                                    ORDER BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size ) imp 
                                                    GROUP BY imp.zone, imp.pwa_code, imp.project_prov_id, imp.project_prov_name, imp.pipe_type, imp.pipe_size, imp.remark, imp.uid_insert 
                                                    ORDER BY imp.project_prov_id, imp.project_prov_name, imp.pipe_type, imp.pipe_size       ";

    $result = pg_exec($connection, $q_pipe_type_weight_approve);

    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipetype in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipetype.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipetype_weight_approve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'sum_long' => $row['sum_long'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
                'avg_ww' => $row['avg_ww'],
            ));
        }
    }
    $data['group_pipetype_weight_approve'] = $group_pipetype_weight_approve;
    $data['message'] = 'Complete for get pipe_type and weight.';
}

if ($act == "group_pipeid_weight") {
    $group_pipeid_weight_improve = array();
    $group_pipeid_weight_approve = array();

    $q_pipe_id_weight_improve = " SET CLIENT_ENCODING TO 'utf-8'; 
                        SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, 
                        remark, uid_insert, 
                    ({$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                            WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                    AND SUM(c_leak) <= {$leak_data['value_2']}  THEN 2
                            WHEN  SUM(c_leak) > {$leak_data['value_2']}  THEN 3 END)) 
                            + ({$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                            WHEN  SUM(cost_repair) >= {$cost_data['value_1']} 
                            AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                            WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                            ELSE 0 END)) 
                            + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                    WHEN pipe_age >= {$age_data['value_1']}
                                                    AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                    WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                    ELSE 0 END)) 
                            + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) +  
                                ({$w_branch} * r_score 
                                +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww
                    FROM dssnrw.pipe_improve pm
                    WHERE pwa_code = '{$pwa_code}' AND fiscal_year = '{$proj_fiscal}' AND budget_type = '{$proj_budget}' 
                        AND pm.project_prov_id NOT IN (SELECT project_prov_id FROM dssnrw.pipe_approve)  
										GROUP BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, 
                                        pipe_size, pipe_long, pipe_age, r_score, dma_nrw, remark, uid_insert 
                    ORDER BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size  ";

    $result = pg_exec($connection, $q_pipe_id_weight_improve);

    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipe_id in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipe_id.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipeid_weight_improve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'pipe_id' => $row['pipe_id'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'pipe_long' => $row['pipe_long'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
                'sum_ww' => $row['sum_ww'],
            ));
        }
    }

    $data['group_pipeid_weight_improve'] = $group_pipeid_weight_improve;

    $q_pipe_id_weight_approve = " SET CLIENT_ENCODING TO 'utf-8'; 
                        SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, 
                        remark, uid_insert, 
                    ({$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                            WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                    AND SUM(c_leak) <= {$leak_data['value_2']}  THEN 2
                            WHEN  SUM(c_leak) > {$leak_data['value_2']}  THEN 3 END)) 
                            + ({$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                            WHEN  SUM(cost_repair) >= {$cost_data['value_1']} 
                            AND  SUM(cost_repair) <= {$cost_data['value_2']} THEN 2
                            WHEN  SUM(cost_repair) > {$cost_data['value_2']} THEN 3 
                            ELSE 0 END)) 
                            + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                    WHEN pipe_age >= {$age_data['value_1']}
                                                    AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                    WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                    ELSE 0 END)) 
                            + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB', 'GRP') THEN 2 
                                WHEN pipe_type = 'AC' THEN 3 ELSE 1 END)) +  
                                ({$w_branch} * r_score 
                                +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww
                    FROM dssnrw.pipe_approve pm
                    WHERE pwa_code = '{$pwa_code}' AND fiscal_year = '{$proj_fiscal}' AND budget_type = '{$proj_budget}' 
										GROUP BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, 
                                        pipe_size, pipe_long, pipe_age, r_score, dma_nrw, remark, uid_insert 
                    ORDER BY zone, pwa_code, project_prov_name, project_prov_id, pipe_id, pipe_type, pipe_size  ";

    $result = pg_exec($connection, $q_pipe_id_weight_approve);

    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipe_id in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipe_id.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipeid_weight_approve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'pipe_id' => $row['pipe_id'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'pipe_long' => $row['pipe_long'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
                'sum_ww' => $row['sum_ww'],
            ));
        }
    }

    $data['group_pipeid_weight_approve'] = $group_pipeid_weight_approve;

    $data['message'] = 'Complete for get pipe_id and weight.';
}

echo json_encode($data);
pg_close($connection);
//}
