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
$reg = $_POST['reg'];

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
    $group_project = array();
    $q_project = "  SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT rm.zone, rm.pwa_code, rm.project_prov_id, rm.project_prov_name, pm.sum_long  , rm.proj_cost, 
                    rm.remark, rm.uid_insert, rm.approve_status
                    FROM dssnrw.ref_pipe_improve rm 
					LEFT JOIN 
                    (SELECT pwa_code, project_prov_id , SUM(pipe_long) AS sum_long 
                        FROM dssnrw.pipe_improve 
                        WHERE zone = '{$reg}' 
                        GROUP BY pwa_code, project_prov_id) pm 
                        ON rm.pwa_code = pm.pwa_code AND rm.project_prov_id = pm.project_prov_id 
                    WHERE rm.budget_type = '{$proj_budget}' AND rm.fiscal_year = '{$proj_fiscal}' 
                        AND rm.zone = '{$reg}'    
                    ORDER BY rm.pwa_code, rm.project_prov_id ";

    $result = pg_exec($connection, $q_project);
    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'Project name not in database.';
    } else {
        $data['status'] = 'true';
        $data['message'] = 'complete for get project name.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);
            array_push($group_project, array(
                'zone' => $row['zone'],
                'pwa_code' => $row['pwa_code'],
                'proj_prov_id' => $row['project_prov_id'], 
                'proj_name' => $row['project_prov_name'],
                'sum_long' => $row['sum_long'],
                'proj_cost' => $row['proj_cost'],
                'proj_remark' => $row['remark'],
                'uid_insert' => $row['uid_insert'],
                'approve_status' => $row['approve_status'],
            ));
        }
    }

    $data['group_project'] = $group_project;
}

if ($act == 'group_pipetype') {
    $group_pipetype = array();

    $q_pipe_type = "    SET CLIENT_ENCODING TO 'utf-8'; 
                        SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_type, pipe_size, SUM(pipe_long) AS sum_long, 
                        proj_cost, remark, uid_insert 
                        FROM dssnrw.pipe_improve 
                        WHERE zone = '{$reg}' AND budget_type = '{$proj_budget}' AND fiscal_year = '{$proj_fiscal}' 
                        GROUP BY zone, pwa_code, project_prov_name, pipe_type, pipe_size, project_prov_id, remark, uid_insert, proj_cost 
                        ORDER BY pwa_code, project_prov_id, pipe_type, pipe_size ";

    $result = pg_exec($connection, $q_pipe_type);
    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipetype in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipetype.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipetype, array(
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
    $data['group_pipetype'] = $group_pipetype;
}

if ($act == 'group_pipeid') {
    $group_pipeid = array();
    $q_pipe_id = "SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT zone, pwa_code, project_prov_id, project_prov_name, pipe_id, pipe_type, pipe_size, pipe_long,
                    proj_cost, remark, uid_insert 
                    FROM dssnrw.pipe_improve 
                    WHERE zone = '{$reg}' AND budget_type = '{$proj_budget}' AND fiscal_year = '{$proj_fiscal}' 
                    ORDER BY project_prov_id, pipe_id, pipe_type, pipe_size ";

    $result = pg_exec($connection, $q_pipe_id);
    $numrows = pg_numrows($result);

    if (!$result || $numrows == 0) {
        $data['status'] = 'false';
        $data['message'] = 'pipe_id in project name not in database.';
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for get pipe_id.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            array_push($group_pipeid, array(
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

    $data['group_pipeid'] = $group_pipeid;
}

echo json_encode($data);
pg_close($connection);
//}
