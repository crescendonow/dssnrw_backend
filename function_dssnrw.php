<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

// ฟังก์ชันสำหรับ query ข้อมูลและคืนค่า array
function fetch_query($connection, $sql) {
    $result = pg_exec($connection, $sql);
    if (!$result) return [ 'success' => false, 'rows' => [], 'error' => pg_last_error($connection) ];
    $rows = [];
    $numrows = pg_numrows($result);
    for ($ri = 0; $ri < $numrows; $ri++) {
        $rows[] = pg_fetch_array($result, $ri);
    }
    return [ 'success' => true, 'rows' => $rows ];
}

// ฟังก์ชันสำหรับดึงข้อมูล pipe summary ทั้งหมด
function get_pipeall_data($connection, $zone, $pwa_code) {
    $sql = "SET CLIENT_ENCODING TO 'utf-8'; 
            SELECT * FROM dssnrw.data_summary 
            WHERE zone = '{$zone}' AND pwa_code = '{$pwa_code}' ";
    $res = fetch_query($connection, $sql);
    if (!$res['success'] || empty($res['rows'])) return [];
    $row = $res['rows'][0];
    return [
        'update_data' => $row['update_data'],
        'c_pipe' => $row['c_pipe'],
        'l_pipe' => $row['l_pipe'],
        'c_mt' => $row['c_mt'],
        'c_valve' => $row['c_valve'],
        'c_fire' => $row['c_fire'],
        'c_leak' => $row['c_leak'],
    ];
}

// ฟังก์ชันสำหรับข้อมูลอายุท่อ (static)
function get_age_data() {
    return [
        'max' => '-',
        'min' => '-',
        'sd' => '-',
        'mean' => '-',
        'value_1' => 10,
        'value_2' => 19
    ];
}

// ฟังก์ชันสำหรับดึงข้อมูล summary leak/cost
function get_summary_data($connection, $sql) {
    $res = fetch_query($connection, $sql);
    if (!$res['success'] || empty($res['rows'])) return [];
    $row = $res['rows'][0];
    return [
        'max' => $row['max'],
        'min' => $row['min'],
        'sd' => $row['sd'],
        'mean' => $row['mean'],
        'value_1' => $row['value_1'],
        'value_2' => $row['value_2'],
    ];
}

// ฟังก์ชันตรวจสอบว่า array เป็นตัวเลขทั้งหมดหรือไม่
function all_numeric($arr) {
    return array_reduce($arr, function ($carry, $item) {
        return $carry && is_numeric(trim($item));
    }, true);
}

// ฟังก์ชันสร้าง temp table 
function create_temp_table($pwa_code, $connection) {
    $sql = "CREATE TABLE IF NOT EXISTS dssnrw.p_temp_{$pwa_code} (
        pipe_id INTEGER,
        pwa_code VARCHAR(10),
        wkb_geometry geometry
    );";
    $result = pg_exec($connection, $sql);
    return $result ? 'true' : 'false';
}

//ตัวอย่างฟังก์ชันอื่นๆ ที่ต้องใช้ เช่น project_prov_id

function project_prov_id($proj_fiscal, $proj_budget, $pwa_code, $prs_id) {
    // 1. ตัดเลขปีงบประมาณให้เป็น 2 หลักท้าย (เช่น 2567 -> 67)
    $fiscal_short = substr($proj_fiscal, -2);

    // 2. ตัดเลขรหัส PWA ให้เหลือ 4 หลักแรก (เช่น 5511001 -> 5511)
    $pwa_short = substr($pwa_code, 0, 4);

    // 3. แปลงรหัสงบประมาณให้เป็น 2 หลัก (ถ้าไม่ใช่ตัวเลข ให้ใช้ 00)
    $budget_short = is_numeric($proj_budget) ? str_pad($proj_budget, 2, "0", STR_PAD_LEFT) : "00";

    // 4. แปลงเลข running id ให้เป็น 4 หลัก (เช่น 1 -> 0001)
    $prs_id_short = str_pad($prs_id, 4, "0", STR_PAD_LEFT);

    // 5. รวมรหัสทั้งหมดเป็น project_prov_id (รูปแบบ: ปี2หลัก + pwa4หลัก + งบ2หลัก + running4หลัก)
    $project_prov_id = $fiscal_short . $pwa_short . $budget_short . $prs_id_short;

    // 6. คืนค่า project_prov_id
    return $project_prov_id;
}

// ฟังก์ชันสำหรับ handle upload excel

function handle_upload_excel($connection, $pwa_code, $zone, &$data) {
    require_once('excel_lib/vendor/autoload.php');
    use PhpOffice\PhpSpreadsheet\IOFactory;

    // 1. สร้าง temp table
    $create_result = create_temp_table($pwa_code, $connection);
    $data['status'] = $create_result;
    $data['message'] = "Complete create p_temp_{$pwa_code}";

    // 2. เตรียมโฟลเดอร์และไฟล์
    $targetDir = "upload_file/xlsx_{$pwa_code}";
    $fileName = basename($_FILES["file"]["name"]);
    $targetFilePath = $targetDir . "/" . $fileName;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    } else {
        $oldFiles = glob($targetDir . '*');
        foreach ($oldFiles as $oldFile) {
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }
        $data['upload_message'] = "Folder exists: {$targetDir} - deleted old files";
    }

    if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
        $data['upload_message'] = "Upload successful: {$fileName}";
    } else {
        $data['upload_message'] = 'Upload failed.';
        $data['status'] = 'false';
        echo json_encode($data); exit();
    }

    // 3. โหลดไฟล์ Excel
    $spreadsheet = IOFactory::load($targetFilePath);
    $worksheet = $spreadsheet->getSheet(0);
    $data_excel = $worksheet->toArray();

    if (empty($data_excel)) {
        $data['status'] = 'false';
        $data['message'] = 'No data in excel file.';
        echo json_encode($data); exit();
    }

    // 4. หา index ของ PIPE_ID
    $headers = $data_excel[0];
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
        echo json_encode($data); exit();
    }

    // 5. ดึง pipe_id ทั้งหมด
    $pipe_id_array = [];
    for ($i = 1; $i < count($data_excel); $i++) {
        $row = $data_excel[$i];
        if (!isset($row[$pipeIdIndex]) || trim($row[$pipeIdIndex]) === '' || $row[$pipeIdIndex] === null) continue;
        $pipeId = trim($row[$pipeIdIndex]);
        if (is_numeric($pipeId)) $pipe_id_array[] = $pipeId;
    }
    $pipe_id_form = implode(',', $pipe_id_array);
    $pipe_chk = explode(",", $pipe_id_form);

    // 6. ตรวจสอบว่าเป็นตัวเลขทั้งหมด
    if (!all_numeric($pipe_chk)) {
        $data['status'] = 'false';
        $data['message'] = "Please input correct format like 14, 25, 300 ";
        echo json_encode($data); exit();
    }

    $data['status'] = 'true';
    $data['message'] = json_encode("correct format.");

    // 7. Query สรุปข้อมูล pipe_sum_freq
    $sqlPipesumfreq = " SELECT COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                        SUM(c_leak) c_leak,sum(cost_repair) as cost_repair, 
                        ROUND(SUM(c_leak)/sum(pipe_long),2) avg_lk
                        FROM dssnrw.pipe_summary
                        WHERE pwa_code = '{$pwa_code}' AND pipe_id IN ({$pipe_id_form})";
    $Psumfreq_result = pg_exec($connection, $sqlPipesumfreq);
    $numrows = pg_numrows($Psumfreq_result);

    if (!$Psumfreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 8. Query pipe type size freq
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
    $Pipetypesizefreq_result = pg_exec($connection, $sqlPipetypesizefreq);
    $numrows = pg_numrows($Pipetypesizefreq_result);

    if (!$Pipetypesizefreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 9. ตรวจสอบ pipe_id ที่มีและไม่มีใน oracle
    $str_check_id = " SET CLIENT_ENCODING TO 'utf-8';    
                    SELECT pipe_id FROM oracle.r{$zone}_pipe 
                    WHERE pipe_id IN ({$pipe_id_form}) AND pwa_code = '{$pwa_code}' 
                    ORDER BY pipe_id ";
    $result_check = pg_exec($connection, $str_check_id);

    if (!$result_check) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
        $data['have_id'] = array();
        $data['no_id'] = array();
    } else {
        $data['status'] = 'true';
        $data['message'] = 'complete for pipe_detail_map.';
        $pid_array = array();
        while ($row = pg_fetch_array($result_check)) {
            array_push($pid_array, $row['pipe_id']);
        }
        $result = array_filter($pipe_chk, function ($value) use ($pid_array) {
            return !in_array($value, $pid_array);
        });
        $empty_pid = array_values($result);
        $data['have_id'] = $pid_array;
        $data['no_id'] = $empty_pid;
    }

    // 10. GeoJSON map
    $strSQL = " SET CLIENT_ENCODING TO 'utf-8';
                SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                FROM (SELECT 'Feature' As type 
                , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                , row_to_json((SELECT l FROM (SELECT pipe_id, project_no, pipe_type, pipe_size, 
                        yearinstall, pipe_long, contrac_date, asset_code, pipe_func, laying, 
                        product, depth, locate, c_leak, cost_repair, dma_name) As l 
                )) As properties 
                FROM (select pp.pipe_id, pp.project_no, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.contrac_date, 
                        CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                        pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.wkb_geometry,
                                            ps.c_leak, ps.cost_repair,ps.dma_name 
                        FROM oracle.r{$zone}_pipe pp 
                                            LEFT JOIN 
                                            (SELECT c_leak, cost_repair, pipe_id, pwa_code, dma_name FROM dssnrw.pipe_summary 
                                                WHERE pwa_code = '{$pwa_code}' ) ps
                                            ON pp.pwa_code = ps.pwa_code AND pp.pipe_id = ps.pipe_id 
                        WHERE pp.pwa_code = '{$pwa_code}' 
                        AND pp.pipe_id IN ({$pipe_id_form})
                        AND pp.wkb_geometry IS NOT NULL
                        ORDER BY pp.pipe_id ) As lg) As f )  As fc ";
    $resultmap = pg_exec($connection, $strSQL);

    if (!$resultmap) {
        $data['geo_map'] = array();
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
        $data['geo_map'] = $arr[0];
    }
    $data['pipe_id_form'] = $pipe_id_form;

    echo json_encode($data);
    pg_close($connection);
    exit();
}

// ฟังก์ชันสำหรับ handle pipe_detail_map

function handle_pipe_detail_map($connection, $pwa_code, $zone, $pipe_id_form, &$data) {
    // 1. สร้าง temp table สำหรับ pipe_id
    $create_result = create_temp_table($pwa_code, $connection);
    $data['status'] = $create_result;
    $data['message'] = "Complete create p_temp_{$pwa_code}";

    // 2. ตรวจสอบและเตรียม pipe_id
    $y = is_string($pipe_id_form) ? $pipe_id_form : strval($pipe_id_form);
    $pipe_chk = explode(",", $y);

    // 3. ตรวจสอบว่าเป็นตัวเลขทั้งหมด
    $allNumeric = array_reduce($pipe_chk, function ($carry, $item) {
        return $carry && is_numeric(trim($item));
    }, true);

    if (!$allNumeric) {
        $data['status'] = 'false';
        $data['message'] = "Please input correct format like 14, 25, 300 ";
        echo json_encode($data);
        exit();
    }

    $data['status'] = 'true';
    $data['message'] = json_encode("correct format.");

    // 4. Query summary pipe_sum_freq
    $sqlPipesumfreq = " SELECT COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                        SUM(c_leak) c_leak, sum(cost_repair) as cost_repair, 
                        ROUND(SUM(c_leak)/sum(pipe_long),2) avg_lk
                        FROM dssnrw.pipe_summary
                        WHERE pwa_code = '{$pwa_code}' AND pipe_id IN ({$pipe_id_form})";
    $Psumfreq_result = pg_exec($connection, $sqlPipesumfreq);
    $numrows = pg_numrows($Psumfreq_result);

    if (!$Psumfreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 5. Query pipe type size freq
    $sqlPipetypesizefreq =  " SET CLIENT_ENCODING TO 'utf-8'; 
        SELECT CASE WHEN sum(pp.l_pipe)!=0 THEN ROUND(SUM(pp.c_leak)/sum(pp.l_pipe),2) ELSE 0 END avg_lk, 
            pp.pipe_type, pp.pipe_size, MAX(pp.pipe_age) age_max, MIN(pp.pipe_age) age_min,
            SUM(pp.c_pipe) c_pipe, SUM(pp.l_pipe) l_pipe, SUM(pp.c_leak) c_leak, 
            SUM(pp.cost_repair) cost_repair, MAX(pp.elev) elev,
            MAX(pp.ptype) ptype, MAX(pp.pressure) pressure
        FROM (
            SELECT CASE WHEN sum(pipe_long) != 0 THEN ROUND(SUM(c_leak)/sum(pipe_long),2) ELSE 0 END avg_lk,
                pipe_type, pipe_size, pipe_age,
                COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                SUM(c_leak) as c_leak, 0 ptype, 0 pressure, 0 elev,   
                cost_repair, dma_nrw
            FROM dssnrw.pipe_summary ps 
            LEFT JOIN (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                ON ps.pwa_code = rg.pwa_code 
            WHERE ps.pwa_code = '{$pwa_code}' AND ps.pipe_id IN ({$pipe_id_form}) 
            GROUP BY pipe_type, pipe_size, pipe_age, cost_repair, rg.r_score, dma_nrw
            ORDER BY pipe_type, pipe_size, pipe_age, cost_repair
        ) pp 
        GROUP BY pp.pipe_type, pp.pipe_size
        ORDER BY pipe_type, pipe_size ";
    $Pipetypesizefreq_result = pg_exec($connection, $sqlPipetypesizefreq);
    $numrows = pg_numrows($Pipetypesizefreq_result);

    if (!$Pipetypesizefreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 6. ตรวจสอบ pipe_id ที่มีและไม่มีใน oracle
    $str_check_id = " SET CLIENT_ENCODING TO 'utf-8';    
        SELECT pipe_id FROM oracle.r{$zone}_pipe 
        WHERE pipe_id IN ({$pipe_id_form}) AND pwa_code = '{$pwa_code}' 
        ORDER BY pipe_id ";
    $result_check = pg_exec($connection, $str_check_id);

    if (!$result_check) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
        $data['have_id'] = array();
        $data['no_id'] = array();
    } else {
        $data['status'] = 'true';
        $data['message'] = 'complete for pipe_detail_map.';
        $pid_array = array();
        while ($row = pg_fetch_array($result_check)) {
            array_push($pid_array, $row['pipe_id']);
        }
        $result = array_filter($pipe_chk, function ($value) use ($pid_array) {
            return !in_array($value, $pid_array);
        });
        $empty_pid = array_values($result);
        $data['have_id'] = $pid_array;
        $data['no_id'] = $empty_pid;
    }

    //7. pipe_detail group by dma_no 
    
        $sqlPipetypesizefreq_dma =  "SET CLIENT_ENCODING TO 'utf-8';
                                    SELECT 
                                        CASE 
                                            WHEN SUM(pp.l_pipe) != 0 
                                                THEN ROUND(SUM(pp.c_leak) / SUM(pp.l_pipe), 2) 
                                            ELSE 0 
                                        END AS avg_lk,
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
                                            CASE 
                                                WHEN SUM(pipe_long) != 0 
                                                    THEN ROUND(SUM(c_leak) / SUM(pipe_long), 2) 
                                                ELSE 0 
                                            END AS avg_lk,
                                            pipe_type,
                                            pipe_size,
                                            pipe_age,
                                            COUNT(pipe_id)    AS c_pipe,
                                            SUM(pipe_long)    AS l_pipe,
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

    // 8. GeoJSON map
    $strSQL = " SET CLIENT_ENCODING TO 'utf-8';
        SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
        FROM (SELECT 'Feature' As type 
        , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
        , row_to_json((SELECT l FROM (SELECT pipe_id, project_no, pipe_type, pipe_size, 
                yearinstall, pipe_long, contrac_date, asset_code, pipe_func, laying, 
                product, depth, locate, c_leak, cost_repair, dma_no, dma_name) As l 
        )) As properties 
        FROM (select pp.pipe_id, pp.project_no, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.contrac_date, 
                CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.wkb_geometry,
                                    ps.c_leak, ps.cost_repair, ps.dma_no, ps.dma_name   
                FROM oracle.r{$zone}_pipe pp 
                                    LEFT JOIN 
                                    (SELECT c_leak, cost_repair, pipe_id, pwa_code, dma_no, dma_name FROM dssnrw.pipe_summary 
                                        WHERE pwa_code = '{$pwa_code}' ) ps
                                    ON pp.pwa_code = ps.pwa_code AND pp.pipe_id = ps.pipe_id 
                WHERE pp.pwa_code = '{$pwa_code}' 
                AND pp.pipe_id IN ({$pipe_id_form})
                AND pp.wkb_geometry IS NOT NULL
                ORDER BY pp.pipe_id ) As lg) As f )  As fc ";
    $resultmap = pg_exec($connection, $strSQL);

    if (!$resultmap) {
        $data['geo_map'] = array();
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
        $data['geo_map'] = $arr[0];
    }

    // 9. ส่งผลลัพธ์
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    pg_close($connection);
    exit();
}

// ฟังก์ชันสำหรับ handle f_weight

function handle_f_weight(
    $connection, $pwa_code, $zone,
    $w_age, $w_leak, $w_cost, $w_elev, $w_ptype, $w_pressure, $w_branch, $w_dma,
    &$data
) {
    // 1. เตรียมข้อมูลอายุ/รั่ว/ซ่อม (ควรรับมาจาก global หรือ parameter)
    $age_data = get_age_data();
    $leak_data = get_summary_data(
        $connection,
        "SELECT MAX(c_leak) max, MIN(c_leak) min, ROUND(STDDEV(c_leak), 2) AS sd, ROUND(AVG(c_leak), 2) AS mean,  
            CASE WHEN CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) <= 0 THEN 1 
                ELSE CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) END value_1, 
            CAST(AVG(c_leak) + (1*STDDEV(c_leak)) AS INTEGER) value_2 
            FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}'"
    );
    $cost_data = get_summary_data(
        $connection,
        "SELECT MAX(cost_repair) max, MIN(cost_repair) min, ROUND(STDDEV(cost_repair), 2) AS sd, ROUND(AVG(cost_repair), 2) mean,  
            CASE WHEN CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) <= 0 THEN 1  
                ELSE CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) END value_1, 
            CAST(AVG(cost_repair) + (1*STDDEV(cost_repair)) AS INTEGER) value_2 
            FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}'"
    );

    // 2. Query สรุปข้อมูลรวม
    $sqlPipesumfreq = "SELECT ds.*, ROUND(c_leak::numeric / NULLIF(l_pipe, 0), 4) AS avg_lk 
                       FROM dssnrw.data_summary ds
                       WHERE pwa_code = '{$pwa_code}'";
    $Psumfreq_result = pg_exec($connection, $sqlPipesumfreq);
    $numrows = pg_numrows($Psumfreq_result);

    if (!$Psumfreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 3. Query สรุปตาม type/size พร้อม weight
    $sqlPipetypesizefreq = "SET CLIENT_ENCODING TO 'utf-8'; 
        SELECT CASE WHEN sum(pp.l_pipe)!=0 THEN ROUND(SUM(pp.c_leak)/sum(pp.l_pipe),2) ELSE 0 END avg_lk, 
            pp.pipe_type, pp.pipe_size, MAX(pp.pipe_age) age_max, MIN(pp.pipe_age) age_min,
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
        FROM (
            SELECT CASE WHEN sum(pipe_long) != 0 THEN ROUND(SUM(c_leak)/sum(pipe_long),2) ELSE 0 END avg_lk,
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
            LEFT JOIN (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                ON ps.pwa_code = rg.pwa_code 
            WHERE ps.pwa_code = '{$pwa_code}' 
            GROUP BY pipe_type, pipe_size,pipe_age, cost_repair, rg.r_score, dma_nrw
            ORDER BY pipe_type, pipe_size, pipe_age, cost_repair
        ) pp 
        GROUP BY pp.pipe_type, pp.pipe_size, pp.w_age
        ORDER BY pipe_type, pipe_size";
    $Pipetypesizefreq_result = pg_exec($connection, $sqlPipetypesizefreq);
    $numrows = pg_numrows($Pipetypesizefreq_result);

    if (!$Pipetypesizefreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // --- pw_typesize_freq_dma ---
    $sqlPipetypesizefreq_dma =  " SET CLIENT_ENCODING TO 'utf-8';

                                WITH pp AS (
                                SELECT
                                    CASE WHEN SUM(pipe_long) <> 0
                                        THEN ROUND(SUM(c_leak) / SUM(pipe_long), 2)
                                        ELSE 0
                                    END AS avg_lk,

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

                                    COUNT(pipe_id)   AS c_pipe,
                                    SUM(pipe_long)   AS l_pipe,
                                    SUM(c_leak)      AS c_leak,

                                    {$w_leak}             AS w_leak,
                                    {$w_leak} * (
                                        CASE
                                        WHEN SUM(c_leak) < {$leak_data['value_1']} THEN 1
                                        WHEN SUM(c_leak) BETWEEN {$leak_data['value_1']} AND {$leak_data['value_2']} THEN 2
                                        WHEN SUM(c_leak) > {$leak_data['value_2']} THEN 3
                                        END
                                    ) AS c_leak_ww,

                                    SUM(cost_repair) AS cost_repair,

                                    {$w_cost}   AS w_cost,
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

                                    0     AS ptype,
                                    {$w_ptype}  AS w_ptype,
                                    {$w_ptype} * (
                                        CASE
                                        WHEN pipe_type IN ('ST','ST_UN','ST_ON','ST_CV','GS','DI','CI') THEN 1
                                        WHEN pipe_type IN ('PVC','PVC_O','HDPE','PB')              THEN 2
                                        WHEN pipe_type IN ('AC', 'GRP')                            THEN 3
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

                                    CASE WHEN SUM(l_pipe) <> 0
                                        THEN ROUND(SUM(c_leak) / SUM(l_pipe), 2)
                                        ELSE 0
                                    END AS avg_lk,

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

    
    $Pipetypesizefreq_dma_result = pg_exec($connection, $sqlPipetypesizefreq_dma);
    $numrows = pg_numrows($Pipetypesizefreq_dma_result);

    if (!$Pipetypesizefreq_dma_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
        $data['pw_typesize_freq_dma'] = array();
    } else {
        $data['status'] = 'true';
        $data['message'] = 'complete for pipe type size with weight and dma.';
        $pw_typesize_dma = array();
        for ($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($Pipetypesizefreq_dma_result, $ri);
            array_push($pw_typesize_dma, array(
                'avg_lk' => $row['avg_lk'],
                'dma_no' => $row['dma_no'],
                'dma_name' => $row['dma_name'],
                'pipe_type' => $row['pipe_type'],
                'pipe_size' => $row['pipe_size'],
                'age_max' => $row['age_max'],
                'age_min' => $row['age_min'],
                'w_age' => $row['w_age'],
                'pipe_age_ww' => $row['pipe_age_ww'],
                'c_pipe' => $row['c_pipe'],
                'l_pipe' => $row['l_pipe'],
                'c_leak' => $row['c_leak'],
                'w_leak' => $row['w_leak'],
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
                'w_dma' => $row['w_dma'],
                'dma_ww' => $row['dma_ww'],
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

    // 4. ส่งผลลัพธ์
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    pg_close($connection);
    exit();
}

// ฟังก์ชันสำหรับ handle pipe_detail_freq_map

function handle_pipe_detail_freq_map($connection, $pwa_code, $zone, $pipe_id_form, $w_age, $w_leak, $w_cost, $w_ptype, $w_branch, $w_dma, $age_data, $leak_data, $cost_data, &$data) {
    // 1. แปลง pipe_id_form เป็น array
    $arr_pipe = json_decode($pipe_id_form);

    // 2. ลบข้อมูล temp เดิม
    $delSQL = "SET CLIENT_ENCODING TO 'utf-8'; 
               DELETE FROM dssnrw.p_temp_{$pwa_code}; COMMIT;";
    pg_exec($connection, $delSQL);

    // 3. เตรียมข้อมูล geometry และ insert ลง temp
    $pipe_id_list = '(';
    foreach ($arr_pipe as $key => $value) {
        $latlon = (array)json_encode($value->{'coordinates'});
        foreach ($latlon as $x) {
            $coor = '(';
            $y = explode('],', $x);
            foreach ($y as $val) {
                $str_pair = str_replace('[', '', $val);
                $str_pair = str_replace(']', '', $str_pair);
                $str_pair = str_replace(',', ' ', $str_pair);
                $coor .= $str_pair . ', ';
            }
        }
        $coor .= $str_pair . ')';
        $coor = str_replace(',)', ')', $coor);
        $pipe_id_list .= $key . ',';

        $istSQL = "SET CLIENT_ENCODING TO 'utf-8'; 
                   INSERT INTO dssnrw.p_temp_{$pwa_code} (pipe_id, pwa_code, wkb_geometry) 
                   VALUES ({$key}, '{$pwa_code}', ST_GeomFromText('LINESTRING{$coor}', 4326)); COMMIT;";
        pg_exec($connection, $istSQL);
    }
    $pipe_id_list .= ')';
    $pipe_id_list = str_replace(',)', ')', $pipe_id_list);

    // 4. สร้าง geometry column และ index
    $gen_geom = "SET CLIENT_ENCODING TO 'utf-8'; 
        ALTER TABLE dssnrw.p_temp_{$pwa_code} DROP COLUMN IF EXISTS gen_geometry; COMMIT;
        DROP INDEX IF EXISTS p_temp_{$pwa_code}_gengeometry_idx; COMMIT;
        SELECT AddGeometryColumn ('dssnrw','p_temp_{$pwa_code}','gen_geometry',4326,'GEOMETRY',2); COMMIT;
        CREATE INDEX p_temp_{$pwa_code}_gengeometry_idx ON dssnrw.p_temp_{$pwa_code} USING gist (gen_geometry); COMMIT;
        UPDATE dssnrw.p_temp_{$pwa_code} SET gen_geometry = ST_SnapToGrid(wkb_geometry, 0.000001); COMMIT;";
    pg_exec($connection, $gen_geom);

    // 5. Query summary pipe data
    $sqlPipesumfreq = "SELECT COUNT(DISTINCT pt.pipe_id) as c_pipe, 
                        SUM(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER) AS l_pipe, 
                        COUNT(DISTINCT lk.leak_id) c_leak,
                        SUM(CASE WHEN lk.repaircost IS NOT NULL THEN lk.repaircost ELSE 0 END) as cost_repair, 
                        ROUND(COUNT(DISTINCT lk.leak_id)/sum(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER),2) avg_lk
                        FROM dssnrw.p_temp_{$pwa_code} pt
                        LEFT JOIN 
                        (SELECT leak_id, leak_no, repaircost, pwa_code, wkb_geometry, gen_geometry 
                            FROM oracle.r{$zone}_leakpoint ) lk 
                            ON ST_Intersects(lk.gen_geometry, pt.gen_geometry)
                        LEFT JOIN 
                        (SELECT pipe_id, c_leak, cost_repair, pwa_code, wkb_geometry
                        FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}' ) ps 
                            ON pt.pipe_id = ps.pipe_id";
    $Psumfreq_result = pg_exec($connection, $sqlPipesumfreq);
    $numrows = pg_numrows($Psumfreq_result);

    if (!$Psumfreq_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 6. Query สรุปตาม type/size พร้อม weight
    $sqlPipetypesizefreq_temp =  "SET CLIENT_ENCODING TO 'utf-8'; 
        SELECT CASE WHEN sum(pp.l_pipe)!=0 THEN ROUND(SUM(pp.c_leak)/sum(pp.l_pipe),2) ELSE 0 END avg_lk, 
            pp.pipe_type, pp.pipe_size, MAX(pp.pipe_age) age_max, MIN(pp.pipe_age) age_min,
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
            LEFT JOIN (SELECT * FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}') ps
            ON pt.pipe_id = ps.pipe_id  
            LEFT JOIN (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
            ON ps.pwa_code = rg.pwa_code 
            LEFT JOIN (SELECT leak_id, leak_no, pwa_code, wkb_geometry, gen_geometry FROM oracle.r{$zone}_leakpoint 
                WHERE pwa_code = '{$pwa_code}' ) lk  
            ON ST_Intersects(lk.gen_geometry, pt.gen_geometry)
            GROUP BY pipe_type, pipe_size,pipe_age, cost_repair, rg.r_score, dma_nrw
            ORDER BY pipe_type, pipe_size, pipe_age, cost_repair) pp 
        GROUP BY pp.pipe_type, pp.pipe_size, pp.w_age 
        ORDER BY pipe_type, pipe_size";
    $result_freq = pg_exec($connection, $sqlPipetypesizefreq_temp);
    $numrows = pg_numrows($result_freq);

    if (!$result_freq) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 7. Group weight summary
    $sqlGroupWeight_temp =  "SET CLIENT_ENCODING TO 'utf-8'; 
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
            LEFT JOIN (SELECT leak_id, leak_no, pwa_code, wkb_geometry, gen_geometry FROM oracle.r{$zone}_leakpoint WHERE pwa_code='{$pwa_code}' ) lk 
                ON ST_Intersects(lk.gen_geometry, pt.gen_geometry)
            ) sw";
    $sqlGroupWeight_temp_result = pg_exec($connection, $sqlGroupWeight_temp);
    $numrows = pg_numrows($sqlGroupWeight_temp_result);

    if (!$sqlGroupWeight_temp_result) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
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

    // 8. GeoJSON map
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
                        THEN ROUND(COUNT(DISTINCT l.leak_id)/sum(DISTINCT ST_Length(pt.wkb_geometry::geography)::INTEGER),2) ELSE 0 END avg_lk,
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
                        CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall pipe_age,
        {$w_age} w_age,
        {$w_age} * (CASE WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall < {$age_data['value_1']} THEN 1 
                                WHEN CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall >= {$age_data['value_1']}
                                AND CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall <= {$age_data['value_2']} THEN 2 
                                WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall >  {$age_data['value_2']} THEN 3 
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
                            (CASE WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall < {$age_data['value_1']} THEN 1 
                                WHEN CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall >= {$age_data['value_1']}
                                AND CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall <= {$age_data['value_2']} THEN 2 
                                WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall >  {$age_data['value_2']} THEN 3 
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
                ORDER BY pt.pipe_id ) As lg ) As f )  As fc";
    $resultmap = pg_exec($connection, $geoSQL);

    if (!$resultmap) {
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($connection));
        $data['geo_map'] = array();
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
        $data['status'] = 'true';
        $data['message'] = 'Complete get map and data from pipe target';
        $data['geo_map'] = $arr[0];
    }

    echo json_encode($data);
    exit();
}

// ฟังก์ชันสำหรับ handle pipe_proj_save_map
function handle_pipe_proj_save_map(
    $connection, $pwa_code, $zone, $pipe_id_form,
    $proj_fiscal, $proj_budget, $proj_name, $proj_remark, $proj_cost, &$data
) {
    // 1. แปลง pipe_id_form เป็น array (จาก JSON ที่รับมาจาก frontend)
    $arr_pipe = json_decode($pipe_id_form);

    // 2. หา project id ใหม่
    $c_proj = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT MAX(pid_no)+1 AS prs_id FROM dssnrw.ref_pipe_improve";
    $c_proj_result = pg_exec($connection, $c_proj);
    $numrows = pg_numrows($c_proj_result);
    if (!$c_proj_result) {
        $prs_id = 1;
    } else {
        $arr_c_proj = pg_fetch_array($c_proj_result, 0, PGSQL_NUM);
        $prs_id = $arr_c_proj[0];
    }

    // 3. เตรียม insert ข้อมูลแต่ละ pipe
    $pipe_id_list = '(';
    foreach ($arr_pipe as $key => $value) {
        $latlon = (array)json_encode($value->{'coordinates'});
        foreach ($latlon as $x) {
            $coor = '(';
            $y = explode('],', $x);
            foreach ($y as $val) {
                $str_pair = str_replace('[', '', $val);
                $str_pair = str_replace(']', '', $str_pair);
                $str_pair = str_replace(',', ' ', $str_pair);
                $coor .= $str_pair . ', ';
            }
        }
        $coor .= $str_pair . ')';
        $coor = str_replace(',)', ')', $coor);
        $pipe_id_list .= $key . ',';

        // สร้าง project_prov_id (ถ้ามีฟังก์ชัน project_prov_id ให้เรียกใช้)
        $project_prov_id = project_prov_id($proj_fiscal, $proj_budget, $pwa_code, $prs_id);

        // 4. Insert ข้อมูลลง pipe_improve
        $istSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                    INSERT INTO dssnrw.pipe_improve (
                        zone, pwa_code, pipe_id, fiscal_year, budget_type, project_prov_id, project_prov_name, 
                        pipe_long, wkb_geometry, gen_geometry, created_date, remark, proj_cost
                    ) VALUES (
                        {$zone}, '{$pwa_code}', {$key}, '{$proj_fiscal}', {$proj_budget}, {$project_prov_id}, '{$proj_name}', 
                        ST_Length(ST_Transform(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 32647)),
                        ST_GeomFromText('LINESTRING{$coor}', 4326 ),
                        ST_SnapToGrid(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 0.000001), 
                        now(), '{$proj_remark}', {$proj_cost}
                    ); COMMIT;";
        pg_exec($connection, $istSQL);
    }
    $pipe_id_list .= ')';
    $pipe_id_list = str_replace(',)', ')', $pipe_id_list);

    // 5. Insert ข้อมูลลง ref_pipe_improve (project summary)
    $istref_SQL  =  " SET CLIENT_ENCODING TO 'utf-8'; 
                    INSERT INTO dssnrw.ref_pipe_improve (
                        zone, pwa_code, fiscal_year, budget_type, project_prov_id,
                        project_prov_name, created_date, remark, proj_cost
                    ) VALUES (
                        {$zone}, '{$pwa_code}', '{$proj_fiscal}', {$proj_budget}, {$project_prov_id}, '{$proj_name}', 
                        now(), '{$proj_remark}', {$proj_cost}
                    );";
    pg_exec($connection, $istref_SQL);

    // 6. อัปเดตข้อมูลรายละเอียดท่อใน pipe_improve จากข้อมูลจริง
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
                            FROM (
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
                                LEFT JOIN (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                                    ON ps.pwa_code = rg.pwa_code 
                                WHERE ps.pwa_code = '{$pwa_code}' 
                                GROUP BY pm.pipe_id, pm.pwa_code, pp.pipe_type, pp.pipe_size, ps.pipe_age, lk.repaircost, rg.r_score, ps.dma_nrw,ps.dma_id, ps.dma_no, ps.dma_name, 
                                    pp.yearinstall, pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, 
                                    pp.product, pp.depth, pp.locate, pp.project_no, pp.pipe_long
                            ) pa
                            WHERE pm.pwa_code = pa.pwa_code AND pm.pipe_id = pa.pipe_id 
                                AND pm.pipe_id IN $pipe_id_list ; COMMIT;";
    pg_exec($connection, $update_typesize);

    // 7. ส่งผลลัพธ์กลับ
    $data['status'] = 'true';
    $data['message'] = 'Complete for save pipe projects.';
    echo json_encode($data);
    exit();
}
