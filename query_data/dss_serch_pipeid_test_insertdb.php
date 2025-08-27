<?php
@ini_set('display_errors', '1'); //ไม่แสดงerror
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
date_default_timezone_set("Asia/Bangkok");

//echo '11';
include '../connect.php';
require '../excel_lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

//recieve variable from post 
$xlsx_file =  $_FILES['upload_file'];
$pwa_code = $_POST['pwa_code'];
$user_id = $_REQUEST['user_id'];
$arrReg = array("5511" => "9", "5512" => "10", "5521" => "6", "5522" => "7", "5531" => "1", "5532" => "8", "5541" => "2", "5542" => "3", "5551" => "4", "5552" => "5");
$zone = $arrReg[substr($_POST['pwa_code'], 0, 4)];


//api collect data start 
$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';

//----------------------------------------------------------------- create temp table --------------------------------------------------//
$strTemp = " DROP INDEX IF EXISTS dssnrw_upload.pipeid_idx_{$pwa_code} CASCADE; COMMIT;
                DROP TABLE IF EXISTS dssnrw_upload.pipeid_{$pwa_code} CASCADE; COMMIT;

                CREATE TABLE dssnrw_upload.pipeid_{$pwa_code} (
                idx serial,
                pwa_code varchar(10) COLLATE pg_catalog.default DEFAULT NULL,
                pipe_id numeric(10) NOT NULL DEFAULT NULL,
                CONSTRAINT pipeid_pkey_{$pwa_code} PRIMARY KEY (idx)
                )
                ;

                ALTER TABLE dssnrw_upload.pipeid_{$pwa_code}
                OWNER TO gispwadb;

                CREATE INDEX pipeid_idx_{$pwa_code} ON dssnrw_upload.pipeid_{$pwa_code} (
                idx, pwa_code, pipe_id
                );  ";

$create_result = pg_exec($connection, $strTemp);
//echo json_encode("Complete create database.") ;
//exit() ;

// $inputFileName = '../upload_file/pipeid_banmor_oldver.xls'; // เปลี่ยนเป็นชื่อไฟล์ของคุณ

// excel process path 
//make directory for store file 
$targetDir = "../upload_file/xlsx_{$pwa_code}";
$fileName = basename($_FILES["file"]["name"]);
$targetFilePath = $targetDir . "/".$fileName;

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true); // make folder if not exits
}
else {
    // Delete all old files in the folder
    $oldFiles = glob($targetDir . '*'); // get all file names
    foreach ($oldFiles as $oldFile) {
        if (is_file($oldFile)) {
            unlink($oldFile); // delete file
        }
    }
    //echo "Folder exists: {$targetDir} - deleted old files.\n";
    $data['status'] = 'true';
    $data['upload_message'] = "Folder exists: {$targetDir} - deleted old files";
}


if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
    //echo "Upload successful: $fileName";
    $data['status'] = 'true';
    $data['upload_message'] = "Upload successful: {$fileName}";
} else {
    //echo "Upload failed.";
    $data['status'] = 'true';
    $data['upload_message'] = 'Upload failed.';
}

//exit(); 

// โหลดไฟล์ Excel
$spreadsheet = IOFactory::load($targetFilePath);

// get first worksheet
//$sheet = $spreadsheet->getActiveSheet();
$worksheet = $spreadsheet->getSheet(0);

// get data and append to array 
$data_excel = $worksheet->toArray();

// Ensure file is not empty
if (empty($data_excel)) {
    $data['status'] = 'false';
    $data['message'] = 'No data in excel file.';
}

// Extract headers (first row)
$headers = $data_excel[0];

// Find the index of PIPE_ID (case insensitive)
$pipeIdIndex = null;
foreach ($headers as $index => $header) {
    if (strtolower(trim($header)) === 'pipe_id') {
        $pipeIdIndex = $index;
        break;
    }
}

if ($pipeIdIndex === null) {
    $data['status'] = 'false';
    $data['message'] = 'PIPE_ID or pipe_id column not found.';
}

//echo json_encode($data);
//exit() ;

// Loop through remaining rows and extract PIPE_ID values
$pipe_id_form = '';
for ($i = 1; $i < count($data_excel); $i++) {
    $row = $data_excel[$i];

    if (!isset($row[$pipeIdIndex])) {
        continue; // skip rows missing the column
    }

    $pipeId = $row[$pipeIdIndex];
    if ($i < count($data_excel)-1) {
        $strid = $pipeId."," ;
    }
    else {
        $strid = $pipeId ;
    }
    $pipe_id_form.= $strid;

    if (is_numeric($pipeId)) {
        $istSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                INSERT INTO dssnrw_upload.pipeid_{$pwa_code}  (pwa_code, pipe_id) 
                VALUES ('{$pwa_code}', {$pipeId}); COMMIT; ";

        //echo "$istSQL\n";
        //exit();
        pg_exec($connection, $istSQL);
    }
    //echo "Row $i - PIPE_ID: $pipeId\n" ;
}
echo json_encode($pipe_id_form);
exit() ;

$data['status'] = 'true';
$data['message'] = 'insert pipe id to pipeid_{$pwa_code}';

//echo json_encode($data);
//exit(); 

//conclusion in branch with freq and weight
$sqlPipesumfreq = " SELECT COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                    SUM(c_leak) c_leak,sum(cost_repair) as cost_repair, 
                    ROUND(SUM(c_leak)/sum(pipe_long),2) avg_lk
                    FROM dssnrw.pipe_summary
                    WHERE pwa_code = '{$pwa_code}' AND pipe_id IN (
                        SELECT pipe_id FROM dssnrw_upload.pipeid_{$pwa_code}) ";

$Psumfreq_result = pg_exec($connection, $sqlPipesumfreq);

$numrows = pg_numrows($Psumfreq_result);

if (!$Psumfreq_result) {
    $data['status'] = 'false';
    $data['message'] = json_encode(pg_last_error($db));
    $data['pid_sum_freq'] = array();
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

//conclusion with weight pipe type size freq 
$sqlPipetypesizefreq =  " SET CLIENT_ENCODING TO 'utf-8'; 
                            SELECT CASE WHEN sum(pp.l_pipe)!=0 THEN ROUND(SUM(pp.c_leak)/sum(pp.l_pipe),
                            2)ELSE 0 END avg_lk, pp.pipe_type, pp.pipe_size, MAX(pp.pipe_age) age_max, MIN(pp.pipe_age) age_min,
                            SUM(pp.c_pipe) c_pipe, SUM(pp.l_pipe) l_pipe, SUM(pp.c_leak) c_leak, 
                            SUM(pp.cost_repair) cost_repair, MAX(pp.elev) elev,
                            MAX(pp.ptype) ptype, MAX(pp.pressure) pressure
                            FROM 
                            (SELECT CASE WHEN sum(pipe_long) != 0 THEN ROUND(SUM(c_leak)/sum(pipe_long),2) ELSE 0 END avg_lk,
                            pipe_type, pipe_size, pipe_age,
                            COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                            SUM(c_leak) as c_leak,  0 ptype, 0 pressure, 0 elev,   
                            cost_repair, dma_nrw
                            FROM dssnrw.pipe_summary ps 
                            LEFT JOIN 
                            (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                            ON ps.pwa_code = rg.pwa_code 
                            WHERE ps.pwa_code = '{$pwa_code}' AND ps.pipe_id IN (SELECT pipe_id FROM dssnrw_upload.pipeid_{$pwa_code}) 
                            GROUP BY pipe_type, pipe_size,pipe_age, cost_repair, rg.r_score, dma_nrw
                            ORDER BY pipe_type, pipe_size, pipe_age, cost_repair) pp 
                            GROUP BY pp.pipe_type, pp.pipe_size
                            ORDER BY pipe_type, pipe_size ";

//echo $sqlPipetypesizefreq;
//exit();


$Pipetypesizefreq_result = pg_exec($connection, $sqlPipetypesizefreq);

$numrows = pg_numrows($Pipetypesizefreq_result);

if (!$Pipetypesizefreq_result) {
    $data['status'] = 'false';
    $data['message'] = json_encode(pg_last_error($db));
    $data['pid_typesize_freq'] = array();
} else {

    $data['status'] = 'true';
    $data['message'] = 'complete for pipe type size with weight.';

    $pw_typesize = array();

    for ($ri = 0; $ri < $numrows; $ri++) {
        $row = pg_fetch_array($Pipetypesizefreq_result, $ri);

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

$str_check_id = " SET CLIENT_ENCODING TO 'utf-8';    
                    SELECT pipe_id FROM oracle.r{$zone}_pipe 
                    WHERE pipe_id IN (SELECT pipe_id FROM dssnrw_upload.pipeid_{$pwa_code}) AND pwa_code = '{$pwa_code}' 
                    ORDER BY pipe_id ";

$result_check = pg_exec($connection, $str_check_id);

if (!$result_check) {
    $data['status'] = 'false';
    $data['message'] = json_encode(pg_last_error($db));
    $data['have_id'] = array();
    $data['no_id'] = array();
} else {
    $data['status'] = 'true';
    $data['message'] = 'complete for pipe_detail_map.';

    //crate pipe_id from query for check 
    $pid_array = array();
    while ($row = pg_fetch_array($result_check)) {
        // Push each pipe_id value into the array
        array_push($pid_array, $row['pipe_id']);
    }
    //check pipe_id not in oracle pipe data 
    $result = array_filter($pipe_chk, function ($value) use ($pid_array) {
        return !in_array($value, $pid_array);
    });
    $empty_pid = array_values($result);

    //add have_pipe and no pipe in data array 
    $data['have_id'] = $pid_array;
    $data['no_id'] = $empty_pid;
}

$strSQL = " SET CLIENT_ENCODING TO 'utf-8';
                SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                FROM (SELECT 'Feature' As type 
                , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                , row_to_json((SELECT l FROM (SELECT pipe_id, project_no, pipe_type, pipe_size, 
                        yearinstall, pipe_long, contrac_date, asset_code, pipe_func, laying, 
                        product, depth, locate, c_leak, cost_repair) As l 
                )) As properties 
                FROM (select pp.pipe_id, pp.project_no, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.contrac_date, 
                        CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                        pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.wkb_geometry,
                                            ps.c_leak, ps.cost_repair 
                        FROM oracle.r{$zone}_pipe pp 
                                            LEFT JOIN 
                                            (SELECT c_leak, cost_repair, pipe_id, pwa_code FROM dssnrw.pipe_summary 
                                                WHERE pwa_code = '{$pwa_code}' ) ps
                                            ON pp.pwa_code = ps.pwa_code AND pp.pipe_id = ps.pipe_id 
                        WHERE pp.pwa_code = '{$pwa_code}' 
                        AND pp.pipe_id IN (SELECT pipe_id FROM dssnrw_upload.pipeid_{$pwa_code})
                        AND pp.wkb_geometry IS NOT NULL
                        ORDER BY pp.pipe_id ) As lg) As f )  As fc ";

$resultmap = pg_exec($connection, $strSQL);

if (!$resultmap) {
    $data['geo_map'] = array();
    //echo pg_last_error($db);
} else {
    $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
}
header('Content-Type: application/json; charset=utf-8');
$data['geo_map'] = $arr[0];
//echo $arr[0];  
echo json_encode($data);
pg_close($connection);
//exit();

echo json_encode($data);
exit(); 
