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

//input for pipe project save 
$proj_fiscal = $_POST['proj_fiscal'];
$proj_budget = $_POST['proj_budget'];
$proj_name = $_POST['proj_name'];
$proj_remark = $_POST['proj_remark'];
$project_prov_id = $_POST['proj_prov_id'];

$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';

//$project_prov_name =iconv( "utf-8", "windows-874",$_POST['project_prov_name']);
//$remark =iconv( "utf-8", "windows-874",$_POST['remark']);

if ($act == 'group_project') {
    $group_project_improve = array();
    $group_project_approve = array();

    $q_project_improve = "  SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT rm.zone, rm.pwa_code, rm.project_prov_id, rm.project_prov_name, pm.sum_long  , rm.proj_cost, 
                    rm.remark, rm.uid_insert 
                    FROM dssnrw.ref_pipe_improve rm 
					LEFT JOIN 
                    (SELECT pwa_code, project_prov_id , SUM(pipe_long) AS sum_long 
                        FROM dssnrw.pipe_improve 
                        WHERE pwa_code = '{$pwa_code}' 
                        GROUP BY pwa_code, project_prov_id) pm 
                        ON rm.pwa_code = pm.pwa_code AND rm.project_prov_id = pm.project_prov_id 
                    WHERE rm.budget_type = '{$proj_budget}' AND rm.fiscal_year = '{$proj_fiscal}' 
                        AND rm.zone = '{$zone}' AND rm.pwa_code = '{$pwa_code}' 
                        AND rm.project_prov_id NOT IN (SELECT project_prov_id FROM dssnrw.ref_pipe_approve) 
                        ORDER BY rm.project_prov_id ";

    $result = pg_query($connection, $q_project_improve);
    
    // echo json_encode($result);
    // echo var_dump($result);
    // exit(); 

    $numrows = pg_num_rows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'Project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get project name.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);
            array_push($group_project_improve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'sum_long' => $row['sum_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
            ));
        }
    }

    $data['group_project_improve'] = $group_project_improve;

    $q_project_approve = "  SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT rm.zone, rm.pwa_code, rm.project_prov_id, rm.project_prov_name, pm.sum_long  , rm.proj_cost, 
                    rm.remark, rm.uid_insert 
                    FROM dssnrw.ref_pipe_approve rm 
					LEFT JOIN 
                    (SELECT pwa_code, project_prov_id , SUM(pipe_long) AS sum_long 
                        FROM dssnrw.pipe_approve 
                        WHERE pwa_code = '{$pwa_code}' 
                        GROUP BY pwa_code, project_prov_id) pm 
                        ON rm.pwa_code = pm.pwa_code AND rm.project_prov_id = pm.project_prov_id 
                    WHERE rm.budget_type = '{$proj_budget}' AND rm.fiscal_year = '{$proj_fiscal}' 
                        AND rm.zone = '{$zone}' AND rm.pwa_code = '{$pwa_code}' 
                        AND rm.project_prov_id NOT IN (SELECT project_prov_id FROM dssnrw.ref_pipe_approve) 
                        ORDER BY rm.project_prov_id ";

    $result = pg_query($connection, $q_project_approve);
    
    // echo json_encode($result);
    // echo var_dump($result);
    // exit(); 

    $numrows = pg_num_rows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'Project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get project name.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);
            array_push($group_project_approve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'sum_long' => $row['sum_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
            ));
        }
    }

    $data['group_project_approve'] = $group_project_approve;
}

if ($act == 'group_pipetype') {
    $group_pipetype_improve = array();
    $group_pipetype_approve = array();

    $q_pipe_type_improve = "    SET CLIENT_ENCODING TO 'utf-8'; 
                        SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_type, pipe_size, SUM(pipe_long) AS sum_long, 
                        proj_cost, remark, uid_insert 
                        FROM dssnrw.pipe_improve 
                        WHERE pwa_code = '{$pwa_code}' AND budget_type = '{$proj_budget}' AND fiscal_year = '{$proj_fiscal}' 
                            AND project_prov_id NOT IN (SELECT project_prov_id FROM dssnrw.pipe_approve) 
                        GROUP BY zone, pwa_code, project_prov_name, pipe_type, pipe_size, project_prov_id, remark, uid_insert, proj_cost 
                        ORDER BY project_prov_id, pipe_type, pipe_size ";

    $result = pg_exec($connection, $q_pipe_type_improve);
    $numrows = pg_num_rows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipetype in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipetype.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipetype_improve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'sum_long' => $row['sum_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
            ));
        }
    }

    $data['group_pipetype_improve'] = $group_pipetype_improve;

    $q_pipe_type_approve = "    SET CLIENT_ENCODING TO 'utf-8'; 
                        SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_type, pipe_size, SUM(pipe_long) AS sum_long, 
                        proj_cost, remark, uid_insert 
                        FROM dssnrw.pipe_approve 
                        WHERE pwa_code = '{$pwa_code}' AND budget_type = '{$proj_budget}' AND fiscal_year = '{$proj_fiscal}' 
                        GROUP BY zone, pwa_code, project_prov_name, pipe_type, pipe_size, project_prov_id, remark, uid_insert, proj_cost 
                        ORDER BY project_prov_id, pipe_type, pipe_size ";

    $result = pg_exec($connection, $q_pipe_type_approve);
    $numrows = pg_num_rows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipetype in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipetype.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipetype_approve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'sum_long' => $row['sum_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
            ));
        }
    }

    $data['group_pipetype_approve'] = $group_pipetype_approve;
}

if ($act == 'group_pipeid') {
    $group_pipeid_improve = array();
    $group_pipeid_approve = array();

    $q_pipe_id_improve = "SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, 
                    proj_cost, remark, uid_insert 
                    FROM dssnrw.pipe_improve 
                    WHERE pwa_code = '{$pwa_code}' AND budget_type = '{$proj_budget}' AND fiscal_year = '{$proj_fiscal}' 
                        AND project_prov_id NOT IN (SELECT project_prov_id FROM dssnrw.pipe_approve) 
                    ORDER BY project_prov_id, pipe_id, pipe_type, pipe_size ";

    $result = pg_exec($connection, $q_pipe_id_improve);
    $numrows = pg_num_rows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipe_id in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipe_id.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipeid_improve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'pipe_id' => $row['pipe_id'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'pipe_long' => $row['pipe_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
            ));
        }
    }

    $data['group_pipeid_improve'] = $group_pipeid_improve;

    $q_pipe_id_approve = "SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long, 
                    proj_cost, remark, uid_insert 
                    FROM dssnrw.pipe_approve 
                    WHERE pwa_code = '{$pwa_code}' AND budget_type = '{$proj_budget}' AND fiscal_year = '{$proj_fiscal}' 
                    ORDER BY project_prov_id, pipe_id, pipe_type, pipe_size ";

    $result = pg_exec($connection, $q_pipe_id_approve);
    $numrows = pg_num_rows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipe_id in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipe_id.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipeid_approve, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'pipe_id' => $row['pipe_id'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'pipe_long' => $row['pipe_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
            ));
        }
    }

    $data['group_pipeid_approve'] = $group_pipeid_approve;
}

echo json_encode($data);
pg_close($connection);
//}
