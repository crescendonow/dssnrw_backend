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

$project_prov_id_form = $_POST['proj_prov_id_form'];
//input for pipe project save 
$proj_fiscal = $_POST['proj_fiscal'];
$proj_budget = $_POST['proj_budget'];
$proj_name = $_POST['proj_name'];
$proj_remark = $_POST['proj_remark'];
$projectCodes = $_POST['projectCodes'];

$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';

//$project_prov_name =iconv( "utf-8", "windows-874",$_POST['project_prov_name']);
//$remark =iconv( "utf-8", "windows-874",$_POST['remark']);

$projectCodes = json_decode($_POST['projectCodes'], true);

//loop for update status code 
foreach ($projectCodes as $code) {
        $sql_update = " UPDATE dssnrw.ref_pipe_improve 
                        SET approve_status = {$code['statusCode']} 
                        WHERE project_prov_id = '{$code['projectCode']}' " ;

        $result = pg_exec($connection,  $sql_update);
     }

$data['status'] = 'true';
$data['message'] = 'complete for update status ref_pipe_approve.';

echo json_encode($data);
pg_close($connection);
//}
