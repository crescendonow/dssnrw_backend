<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Content-Type: application/json; charset=utf-8');
error_reporting(~E_NOTICE);
@ini_set('display_errors', '1'); //ไม่แสดงerror
include 'connect.php';

require('excel_lib/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;
//if ($_SERVER['REQUEST_METHOD'] === "REQUEST") { check session 

$arrReg = array("5511" => "9", "5512" => "10", "5521" => "6", "5522" => "7", "5531" => "1", "5532" => "8", "5541" => "2", "5542" => "3", "5551" => "4", "5552" => "5");
$zone = $arrReg[substr($_REQUEST['pwa_code'], 0, 4)];
$act = $_REQUEST['act'];
$is_excel = $_REQUEST['is_excel'];
$pwa_code = $_REQUEST['pwa_code'];
$pipe_id_form = $_REQUEST['pipe_id_form'];
$q_pipe_id_form = $pipe_id_form;
$user_id = $_REQUEST['user_id'];

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
$proj_cost = $_POST['proj_cost'];

$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';

//-----------------function zone--------------------------------------->

/* //create temp table 
$strTemp = " DROP INDEX IF EXISTS dssnrw.p_temp_gengeometry_idx_{$pwa_code} CASCADE; COMMIT;
                        DROP INDEX IF EXISTS dssnrw.p_temp_geometry_idx_{$pwa_code} CASCADE; COMMIT;
                        DROP INDEX IF EXISTS dssnrw.p_temp_geom_idx_{$pwa_code} CASCADE; COMMIT;
                        DROP TABLE IF EXISTS dssnrw.p_temp_{$pwa_code} CASCADE; COMMIT;
                        DROP SEQUENCE IF EXISTS p_temp_{$pwa_code}_idx_seq CASCADE; COMMIT;

                        DO $$
                        BEGIN
                            IF NOT EXISTS (
                                SELECT 1 FROM pg_class c
                                JOIN pg_namespace n ON n.oid = c.relnamespace
                                WHERE c.relname = 'p_temp_{$pwa_code}_idx_seq' AND n.nspname = 'dssnrw'
                            ) THEN
                                CREATE SEQUENCE dssnrw.p_temp_{$pwa_code}_idx_seq;
                            END IF;
                        END
                        $$;

                        CREATE TABLE dssnrw.p_temp_{$pwa_code} (
                        pipe_id numeric(10) NOT NULL DEFAULT NULL,
                        pwa_code varchar(10) COLLATE pg_catalog.default DEFAULT NULL,
                        wkb_geometry public.geometry DEFAULT NULL,
                        idx integer DEFAULT nextval('p_temp_{$pwa_code}_idx_seq'),
                        gen_geometry public.geometry DEFAULT NULL,
                        CONSTRAINT p_temp_pkey_{$pwa_code} PRIMARY KEY (idx)
                        )
                        ;

                        ALTER TABLE dssnrw.p_temp_{$pwa_code}
                        OWNER TO gispwadb;

                        CREATE INDEX p_temp_gengeometry_idx_{$pwa_code} ON dssnrw.p_temp_{$pwa_code} USING gist (
                        gen_geometry public.gist_geometry_ops_2d
                        );

                        CREATE INDEX p_temp_geom_idx_{$pwa_code} ON dssnrw.p_temp_{$pwa_code} USING gist (
                        wkb_geometry public.gist_geometry_ops_2d
                        );  "; */

$str_pipeall = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT * FROM dssnrw.data_summary 
                WHERE zone = '{$zone}' AND pwa_code = '{$pwa_code}' ";
$result = pg_exec($connection, $str_pipeall);

$numrows = pg_numrows($result);

if (!$result) {
    $data['status'] = 'false';
    $data['message'] = json_encode(pg_last_error($db));
    $data['leak_data'] = array();
} else {

    $data['status'] = 'true';
    $data['message'] = 'complete for pipe all data.';

    for ($ri = 0; $ri < $numrows; $ri++) {
        $row = pg_fetch_array($result, $ri);
        $pipeall_data = array(
            'update_data' => $row['update_data'],
            'c_pipe' => $row['c_pipe'],
            'l_pipe' => $row['l_pipe'],
            'c_mt' => $row['c_mt'],
            'c_valve' => $row['c_valve'],
            'c_fire' => $row['c_fire'],
            'c_leak' => $row['c_leak'],
        );
    }

    $data['pipeall_data'] = $pipeall_data;
}

//basic data from branch 
$age_data = array(
    'max' => '-',
    'min' => '-',
    'sd' => '-',
    'mean' => '-',
    'value_1' => 10,
    'value_2' => 19
);
$data['message'] = 'age data complete.';
$data['age_data'] = $age_data;

//push leakdata 
$strLeak = "SELECT MAX(c_leak) max, 
MIN(c_leak) min, 
ROUND(STDDEV(c_leak), 2) AS sd, 
ROUND(AVG(c_leak), 2) AS mean,  
        CASE WHEN CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) <= 0 THEN 1 
            ELSE CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) END value_1, 
        CAST(AVG(c_leak) + (1*STDDEV(c_leak)) AS INTEGER) value_2 FROM 
        dssnrw.pipe_summary 
        WHERE pwa_code = '{$pwa_code}' ";

$leak_result = pg_exec($connection, $strLeak);

$numrows = pg_numrows($leak_result);

if (!$leak_result) {
    $data['status'] = 'false';
    $data['message'] = json_encode(pg_last_error($db));
    $data['leak_data'] = array();
} else {

    $data['status'] = 'true';
    $data['message'] = 'complete for leak_data.';

    for ($ri = 0; $ri < $numrows; $ri++) {
        $row = pg_fetch_array($leak_result, $ri);

        $leak_data = array(
            'max' => $row['max'],
            'min' => $row['min'],
            'sd' => $row['sd'],
            'mean' => $row['mean'],
            'value_1' => $row['value_1'],
            'value_2' => $row['value_2'],
        );
    }
    $data['leak_data'] = $leak_data;
}

//get data from repaircost 
$strCost = "SELECT MAX(cost_repair) max, 
MIN(cost_repair) min, 
ROUND(STDDEV(cost_repair), 2) AS sd, 
ROUND(AVG(cost_repair), 2) mean,  
CASE WHEN CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) <= 0 THEN 1  
    ELSE CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) END value_1, 
CAST(AVG(cost_repair) + (1*STDDEV(cost_repair)) AS INTEGER) value_2 FROM 
dssnrw.pipe_summary 
        WHERE pwa_code = '{$pwa_code}' ";

$cost_result = pg_exec($connection, $strCost);

$numrows = pg_numrows($cost_result);

if (!$cost_result) {
    $data['status'] = 'false';
    $data['message'] = json_encode(pg_last_error($db));
    $data['cost_data'] = array();
} else {

    $data['status'] = 'true';
    $data['message'] = 'complete for cost_data.';

    for ($ri = 0; $ri < $numrows; $ri++) {
        $row = pg_fetch_array($cost_result, $ri);

        $cost_data = array(
            'max' => $row['max'],
            'min' => $row['min'],
            'sd' => $row['sd'],
            'mean' => $row['mean'],
            'value_1' => $row['value_1'],
            'value_2' => $row['value_2'],
        );
    }
    $data['cost_data'] = $cost_data;
}

if ($act == 'upload_excel') {

    $data = array();
    //check excel insert to pipe_id_form 
    $create_result = create_temp_table($pwa_code, $connection);
    $data['status'] = $create_result;
    $data['message'] = "Complete create p_temp_{$pwa_code}";

    //make directory for store file 
    $targetDir = "upload_file/xlsx_{$pwa_code}";
    $fileName = basename($_FILES["file"]["name"]);
    $targetFilePath = $targetDir . "/" . $fileName;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true); // make folder if not exits 
    } else {
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
    $pipe_id_array = [];

    for ($i = 1; $i < count($data_excel); $i++) {
        $row = $data_excel[$i];

        // skip missing row or empty /null
        if (!isset($row[$pipeIdIndex]) || trim($row[$pipeIdIndex]) === '' || $row[$pipeIdIndex] === null) {
            continue;
        }

        $pipeId = trim($row[$pipeIdIndex]);

        // filter numeric 
        if (is_numeric($pipeId)) {
            $pipe_id_array[] = $pipeId;
        }
    }

    // combine string ,
    $pipe_id_form = implode(',', $pipe_id_array);

    $y = $pipe_id_form;
    $pipe_chk = explode(",", $y);

    $allNumeric = array_reduce($pipe_chk, function ($carry, $item) {
        return $carry && is_numeric(trim($item));
    }, true);

    if (!$allNumeric) {
        $data['status'] = 'false';
        $data['message'] = "Please input correct format like 14, 25, 300 ";
        echo json_encode($data);
        exit();
    } else {
        $data['status'] = 'true';
        $data['message'] = json_encode("correct format.");

        //conclusion in branch with freq and weight
        $sqlPipesumfreq = " SELECT COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                        SUM(c_leak) c_leak,sum(cost_repair) as cost_repair, 
                        ROUND(SUM(c_leak)/sum(pipe_long),2) avg_lk
                        FROM dssnrw.pipe_summary
                        WHERE pwa_code = '{$pwa_code}' AND pipe_id IN ({$pipe_id_form})";
        //echo $sqlPipesumfreq;
        //exit();

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
                            WHERE ps.pwa_code = '{$pwa_code}' AND ps.pipe_id IN ({$pipe_id_form}) 
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
                    WHERE pipe_id IN ({$pipe_id_form}) AND pwa_code = '{$pwa_code}' 
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
                        product, depth, locate, c_leak, cost_repair, dma_name, dma_no_list, dma_name_list) As l 
                )) As properties 
                FROM (select pp.pipe_id, pp.project_no, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.contrac_date, 
                        CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                        pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, ps.dma_no_list, ps.dma_name_list,
                        pp.wkb_geometry, ps.c_leak, ps.cost_repair,ps.dma_name 
                        FROM oracle.r{$zone}_pipe pp 
                                            LEFT JOIN 
                                            (SELECT c_leak, cost_repair, pipe_id, pwa_code, dma_name, dma_no_list, dma_name_list FROM dssnrw.pipe_summary 
                                                WHERE pwa_code = '{$pwa_code}' ) ps
                                            ON pp.pwa_code = ps.pwa_code AND pp.pipe_id = ps.pipe_id 
                        WHERE pp.pwa_code = '{$pwa_code}' 
                        AND pp.pipe_id IN ({$pipe_id_form})
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
        $data['pipe_id_form'] = $pipe_id_form;
        //echo $arr[0];  
        echo json_encode($data);
        pg_close($connection);
        exit();
    }
}

if ($act == 'pipe_detail_map') {

    $data = array();

    $create_result = create_temp_table($pwa_code, $connection);
    $data['status'] = $create_result;
    $data['message'] = "Complete create p_temp_{$pwa_code}";

    if (is_string($pipe_detail_map)) {
        $y = $pipe_id_form;
    } else {
        $y = strval($pipe_id_form);
    }
    
    $pipe_chk = explode(",", $y);
    //print_r($pipe_chk);
    //exit();

    $allNumeric = array_reduce($pipe_chk, function ($carry, $item) {
        return $carry && is_numeric(trim($item));
    }, true);

    if (!$allNumeric) {
        $data['status'] = 'false';
        $data['message'] = "Please input correct format like 14, 25, 300 ";
    } else {
        $data['status'] = 'true';
        $data['message'] = json_encode("correct format.");

        //conclusion in branch with freq and weight
        $sqlPipesumfreq = " SELECT COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                        SUM(c_leak) c_leak,sum(cost_repair) as cost_repair, 
                        ROUND(SUM(c_leak)/sum(pipe_long),2) avg_lk
                        FROM dssnrw.pipe_summary
                        WHERE pwa_code = '{$pwa_code}' AND pipe_id IN ({$pipe_id_form})";

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
                            WHERE ps.pwa_code = '{$pwa_code}' AND ps.pipe_id IN ({$pipe_id_form}) 
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

        //conclusion pipe freq dma 
        $sqlPipetypesizefreq_dma =  "SET CLIENT_ENCODING TO 'utf-8';
                                    SELECT 
                                        COALESCE(
                                        ROUND(
                                        (SUM(pp.c_leak)::numeric) 
                                        / NULLIF(SUM(pp.l_pipe)::numeric, 0)
                                        , 2)
                                    , 0)                               AS avg_lk, 
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
                                            pipe_type,
                                            pipe_size,
                                            pipe_age,
                                            COUNT(pipe_id)    AS c_pipe,
                                            SUM(ST_Length(wkb_geometry::geography)) AS l_pipe, 
                                            SUM(c_leak)       AS c_leak,
                                            0                 AS ptype,
                                            0                 AS pressure,
                                            0                 AS elev,
                                            cost_repair,
                                            dma_nrw,
                                            dma_no,
                                            dma_name
                                        FROM dssnrw.pipe_summary ps
                                        LEFT JOIN (
                                            SELECT pwa_code, r_score 
                                            FROM dssnrw.pwa_risk_group
                                        ) rg
                                        ON ps.pwa_code = rg.pwa_code
                                        WHERE ps.pwa_code = '{$pwa_code}'
                                        AND ps.pipe_id IN ({$pipe_id_form})
                                        GROUP BY 
                                            pipe_type, pipe_size, pipe_age, cost_repair, rg.r_score, dma_nrw, dma_no, dma_name
                                    ) pp
                                    GROUP BY 
                                        pp.dma_no, pp.dma_name, pp.pipe_type, pp.pipe_size
                                    ORDER BY 
                                        pp.dma_no, pp.dma_name, pp.pipe_type, pp.pipe_size; " ;

        
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

        $str_check_id = " SET CLIENT_ENCODING TO 'utf-8';    
                    SELECT pipe_id FROM oracle.r{$zone}_pipe 
                    WHERE pipe_id IN ({$pipe_id_form}) AND pwa_code = '{$pwa_code}' 
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
                        product, depth, locate, c_leak, cost_repair, dma_no, dma_name, dma_no_list, dma_name_list) As l 
                )) As properties 
                FROM (select pp.pipe_id, pp.project_no, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.contrac_date, 
                        CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                        pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.wkb_geometry,
                                            ps.c_leak, ps.cost_repair, ps.dma_no, ps.dma_name, ps.dma_no_list, ps.dma_name_list  
                        FROM oracle.r{$zone}_pipe pp 
                                            LEFT JOIN 
                                            (SELECT c_leak, cost_repair, pipe_id, pwa_code, dma_no, dma_name,  dma_no_list, dma_name_list 
                                            FROM dssnrw.pipe_summary 
                                                WHERE pwa_code = '{$pwa_code}' ) ps
                                            ON pp.pwa_code = ps.pwa_code AND pp.pipe_id = ps.pipe_id 
                        WHERE pp.pwa_code = '{$pwa_code}' 
                        AND pp.pipe_id IN ({$pipe_id_form})
                        AND pp.wkb_geometry IS NOT NULL
                        ORDER BY pp.pipe_id ) As lg) As f )  As fc ";
        
        //echo $strSQL;
        //exit();

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
        exit();
    }
}

if ($act == 'f_weight') {
    $data = array();
    //conclusion in branch with freq and weight
    $sqlPipesumfreq = " SELECT ds.*,  ROUND(c_leak::numeric / NULLIF(l_pipe, 0), 4) AS avg_lk 
						FROM dssnrw.data_summary ds
                        WHERE pwa_code = '{$pwa_code}' ";

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
                'cost_repair' => $row['c_repair'],
                'avg_lk' => $row['avg_lk'],
            );
        }
    }

    //conclusion with weight pipe type size freq 
    $sqlPipetypesizefreq =  " SET CLIENT_ENCODING TO 'utf-8'; 
                                SELECT CASE WHEN sum(pp.l_pipe)!=0 THEN ROUND(SUM(pp.c_leak)/sum(pp.l_pipe),
                                2)ELSE 0 END avg_lk, pp.pipe_type, pp.pipe_size, MAX(pp.pipe_age) age_max, MIN(pp.pipe_age) age_min,
                                pp.w_age w_age,
                                MAX(pp.pipe_age_ww) pipe_age_ww, SUM(pp.c_pipe) c_pipe, SUM(pp.l_pipe) l_pipe, SUM(pp.c_leak) c_leak, MAX(pp.w_leak) w_leak, MAX(pp.c_leak_ww) c_leak_ww, 
                                SUM(pp.cost_repair) cost_repair, MAX(pp.w_cost) w_cost, MAX(pp.cost_repair_ww) cost_repair_ww, MAX(pp.elev) elev, MAX(pp.w_elev) w_elev, MAX(pp.elev_ww) elev_ww,
                                MAX(pp.ptype) ptype, MAX(pp.w_ptype) w_ptype, MAX(pp.ptype_ww) ptype_ww, MAX(pp.pressure) pressure, MAX(pp.w_pressure) w_pressure, MAX(pp.pressure_ww) pressure_ww,
                                MAX(pp.w_branch) w_branch, MAX(pp.branch_ww) branch_ww, MAX(pp.w_dma) w_dma, MAX(pp.dma_ww) dma_ww,   
                                SUM(CASE WHEN pp.sum_ww >= 0 AND pp.sum_ww <= 1 THEN c_pipe ELSE 0 END) lr,
                                SUM(CASE WHEN pp.sum_ww >= 0 AND pp.sum_ww <= 1 THEN l_pipe ELSE 0 END) long_lr,
                                SUM(CASE WHEN pp.sum_ww > 1 AND pp.sum_ww <= 2 THEN c_pipe ELSE 0 END) mr,
                                SUM(CASE WHEN pp.sum_ww > 1 AND pp.sum_ww <= 2 THEN l_pipe ELSE 0 END) long_mr,
                                SUM(CASE WHEN pp.sum_ww > 2 AND pp.sum_ww <= 3 THEN c_pipe ELSE 0 END) hr,
                                SUM(CASE WHEN pp.sum_ww > 2 AND pp.sum_ww <= 3 THEN l_pipe ELSE 0 END) long_hr  
                                FROM 
                            (SELECT CASE WHEN sum(pipe_long) != 0 THEN ROUND(SUM(c_leak)/sum(pipe_long),2) ELSE 0 END avg_lk,
                            pipe_type, pipe_size, pipe_age, 
                            {$w_age} w_age, {$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                    WHEN  pipe_age >= {$age_data['value_1']}
                                                    AND  pipe_age <= {$age_data['value_2']} THEN 2 
                                                    WHEN   pipe_age >  {$age_data['value_2']} THEN 3 
                                                    ELSE 0 END) pipe_age_ww, 
                            COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                            SUM(c_leak) as c_leak, {$w_leak} AS w_leak, 
                            {$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                                WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                        AND SUM(c_leak) <= {$leak_data['value_2']} THEN 2
                                WHEN  SUM(c_leak) > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                            cost_repair, 
                            {$w_cost} w_cost, 
                            {$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                WHEN  SUM(cost_repair) >= {$cost_data['value_1']}  
                                AND  SUM(cost_repair) <= {$cost_data['value_2']}  THEN 2
                                WHEN  SUM(cost_repair) > {$cost_data['value_2']}  THEN 3 
                                ELSE 0 END) cost_repair_ww, 
                            0 elev, 0 w_elev, 0 elev_ww, 
                            0 ptype, 
                            {$w_ptype} w_ptype, 
                            {$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                                WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END)  ptype_ww, 
                            0 pressure, 0 w_pressure, 0 pressure_ww, {$w_branch} w_branch, {$w_branch} * rg.r_score branch_ww, 
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
                            ELSE 0 END)) 
                            + ({$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                    WHEN pipe_age >= {$age_data['value_1']}
                                                    AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                    WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                    ELSE 0 END)) 
                            + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                                WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END)) +  
                                ({$w_branch} * rg.r_score 
                                +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww
                            FROM dssnrw.pipe_summary ps 
                            LEFT JOIN 
                            (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                            ON ps.pwa_code = rg.pwa_code 
                            WHERE ps.pwa_code = '{$pwa_code}' 
                            GROUP BY pipe_type, pipe_size,pipe_age, cost_repair, rg.r_score, dma_nrw
                            ORDER BY pipe_type, pipe_size, pipe_age, cost_repair) pp 
                            GROUP BY pp.pipe_type, pp.pipe_size, pp.w_age
                            ORDER BY pipe_type, pipe_size ";

    //echo $sqlPipetypesizefreq;
    //exit();


    $Pipetypesizefreq_result = pg_exec($connection, $sqlPipetypesizefreq);

    $numrows = pg_numrows($Pipetypesizefreq_result);

    if (!$Pipetypesizefreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['pw_typesize_freq'] = array();
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

        $data['pw_typesize_freq'] = $pw_typesize;
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
                        ELSE 0 END)) 
                        + ({$w_age} * 
                        (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                            WHEN pipe_age >= {$age_data['value_1']}
                            AND pipe_age <= {$age_data['value_2']} THEN 2 
                            WHEN  pipe_age > {$age_data['value_2']} THEN 3 
                            ELSE 0 END) ) 
                        + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                            WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                            WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END) )
                        + ({$w_branch} * rg.r_score) 
                        +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END))
                        ) sum_ww 
                            FROM dssnrw.pipe_summary ps 
                            LEFT JOIN 
                            (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group ) rg 
                            ON ps.pwa_code = rg.pwa_code 
                            WHERE ps.pwa_code = '{$pwa_code}' ) sw ";

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

    //typesize_dma
    $sqlPipetypesizefreq_dma =  " SET CLIENT_ENCODING TO 'utf-8';

                                    WITH pp AS (
                                    SELECT
                                        COALESCE(
                                        ROUND( (SUM(c_leak)::numeric) / NULLIF(SUM(ST_Length(ps.wkb_geometry::geography))::numeric, 0), 2 ),
                                        0
                                        ) AS avg_lk,

                                        pipe_type,
                                        pipe_size,
                                        pipe_age,

                                        dma_no,
                                        dma_name,

                                        {$w_age} AS w_age,

                                        {$w_age} * (
                                        CASE
                                            WHEN pipe_age < {$age_data['value_1']} THEN 1
                                            WHEN pipe_age BETWEEN {$age_data['value_1']} AND {$age_data['value_2']} THEN 2
                                            WHEN pipe_age > {$age_data['value_2']} THEN 3
                                            ELSE 0
                                        END
                                        ) AS pipe_age_ww,

                                        COUNT(pipe_id) AS c_pipe,
                                        SUM(ST_Length(ps.wkb_geometry::geography)) AS l_pipe,
                                        SUM(c_leak)  AS c_leak,

                                        {$w_leak} AS w_leak,
                                        {$w_leak} * (
                                        CASE
                                            WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1
                                            WHEN SUM(c_leak) BETWEEN {$leak_data['value_1']} AND {$leak_data['value_2']} THEN 2
                                            WHEN SUM(c_leak) > {$leak_data['value_2']} THEN 3
                                        END
                                        ) AS c_leak_ww,

                                        SUM(cost_repair) AS cost_repair,

                                        {$w_cost} AS w_cost,
                                        {$w_cost} * (
                                        CASE
                                            WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                            WHEN SUM(cost_repair) BETWEEN {$cost_data['value_1']} AND {$cost_data['value_2']} THEN 2
                                            WHEN SUM(cost_repair) > {$cost_data['value_2']} THEN 3
                                            ELSE 0
                                        END
                                        ) AS cost_repair_ww,

                                        0 AS elev,
                                        0 AS w_elev,
                                        0 AS elev_ww,

                                        0           AS ptype,
                                        {$w_ptype}  AS w_ptype,
                                        {$w_ptype} * (
                                        CASE
                                            WHEN pipe_type IN ('ST','ST_UN','ST_ON','ST_CV','GS','DI','CI') THEN 1
                                            WHEN pipe_type IN ('PVC','PVC_O','HDPE','PB')                    THEN 2
                                            WHEN pipe_type IN ('AC','GRP')                                   THEN 3
                                            ELSE 1
                                        END
                                        ) AS ptype_ww,

                                        0 AS pressure,
                                        0 AS w_pressure,
                                        0 AS pressure_ww,

                                        {$w_branch} AS w_branch,
                                        {$w_branch} * rg.r_score AS branch_ww,

                                        {$w_dma} AS w_dma,
                                        {$w_dma} * (
                                        CASE
                                            WHEN dma_nrw <= 30 THEN 1
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3
                                            ELSE 0
                                        END
                                        ) AS dma_ww,

                                        (
                                        ({$w_leak} * (
                                            CASE
                                            WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1
                                            WHEN SUM(c_leak) BETWEEN {$leak_data['value_1']} AND {$leak_data['value_2']} THEN 2
                                            WHEN SUM(c_leak) > {$leak_data['value_2']} THEN 3
                                            END
                                        ))
                                        +
                                        ({$w_cost} * (
                                            CASE
                                            WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                            WHEN SUM(cost_repair) BETWEEN {$cost_data['value_1']} AND {$cost_data['value_2']} THEN 2
                                            WHEN SUM(cost_repair) > {$cost_data['value_2']} THEN 3
                                            ELSE 0
                                            END
                                        ))
                                        +
                                        ({$w_age} * (
                                            CASE
                                            WHEN pipe_age < {$age_data['value_1']} THEN 1
                                            WHEN pipe_age BETWEEN {$age_data['value_1']} AND {$age_data['value_2']} THEN 2
                                            WHEN pipe_age > {$age_data['value_2']} THEN 3
                                            ELSE 0
                                            END
                                        ))
                                        +
                                        ({$w_ptype} * (
                                            CASE
                                            WHEN pipe_type IN ('ST','ST_UN','ST_ON','ST_CV','GS','DI','CI') THEN 1
                                            WHEN pipe_type IN ('PVC','PVC_O','HDPE','PB')                    THEN 2
                                            WHEN pipe_type IN ('GRP','AC')                                   THEN 3
                                            ELSE 1
                                            END
                                        ))
                                        +
                                        ({$w_branch} * rg.r_score + (0 * (
                                            CASE
                                            WHEN dma_nrw <= 30 THEN 1
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3
                                            ELSE 0
                                            END
                                        )))
                                        ) AS sum_ww

                                    FROM dssnrw.pipe_summary ps
                                    LEFT JOIN dssnrw.pwa_risk_group rg
                                        ON ps.pwa_code = rg.pwa_code
                                    WHERE ps.pwa_code = '{$pwa_code}'
                                    GROUP BY
                                        pipe_type, pipe_size, pipe_age,
                                        rg.r_score, dma_nrw, dma_no, dma_name
                                    ),
                                    agg AS (
                                    SELECT
                                        dma_no,
                                        dma_name,
                                        pipe_type,
                                        pipe_size,

                                        COALESCE(
                                        ROUND( (SUM(c_leak)::numeric) / NULLIF(SUM(l_pipe)::numeric, 0), 2 ),
                                        0
                                        ) AS avg_lk,

                                        MAX(pipe_age)   AS age_max,
                                        MIN(pipe_age)   AS age_min,

                                        MIN(w_age)          AS w_age,
                                        MAX(pipe_age_ww)    AS pipe_age_ww,

                                        SUM(c_pipe)         AS c_pipe,
                                        SUM(l_pipe)         AS l_pipe,      
                                        SUM(c_leak)         AS c_leak,

                                        MAX(w_leak)         AS w_leak,
                                        MAX(c_leak_ww)      AS c_leak_ww,

                                        SUM(cost_repair)    AS cost_repair,
                                        MAX(w_cost)         AS w_cost,
                                        MAX(cost_repair_ww) AS cost_repair_ww,

                                        MAX(elev)           AS elev,
                                        MAX(w_elev)         AS w_elev,
                                        MAX(elev_ww)        AS elev_ww,

                                        MAX(ptype)          AS ptype,
                                        MAX(w_ptype)        AS w_ptype,
                                        MAX(ptype_ww)       AS ptype_ww,

                                        MAX(pressure)       AS pressure,
                                        MAX(w_pressure)     AS w_pressure,
                                        MAX(pressure_ww)    AS pressure_ww,

                                        MAX(w_branch)       AS w_branch,
                                        MAX(branch_ww)      AS branch_ww,

                                        MAX(w_dma)          AS w_dma,
                                        MAX(dma_ww)         AS dma_ww,

                                        SUM(CASE WHEN sum_ww >= 0 AND sum_ww <= 1 THEN c_pipe ELSE 0 END) AS lr,
                                        SUM(CASE WHEN sum_ww >= 0 AND sum_ww <= 1 THEN l_pipe ELSE 0 END) AS long_lr,

                                        SUM(CASE WHEN sum_ww > 1 AND sum_ww <= 2 THEN c_pipe ELSE 0 END)  AS mr,
                                        SUM(CASE WHEN sum_ww > 1 AND sum_ww <= 2 THEN l_pipe ELSE 0 END)  AS long_mr,

                                        SUM(CASE WHEN sum_ww > 2 AND sum_ww <= 3 THEN c_pipe ELSE 0 END)  AS hr,
                                        SUM(CASE WHEN sum_ww > 2 AND sum_ww <= 3 THEN l_pipe ELSE 0 END)  AS long_hr
                                    FROM pp
                                    GROUP BY
                                        dma_no, dma_name, pipe_type, pipe_size
                                    )
                                    SELECT *
                                    FROM agg
                                    ORDER BY dma_no, dma_name, pipe_type, pipe_size;" ;

    //echo  $sqlPipetypesizefreq_dma;
    //exit();


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

    //echo ' value_1 : '.$age_data['value_1']. 'and value_2 : '.$age_data['value_2'];
    //exit();

    //set data for geojson 
    $strPipetyesizefreq_map = " SET CLIENT_ENCODING TO 'utf-8';
                                SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                                FROM (SELECT 'Feature' As type 
                                , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                                , row_to_json((SELECT l FROM (SELECT pipe_id, avg_lk, c_leak, w_leak, c_leak_ww,
                                                cost_repair, w_cost, cost_repair_ww, pipe_type, pipe_size, pipe_age, 
                                                w_age, pipe_age_ww, elev, w_elev, elev_ww, ptype, w_ptype, ptype_ww,
                                                pressure, w_pressure, pressure_ww, w_branch, branch_ww, w_dma, dma_ww, 
                                                sum_ww, pipe_long, project_no, contrac_date,   
                                                asset_code, pipe_func, laying, product, depth, locate, dma_no, dma_name, dma_no_list, dma_name_list) As l 
                                )) As properties 
                                FROM (SELECT pipe_id, 
                                            CASE WHEN pipe_long != 0 THEN ROUND(c_leak/pipe_long,2) 
                                            ELSE 0 END avg_lk,
                                            c_leak,
                            {$w_leak} w_leak,
                            {$w_leak} * (CASE WHEN c_leak < {$leak_data['value_1']} THEN 1 
                                                    WHEN  c_leak >= {$leak_data['value_1']}
                                                            AND c_leak <= {$leak_data['value_2']} THEN 2
                                                    WHEN  c_leak > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                                                cost_repair,
                            {$w_cost} w_cost,
                            {$w_cost} * 
                                                (CASE WHEN cost_repair < {$cost_data['value_1']} THEN 1
                                                    WHEN  cost_repair >= {$cost_data['value_1']} 
                                                    AND  cost_repair <= {$cost_data['value_2']} THEN 2
                                                    WHEN  cost_repair > {$cost_data['value_2']} THEN 3 
                                                    ELSE 0 END) cost_repair_ww, 
                                                pipe_type, pipe_size, pipe_age,
                            {$w_age} w_age,
                            {$w_age} * 
                                                (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                    WHEN pipe_age >= {$age_data['value_1']}
                                                    AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                    WHEN pipe_age >  {$age_data['value_2']} THEN 3 
                                                    ELSE 0 END) pipe_age_ww,
                            0 elev,
                            0 w_elev,
                            0 elev_ww,
                            0 ptype,
                            {$w_ptype} w_ptype,
                            {$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                            WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                            WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END) ptype_ww,
                            0 pressure,
                            0 w_pressure,
                            0 pressure_ww,
                            {$w_branch} w_branch, 
                            {$w_branch} * rg.r_score branch_ww, 
                            {$w_dma} w_dma, {$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END) dma_ww, 
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
                                                    ELSE 0 END)) 
                                                    + ({$w_age} * 
                                                    (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                        WHEN pipe_age >= {$age_data['value_1']}
                                                        AND pipe_age <= {$age_data['value_2']} THEN 2 
                                                        WHEN  pipe_age >  {$age_data['value_2']} THEN 3 
                                                        ELSE 0 END) ) 
                                                    + ({$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                                    WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                                                    WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END))
                                                    + ({$w_branch} * rg.r_score) 
                                                    +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                                    WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                                    WHEN dma_nrw > 40 THEN 3 ELSE 0 END))
                                                    ) sum_ww, 
                                                pipe_long, 
                                                CASE WHEN project_no IS NULL THEN '' ELSE project_no END project_no, 
                                                contrac_date, 
                                                CASE WHEN asset_code IS NULL THEN '' ELSE asset_code END asset_code, 
                                                pipe_func, laying, product, depth, locate, wkb_geometry, dma_no, dma_name, dma_no_list, dma_name_list  
                                        FROM dssnrw.pipe_summary ps 
                                        LEFT JOIN 
                                        (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg 
                                        ON ps.pwa_code = rg.pwa_code 
                                        WHERE ps.pwa_code = '{$pwa_code}' 
                                        ORDER BY pipe_id ) As lg ) As f )  As fc   ";
    //echo $strPipetyesizefreq_map;
    //exit();

    $Pipetyesizefreq_map_result = pg_exec($connection, $strPipetyesizefreq_map);

    if (!$Pipetyesizefreq_map_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['geo_map'] = array();
    } else {
        $data['status'] = 'true';
        $data['message'] = 'complete for create geojson in pipetypesize frequency';
        $arr = pg_fetch_array($Pipetyesizefreq_map_result, 0, PGSQL_NUM);
    }
    $data['geo_map'] = $arr[0];

    //echo json_encode($arr[0]);
    //exit(); 

} else if ($act == 'pipe_detail_freq_map') {

    $strSQL = "SET CLIENT_ENCODING TO 'utf-8';
                SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                FROM (SELECT 'Feature' As type 
                , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                , row_to_json((SELECT l FROM (SELECT pipe_id, avg_lk, c_leak, cost_repair, 
                                pipe_type, pipe_size, yearinstall, project_no, pipe_long, yearinstall, pipe_long, 
                                contrac_date, asset_code, pipe_func, laying, product, depth, locate, dma_no, dma_name) As l 
                )) As properties 
                FROM (SELECT pp.pipe_id, 
                                CASE WHEN sum(pp.pipe_long) != 0 THEN ROUND(COUNT(l.wkb_geometry)/sum(pp.pipe_long),6) ELSE 0 END avg_lk,
                                COUNT(l.leak_id) as c_leak,
                                SUM(l.repaircost) cost_repair,
                                pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, 
                                CASE WHEN pp.project_no IS NULL THEN '' ELSE pp.project_no END project_no, 
                                pp.contrac_date, 
                                CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                                pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.wkb_geometry, ps.dma_no, ps.dma_name  
                        FROM oracle.r{$zone}_pipe pp LEFT JOIN dssnrw_leak.r{$zone}_leakpoint l ON ST_Intersects(l.gen_geometry, pp.gen_geometry)
                        WHERE pp.pwa_code = '{$pwa_code}' AND pp.wkb_geometry IS NOT NULL
                        AND pp.pipe_id IN ({$pipe_id_form})
                        GROUP BY pp.pipe_id, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long,
                                pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                                pp.depth, pp.locate, pp.project_no, pp.wkb_geometry, ps.dma_no, ps.dma_name 
                        ORDER BY pp.pipe_id ) As lg ) As f )  As fc	";

    $arr_pipe = json_decode($pipe_id_form);

    $delSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                DELETE FROM dssnrw.p_temp_{$pwa_code}; COMMIT; ";

    pg_exec($connection, $delSQL);

    $pipe_id_list = '(';

    foreach ($arr_pipe as $key => $value) {

        $latlon = (array)json_encode($value->{'coordinates'});


        foreach ($latlon as $x) {
            $coor = '(';
            //var_dump($x);
            $y = explode('],', $x);
            //var_dump($y);
            foreach ($y as $val) {
                $str_pair = str_replace('[', '', $val);
                $str_pair = str_replace(']', '', $str_pair);
                $str_pair = str_replace(',', ' ', $str_pair);
                //echo "$str_pair\n";
                $coor .= $str_pair . ', ';
            }
        }
        $coor .= $str_pair . ')';
        $coor = str_replace(',)', ')', $coor);
        $pipe_id_list .= $key . ',';

        $istSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                    INSERT INTO dssnrw.p_temp_{$pwa_code} (pipe_id, pwa_code, wkb_geometry) 
                    VALUES ({$key}, '{$pwa_code}', ST_GeomFromText('LINESTRING{$coor}', 4326 ) ); COMMIT; ";
        //echo "$istSQL\n";
        pg_exec($connection, $istSQL);
        //exit();  
    }
    $pipe_id_list .= ')';
    $pipe_id_list = str_replace(',)', ')', $pipe_id_list);

    $gen_geom = " SET CLIENT_ENCODING TO 'utf-8'; 
                    ALTER TABLE dssnrw.p_temp_{$pwa_code}
                    DROP COLUMN IF EXISTS gen_geometry; COMMIT;
                    DROP INDEX IF EXISTS p_temp_{$pwa_code}_gengeometry_idx; COMMIT;
                    SELECT 
                    AddGeometryColumn ('dssnrw','p_temp_{$pwa_code}','gen_geometry',4326,'GEOMETRY',2); COMMIT;
                    CREATE INDEX p_temp_{$pwa_code}_gengeometry_idx ON dssnrw.p_temp_{$pwa_code} USING gist (gen_geometry); COMMIT;
                    UPDATE dssnrw.p_temp_{$pwa_code} SET gen_geometry = ST_SnapToGrid(wkb_geometry, 0.000001); COMMIT; ";

    pg_exec($connection, $gen_geom);

    //query summary pipe data 
    $data = array();
    //conclusion in branch with freq and weight
    $sqlPipesumfreq = " SELECT COUNT( DISTINCT pt.pipe_id) as c_pipe, 
                        SUM(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER) AS l_pipe, 
                        COUNT(DISTINCT lk.leak_id) c_leak,
                        SUM(CASE WHEN lk.repaircost IS NOT NULL THEN lk.repaircost ELSE 0 END) as cost_repair, 
                        ROUND(COUNT(DISTINCT lk.leak_id)/sum(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER),2) avg_lk
                                FROM dssnrw.p_temp_{$pwa_code} pt
                        LEFT JOIN 
                        (SELECT leak_id, leak_no, repaircost, pwa_code, wkb_geometry, gen_geometry 
                            FROM dssnrw_leak.r{$zone}_leakpoint ) lk 
                            ON ST_Intersects(lk.gen_geometry, pt.gen_geometry)
                        LEFT JOIN 
                        (SELECT pipe_id, c_leak, cost_repair, pwa_code, wkb_geometry
                        FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}' ) ps 
                            ON pt.pipe_id = ps.pipe_id  ";

    //echo $sqlPipesumfreq;
    //exit();

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


    //conclusion with weight pipe type size freq 
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
                            (SELECT ROUND(COUNT(DISTINCT lk.leak_id)/sum(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER),2) avg_lk,
                            pipe_type, pipe_size, pipe_age,
                            {$w_age} w_age, {$w_age} * (CASE WHEN  pipe_age < {$age_data['value_1']} THEN 1 
                                                    WHEN  pipe_age >= {$age_data['value_1']}
                                                    AND  pipe_age <= {$age_data['value_2']} THEN 2 
                                                    WHEN   pipe_age >  {$age_data['value_2']} THEN 3 
                                                    ELSE 0 END) pipe_age_ww, 
                            COUNT(pt.pipe_id) as c_pipe, SUM(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER) AS l_pipe, 
                            COUNT(DISTINCT lk.leak_id) as c_leak, {$w_leak} AS w_leak, 
                            {$w_leak} * (CASE WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1 
                                WHEN  SUM(c_leak) >= {$leak_data['value_1']}
                                        AND SUM(c_leak) <= {$leak_data['value_2']} THEN 2
                                WHEN  SUM(c_leak) > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                            cost_repair, 
                            {$w_cost} w_cost, 
                            {$w_cost} * (CASE WHEN SUM(cost_repair) < {$cost_data['value_1']} THEN 1
                                WHEN  SUM(cost_repair) >= {$cost_data['value_1']}  
                                AND  SUM(cost_repair) <= {$cost_data['value_2']}  THEN 2
                                WHEN  SUM(cost_repair) > {$cost_data['value_2']}  THEN 3 
                                ELSE 1 END) cost_repair_ww, 
                            {$w_branch} * rg.r_score , 
                            0 elev, 0 w_elev, 0 elev_ww, 
                            0 ptype, 
                            {$w_ptype} w_ptype, 
                            {$w_ptype} * (CASE WHEN pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                                WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END)  ptype_ww, 
                            0 pressure, 0 w_pressure, 0 pressure_ww, {$w_branch} w_branch, {$w_branch} * rg.r_score branch_ww, 
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
                                WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                                WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END)) +  
                                ({$w_branch} * rg.r_score )  
                                +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END))  sum_ww
                            FROM dssnrw.p_temp_{$pwa_code} pt 
                            LEFT JOIN 
                            (SELECT * FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}') ps
                            ON pt.pipe_id = ps.pipe_id  
                            LEFT JOIN 
                            (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                            ON ps.pwa_code = rg.pwa_code 
                            LEFT JOIN 
                            (SELECT leak_id, leak_no, pwa_code, wkb_geometry, gen_geometry FROM dssnrw_leak.r{$zone}_leakpoint 
                                WHERE pwa_code = '{$pwa_code}' ) lk  
                            ON ST_Intersects(lk.gen_geometry, pt.gen_geometry)
                            GROUP BY pipe_type, pipe_size,pipe_age, cost_repair, rg.r_score, dma_nrw
                            ORDER BY pipe_type, pipe_size, pipe_age, cost_repair) pp 
                            GROUP BY pp.pipe_type, pp.pipe_size, pp.w_age 
                            ORDER BY pipe_type, pipe_size ";

    $result_freq = pg_exec($connection, $sqlPipetypesizefreq_temp);
    $numrows = pg_numrows($result_freq);
    //echo $sqlPipetypesizefreq_temp;
    //exit();    

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
    //echo json_encode($data);
    //exit();              

    $sqlGroupWeight_temp =  " SET CLIENT_ENCODING TO 'utf-8'; 
                        SELECT 
                        SUM(CASE WHEN sw.sum_ww >= 0 AND sw.sum_ww <= 1 THEN 1 ELSE 0 END) lr,
                        SUM(CASE WHEN sw.sum_ww >= 0 AND sw.sum_ww <= 1 THEN pipe_long ELSE 0 END) long_lr,
                    SUM(CASE WHEN sw.sum_ww > 1 AND sw.sum_ww <= 2 THEN 1 ELSE 0 END) mr,
                        SUM(CASE WHEN sw.sum_ww > 1 AND sw.sum_ww <= 2 THEN pipe_long ELSE 0 END) long_mr,
                    SUM(CASE WHEN sw.sum_ww > 2 AND sw.sum_ww <= 3 THEN 1 ELSE 0 END) hr,
                        SUM(CASE WHEN sw.sum_ww > 2 AND sw.sum_ww <= 3 THEN pipe_long ELSE 0 END) long_hr
                        FROM 
                    (SELECT pt.pipe_id, ST_Length(pt.wkb_geometry::geography)::INTEGER AS pipe_long,
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
                            WHEN pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                            WHEN pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END) )
                        + ({$w_branch} * rg.r_score) 
                        +({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END))
                        ) sum_ww 
                            FROM dssnrw.p_temp_{$pwa_code} pt
                            LEFT JOIN (SELECT*FROM dssnrw.pipe_summary WHERE pwa_code='{$pwa_code}') ps 
                                ON pt.pipe_id=ps.pipe_id 
                            LEFT JOIN (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg 
                                ON ps.pwa_code=rg.pwa_code 
                            LEFT JOIN (SELECT leak_id,
                            leak_no,
                            pwa_code,
                            wkb_geometry,
                            gen_geometry FROM dssnrw_leak.r{$zone}_leakpoint WHERE pwa_code='{$pwa_code}}' ) lk 
                                ON ST_Intersects(lk.gen_geometry, pt.gen_geometry)
                            ) sw  ";

    //echo $sqlGroupWeight_temp;
    //exit();0



























































































































































































































































































































































































































































































































































































































































































































































































































































































































































































    $sqlGroupWeight_temp_result = pg_exec($connection, $sqlGroupWeight_temp);

    $numrows = pg_numrows($sqlGroupWeight_temp_result);

    if (!$sqlGroupWeight_temp_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['pw_group_weight'] = array();
    } else {

        $data['status'] = 'true';
        $data['message'] = 'complete for pt_group_weight.';

        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($sqlGroupWeight_temp_result, $ri);

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

    $geoSQL = "SET CLIENT_ENCODING TO 'utf-8'; 
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
            FROM (SELECT pt.pipe_id, 
                            CASE WHEN sum(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER) != 0 
                            THEN ROUND(COUNT(DISTINCT l.leak_id)/sum(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER),
        2) ELSE 0 END avg_lk,
                            COUNT(DISTINCT l.leak_id) as c_leak,
        {$w_leak} w_leak,
        {$w_leak} * 
                            (CASE WHEN CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) < {$leak_data['value_1']} THEN 1 
                                WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) >= {$leak_data['value_1']}
                                        AND CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) <= {$leak_data['value_2']} THEN 2
                                WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                                SUM(l.repaircost) cost_repair,
        {$w_cost} w_cost,
        {$w_cost} * 
                            (CASE WHEN SUM(l.repaircost) < {$cost_data['value_1']} THEN 1
                                WHEN  SUM(l.repaircost) >= {$cost_data['value_1']} 
                                AND  SUM(l.repaircost) <= {$cost_data['value_2']} THEN 2
                                WHEN  SUM(l.repaircost) > {$cost_data['value_2']} THEN 3 
                                ELSE 1 END) cost_repair_ww, 
                            pp.pipe_type, pp.pipe_size, 
                            CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall pipe_age,
        {$w_age} w_age,
        {$w_age} * (CASE WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall < {$age_data['value_1']} THEN 1 
                                WHEN CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall >= {$age_data['value_1']}
                                AND CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall <= {$age_data['value_2']} THEN 2 
                                WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall >  {$age_data['value_2']} THEN 3 
                                ELSE 0 END) pipe_age_ww,
        {$w_branch} w_branch, 
        {$w_branch} * rg.r_score branch_ww, 
        0 elev,
        0 w_elev,
        0 elev_ww,
        pp.pipe_type ptype,
        {$w_ptype} w_ptype,
        {$w_ptype} * (CASE WHEN pp.pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
        WHEN pp.pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
        WHEN pp.pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END) ptype_ww,
        0 pressure,
        0 w_pressure,
        0 pressure_ww,
        {$w_dma} w_dma, {$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END) dma_ww, 

                            ({$w_leak} * 
                            (CASE WHEN CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) < {$leak_data['value_1']} THEN 1 
                                WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) >= {$leak_data['value_1']}
                                        AND CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) <= {$leak_data['value_2']} THEN 2
                                WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) > {$leak_data['value_2']} THEN 3 END )
                                + ({$w_cost} * 
                                (CASE WHEN SUM(l.repaircost) < {$cost_data['value_1']} THEN 1
                                WHEN  SUM(l.repaircost) >= {$cost_data['value_1']} 
                                AND  SUM(l.repaircost) <= {$cost_data['value_2']} THEN 2
                                WHEN  SUM(l.repaircost) > {$cost_data['value_2']} THEN 3 
                                ELSE 1 END)) 
                                + ({$w_age} * 
                                (CASE WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall < {$age_data['value_1']} THEN 1 
                                    WHEN CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall >= {$age_data['value_1']}
                                    AND CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall <= {$age_data['value_2']} THEN 2 
                                    WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall >  {$age_data['value_2']} THEN 3 
                                    ELSE 0 END) ) 
                                    + ({$w_ptype} * (CASE WHEN pp.pipe_type IN ('ST', 'ST_UN', 'ST_ON', 'ST_CV', 'GS', 'DI', 'CI') THEN 1 
                                                    WHEN pp.pipe_type IN ('PVC', 'PVC_O', 'HDPE', 'PB') THEN 2 
                                                    WHEN pp.pipe_type IN ('GRP','AC') THEN 3 ELSE 1 END)) 
                                + ({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) 
                                            + ({$w_branch} * rg.r_score) ) sum_ww, 
                            ST_Length(pt.wkb_geometry::geography)::INTEGER pipe_long, 
                            CASE WHEN pp.project_no IS NULL THEN '' ELSE pp.project_no END project_no, 
                            pp.contrac_date, 
                            CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                            pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pt.wkb_geometry, dma_no, dma_name 
                    FROM dssnrw.p_temp_{$pwa_code} pt LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.gen_geometry, pt.gen_geometry)
                    LEFT JOIN oracle.r{$zone}_pipe pp ON pt.pipe_id = pp.pipe_id AND pt.pwa_code = pp.pwa_code 
                    LEFT JOIN dssnrw.pipe_summary ps ON pp.pwa_code = ps.pwa_code AND pp.pipe_id = ps.pipe_id 
                    LEFT JOIN (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group ) rg 
                            ON ps.pwa_code = rg.pwa_code 
                    GROUP BY pt.pipe_id, pp.pipe_type, pp.pipe_size, pp.yearinstall, rg.r_score,pt.wkb_geometry, 
                            pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                            pp.depth, pp.locate, pp.project_no, dma_nrw,  dma_no, dma_name    
                    ORDER BY pt.pipe_id ) As lg ) As f )  As fc  ";

    //echo $geoSQL;
    //exit() ;

    $resultmap = pg_exec($connection, $geoSQL);

    if (!$resultmap) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['geo_map'] = array();
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
        $data['status'] = 'true';
        $data['message'] = 'Complete get map and data from pipe target';
        $data['geo_map'] = $arr[0];
    }
    echo json_encode($data);
    exit();
} else if ($act == 'pipe_proj_save_map') {

    $data = array();
    //encode array from front 
    $arr_pipe = json_decode($pipe_id_form);

    //state for insert project name and id 
    $c_proj = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT MAX(pid_no)+1 AS prs_id FROM dssnrw.ref_pipe_improve ";

    $c_proj_result =  pg_exec($connection, $c_proj);

    $numrows = pg_numrows($c_proj_result);

    if (!$c_proj_result) {
        $prs_id = 1;
    } else {
        $arr_c_proj = pg_fetch_array($c_proj_result, 0, PGSQL_NUM);
        $prs_id = $arr_c_proj[0];
    }


    //echo $prs_id;
    //exit();

    $pipe_id_list = '(';

    foreach ($arr_pipe as $key => $value) {

        $latlon = (array)json_encode($value->{'coordinates'});

        foreach ($latlon as $x) {
            $coor = '(';
            //var_dump($x);
            $y = explode('],', $x);
            //var_dump($y);
            foreach ($y as $val) {
                $str_pair = str_replace('[', '', $val);
                $str_pair = str_replace(']', '', $str_pair);
                $str_pair = str_replace(',', ' ', $str_pair);
                //echo "$str_pair\n";
                $coor .= $str_pair . ', ';
            }
        }
        $coor .= $str_pair . ')';
        $coor = str_replace(',)', ')', $coor);
        $pipe_id_list .= $key . ',';
        $project_prov_id = project_prov_id($proj_fiscal, $proj_budget, $pwa_code, $prs_id);

        $istSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                    INSERT INTO dssnrw.pipe_improve (zone, pwa_code, pipe_id, fiscal_year, budget_type, project_prov_id, project_prov_name, 
                        pipe_long, wkb_geometry, gen_geometry, created_date, remark, proj_cost) 
                    VALUES ({$zone}, '{$pwa_code}', {$key}, '{$proj_fiscal}', {$proj_budget}, {$project_prov_id}, '{$proj_name}', 
                        ST_Length(ST_Transform(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 32647)),
                        ST_GeomFromText('LINESTRING{$coor}', 4326 ),
                        ST_SnapToGrid(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 0.000001), 
                        now(), '{$proj_remark}', {$proj_cost} ); COMMIT; ";

        //echo "$istSQL\n";
        pg_exec($connection, $istSQL);
    }

    $istref_SQL  =  " SET CLIENT_ENCODING TO 'utf-8'; 
                    INSERT INTO dssnrw.ref_pipe_improve (zone, pwa_code, fiscal_year, budget_type, project_prov_id,
                        project_prov_name, created_date, remark, proj_cost) 
                    VALUES ({$zone}, '{$pwa_code}', '{$proj_fiscal}', {$proj_budget}, {$project_prov_id}, '{$proj_name}', 
                        now(), '{$proj_remark}', {$proj_cost} ); ";


    pg_exec($connection, $istref_SQL);

    //exit(); 
    $pipe_id_list .= ')';
    $pipe_id_list = str_replace(',)', ')', $pipe_id_list);

    $update_typesize = " UPDATE dssnrw.pipe_improve pm 
                            SET pipe_type = pa.pipe_type, pipe_size = pa.pipe_size, pipe_age = pa.pipe_age,
                                yearinstall = pa.yearinstall, contrac_date = pa.contrac_date, 
                                asset_code = pa.asset_code, pipe_func = pa.pipe_func, 
                                laying = pa.laying, product = pa.product, depth = pa.depth,
                                locate = pa.locate, project_no = pa.project_no, c_leak = pa.c_leak,
                                cost_repair = pa.cost_repair, dma_id = pa.dma_id, dma_no = pa.dma_no,
                                    dma_name = pa.dma_name, 
                                    dma_nrw = pa.dma_nrw, r_score = pa.r_score,
                                    pipeold_long = pa.pipeold_long 

                            FROM 
                            (
                                SELECT pm.pipe_id, pm.pwa_code, 
                            pp.pipe_type, pp.pipe_size, ps.pipe_age, 
                            pp.yearinstall, pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                            pp.depth, pp.locate, pp.project_no, 
                            COUNT(DISTINCT lk.leak_id) as c_leak,  
                            lk.repaircost AS cost_repair, ps.dma_nrw, rg.r_score,
                            ps.dma_id, ps.dma_no, ps.dma_name, pp.pipe_long AS pipeold_long 
                            FROM dssnrw.pipe_improve pm
                            LEFT JOIN oracle.r{$zone}_leakpoint lk
                            ON ST_Intersects(lk.gen_geometry, pm.gen_geometry) 
                            LEFT JOIN oracle.r{$zone}_pipe pp 
                            ON pm.pwa_code = pp.pwa_code AND pm.pipe_id = pp.pipe_id 
                            LEFT JOIN dssnrw.pipe_summary ps
                            ON pm.pwa_code = ps.pwa_code AND pm.pipe_id = ps.pipe_id 
                            LEFT JOIN 
                            (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                            ON ps.pwa_code = rg.pwa_code 
                            WHERE ps.pwa_code = '{$pwa_code}' 
                            GROUP BY pm.pipe_id, pm.pwa_code, pp.pipe_type, pp.pipe_size, ps.pipe_age, lk.repaircost, rg.r_score, ps.dma_nrw,ps.dma_id, ps.dma_no, ps.dma_name, 
                                pp.yearinstall, pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, 
                                pp.product, pp.depth, pp.locate, pp.project_no, pp.pipe_long
                            ) pa

                            WHERE pm.pwa_code = pa.pwa_code AND pm.pipe_id = pa.pipe_id 
                                AND pm.pipe_id IN $pipe_id_list ; COMMIT; ";

    pg_exec($connection, $update_typesize);

    $data['status'] = 'true';
    $data['message'] = 'Complete for save pipe projects.';
    echo json_encode($data);
    exit();
}

echo json_encode($data);
