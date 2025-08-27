<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Content-Type: application/json; charset=utf-8');

error_reporting(~E_NOTICE);
@ini_set('display_errors', '0'); //ไม่แสดงerror

include '../connect.php';
include '../basic_data.php';

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
$pipe_id_form = $_POST['pipe_id_form'];
$user_id = $_POST['user_id'];

//api collect data start 
$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';


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

        //delete from database 
        $del_sql = " DELETE FROM dssnrw.pipe_improve 
                 WHERE project_prov_id = '{$project_prov_id}' ;
                 DELETE FROM dssnrw.ref_pipe_improve 
                 WHERE project_prov_id = '{$project_prov_id}' ; ";

        $del_pipeid = pg_exec($connection, $del_sql);

        $data['status'] = 'true';
        $data['message'] = 'Complete for delete pipe projects.';
}

echo json_encode($data);

//}
