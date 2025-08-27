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

$project_id = '(';

//loop for insert list formaat sql 
foreach ($projectCodes as $code) {
   $elem = "'{$code}'," ;
   $project_id.=$elem ;
}
$project_id.= ')';
$project_id = str_replace(',)', ')', $project_id);

//clear project id before insert 
$del_data = " DELETE FROM dssnrw.ref_pipe_approve 
                        WHERE project_prov_id IN {$project_id} ;
                DELETE FROM dssnrw.pipe_approve 
                        WHERE project_prov_id IN {$project_id} ;  ";

$result = pg_exec($connection,  $del_data);

//insert data from pipe_improve to ref approve table 
$inst_ref = "   INSERT INTO dssnrw.ref_pipe_approve (zone, pwa_code, fiscal_year, budget_type, project_prov_id, project_prov_name,
                                created_date, remark, proj_cost, uid_insert) 
                        
                        (  SELECT
                                zone, pwa_code, fiscal_year, budget_type, project_prov_id, project_prov_name,
                                created_date, remark, proj_cost, uid_insert 
                                FROM dssnrw.ref_pipe_improve WHERE project_prov_id IN {$project_id}
                        ) " ; 

//echo $inst_ref;
//exit(); 

$result = pg_exec($connection,  $inst_ref);

if (!$result || $numrows == 0) {
    $data['status'] = 'false';
    $data['message'] = 'ref project cannot insert.';
} else {

    $data['status'] = 'true';
    $data['message'] = 'complete for insert ref_pipe_approve.';
}

//insert data from pipe_improve to approve table 
$inst_approve = "   INSERT INTO dssnrw.pipe_approve (zone, pwa_code, pipe_id, fiscal_year, budget_type, project_prov_name, 
                                        pipe_type, pipe_size, pipe_long, wkb_geometry, gen_geometry,
                                        created_date, remark, project_prov_id, proj_cost, uid_insert, 
                                        c_leak, pipe_age, project_no, contrac_date, asset_code, pipe_func,
                                        laying, product, depth, locate, dma_id, dma_no, dma_name, dma_nrw, 
                                        r_score, yearinstall, cost_repair, pipeold_long) 
                        
                        (  SELECT
                                zone, pwa_code, pipe_id, fiscal_year, budget_type, project_prov_name, 
                                        pipe_type, pipe_size, pipe_long, wkb_geometry, gen_geometry,
                                        created_date, remark, project_prov_id, proj_cost, uid_insert, 
                                        c_leak, pipe_age, project_no, contrac_date, asset_code, pipe_func,
                                        laying, product, depth, locate, dma_id, dma_no, dma_name, dma_nrw, 
                                        r_score, yearinstall, cost_repair, pipeold_long
                                FROM dssnrw.pipe_improve WHERE project_prov_id IN {$project_id}
                        ) " ; 

$result = pg_exec($connection,  $inst_approve);

$data['status'] = 'true';
$data['message'] = 'complete for insert pipe_approve.';


echo json_encode($data);
pg_close($connection);
//}
