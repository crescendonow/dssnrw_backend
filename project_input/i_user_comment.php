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

//data input 
$zone = $arrReg[substr($_REQUEST['pwa_code'], 0, 4)];
$pwa_code = $_REQUEST['pwa_code'];
$user_id = $_REQUEST['user_id'];
$comment = $_REQUEST['comment'];


$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';

//----------------- processing zone --------------------------------------->>

//insert comment from user 
$istSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                    INSERT INTO dssnrw.user_comment (zone, pwa_code, uid_insert, comment, created_date) 
                    VALUES ('{$zone}', '{$pwa_code}', {$user_id}, '{$comment}', now()); COMMIT; ";

//echo $istSQL ;
//exit(); 

pg_exec($connection, $istSQL);

$data['status'] = 'true';
$data['message'] = 'Complete for save comment.';
echo json_encode($data);
exit();

echo json_encode($data);
