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

$zone = $arrReg[substr($_REQUEST['pwa_code'], 0, 4)];
$act = $_REQUEST['act'];
$pwa_code = $_REQUEST['pwa_code'];
$pipe_id_form = $_REQUEST['pipe_id_form'];

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
$fiscal_year = $_POST['fiscal_year'];
$budget_type = $_POST['budget_type'];
$project_prov_name = $_POST['project_prov_name'];
$remark = $_POST['remark'];

$data = array();
$data['status'] = 'true';
$data['message'] = 'start API';

//----------------- processing zone --------------------------------------->>

// get pipe summary data 
$data['pipeall_data'] = pipe_summary($zone, $pwa_code);

// get age data 
$data['age_data'] = $age_data;

//get leak data value1 - value2 
$data['leak_data'] = get_leak_summary($pwa_code);

//get repair cost value1 - value2 
$data['cost_data'] = get_repair_summary($pwa_code);


if ($act == 'pipe_detail_map') {

    $data = array();

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
                                MAX(pp.ptype) ptype, MAX(pp.pressure) pressure, 
                                FROM 
                            (SELECT CASE WHEN sum(pipe_long) != 0 THEN ROUND(SUM(c_leak)/sum(pipe_long),2) ELSE 0 END avg_lk,
                            pipe_type, pipe_size, pipe_age,
                            COUNT(pipe_id) as c_pipe, sum(pipe_long) AS l_pipe, 
                            SUM(c_leak) as c_leak,  0 ptype, 0 pressure,   
                            cost_repair, dma_nrw
                            FROM dssnrw.pipe_summary ps 
                            LEFT JOIN 
                            (SELECT pwa_code, r_score FROM dssnrw.pwa_risk_group) rg
                            ON ps.pwa_code = rg.pwa_code 
                            WHERE ps.pwa_code = '{$pwa_code}' AND ps.pipe_id IN ({$pipe_id_form}) 
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
                'lr' => $row['lr'],
                'long_lr' => $row['long_lr'],
                'mr' => $row['mr'],
                'long_mr' => $row['long_mr'],
                'hr' => $row['hr'],
                'long_hr' => $row['long_hr'],
            ));
        }

        $data['pid_typesize_freq'] = $pw_typesize;
    }

    $y = strval($pipe_id_form);
    $pipe_chk = explode(",", $y);

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

    $strSQL = "SET CLIENT_ENCODING TO 'utf-8';
            SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
            FROM (SELECT 'Feature' As type 
            , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
            , row_to_json((SELECT l FROM (SELECT pipe_id, project_no, pipe_type, pipe_size, 
                    yearinstall, pipe_long, contrac_date, asset_code, pipe_func, laying, 
                    product, depth, locate) As l 
            )) As properties 
            FROM (select pp.pipe_id, pp.project_no, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.contrac_date, 
                    CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                    pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.wkb_geometry 
                    FROM oracle.r{$zone}_pipe pp 
                    WHERE pp.pwa_code = '{$pwa_code}' 
                    AND pp.pipe_id IN ({$pipe_id_form})
                    AND pp.wkb_geometry IS NOT NULL
                    ORDER BY pp.pipe_id ) As lg) As f )  As fc ";



    $resultmap = pg_exec($connection, $strSQL);

    if (!$resultmap) {
        $data['geomap'] = array();
        //echo pg_last_error($db);
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
    }
    header('Content-Type: application/json; charset=utf-8');
    $data['geomap'] = $arr[0];
    //echo $arr[0];  
    echo json_encode($data);
    pg_close($connection);
    exit();
}

 else if ($act == 'pipe_detail_freq_map') {

    $strSQL = "SET CLIENT_ENCODING TO 'utf-8';
                SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                FROM (SELECT 'Feature' As type 
                , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                , row_to_json((SELECT l FROM (SELECT pipe_id, avg_lk, c_leak, cost_repair, 
                                pipe_type, pipe_size, yearinstall, project_no, pipe_long, yearinstall, pipe_long, 
                                contrac_date, asset_code, pipe_func, laying, product, depth, locate) As l 
                )) As properties 
                FROM (SELECT pp.pipe_id, 
                                CASE WHEN sum(pp.pipe_long) != 0 THEN ROUND(COUNT(l.wkb_geometry)/sum(pp.pipe_long),6) ELSE 0 END avg_lk,
                                COUNT(l.leak_id) as c_leak,
                                SUM(l.repaircost) cost_repair,
                                pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, 
                                CASE WHEN pp.project_no IS NULL THEN '' ELSE pp.project_no END project_no, 
                                pp.contrac_date, 
                                CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                                pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.wkb_geometry 
                        FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.wkb_geometry, pp.wkb_geometry)
                        WHERE pp.pwa_code = '{$pwa_code}' AND pp.wkb_geometry IS NOT NULL
                        AND pp.pipe_id IN ({$pipe_id_form})
                        GROUP BY pp.pipe_id, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long,
                                pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                                pp.depth, pp.locate, pp.project_no, pp.wkb_geometry
                        ORDER BY pp.pipe_id ) As lg ) As f )  As fc	";

    $arr_pipe = json_decode($pipe_id_form);

    $delSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                DELETE FROM dssnrw.p_temp; COMMIT; ";

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
                    INSERT INTO dssnrw.p_temp (pipe_id, pwa_code, wkb_geometry) 
                    VALUES ({$key}, '{$pwa_code}', ST_GeomFromText('LINESTRING{$coor}', 4326 ) ); COMMIT; ";
        //echo "$istSQL\n";
        pg_exec($connection, $istSQL);
        //exit();  
    }
    $pipe_id_list .= ')';
    $pipe_id_list = str_replace(',)', ')', $pipe_id_list);

    $gen_geom = " SET CLIENT_ENCODING TO 'utf-8'; 
                    ALTER TABLE dssnrw.p_temp
                    DROP COLUMN IF EXISTS gen_geometry; COMMIT;
                    DROP INDEX IF EXISTS p_temp_gengeometry_idx; COMMIT;
                    SELECT 
                    AddGeometryColumn ('dssnrw','p_temp','gen_geometry',4326,'GEOMETRY',2); COMMIT;
                    CREATE INDEX p_temp_gengeometry_idx ON dssnrw.p_temp USING gist (gen_geometry); COMMIT;
                    UPDATE dssnrw.p_temp SET gen_geometry = ST_SnapToGrid(wkb_geometry, 0.000001); COMMIT; ";

    pg_exec($connection, $gen_geom);

    $geoSQL = "SET CLIENT_ENCODING TO 'utf-8'; 
            SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
            FROM (SELECT 'Feature' As type 
            , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
            , row_to_json((SELECT l FROM (SELECT pipe_id, avg_lk, c_leak, w_leak, c_leak_ww,
                            cost_repair, w_cost, cost_repair_ww, pipe_type, pipe_size, pipe_age, 
                            w_age, pipe_age_ww, elev, w_elev, elev_ww, ptype, w_ptype, ptype_ww,
                            pressure, w_pressure, pressure_ww, w_dma, dma_ww, 
                            sum_ww, pipe_long, project_no, contrac_date,   
                            asset_code, pipe_func, laying, product, depth, locate) As l 
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
                                ELSE 0 END) cost_repair_ww, 
                            pp.pipe_type, pp.pipe_size, 
                            CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall pipe_age,
        {$w_age} w_age,
        {$w_age} * 
                            (CASE WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall < {$age_data['value_1']} THEN 1 
                                WHEN CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall >= {$age_data['value_1']}
                                AND CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall <= {$age_data['value_2']} THEN 2 
                                WHEN  CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
        1) AS INTEGER) + 543 - pp.yearinstall >  {$age_data['value_2']} THEN 3 
                                ELSE 0 END) pipe_age_ww,
        0 elev,
        0 w_elev,
        0 elev_ww,
        0 ptype,
        0 w_ptype,
        0 ptype_ww,
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
                                ELSE 0 END)) 
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
                                + ({$w_dma} * (CASE WHEN dma_nrw <= 30 THEN 1 
                                            WHEN dma_nrw > 30 AND dma_nrw <= 40 THEN 2
                                            WHEN dma_nrw > 40 THEN 3 ELSE 0 END)) ) sum_ww, 
                            ST_Length(pt.wkb_geometry::geography)::INTEGER pipe_long, 
                            CASE WHEN pp.project_no IS NULL THEN '' ELSE pp.project_no END project_no, 
                            pp.contrac_date, 
                            CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                            pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pt.wkb_geometry 
                    FROM dssnrw.p_temp pt LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.gen_geometry, pt.gen_geometry)
                    LEFT JOIN oracle.r{$zone}_pipe pp ON pt.pipe_id = pp.pipe_id AND pt.pwa_code = pp.pwa_code 
                    LEFT JOIN dssnrw.pipe_summary ps ON pp.pwa_code = ps.pwa_code AND pp.pipe_id = ps.pipe_id 
                    GROUP BY pt.pipe_id, pp.pipe_type, pp.pipe_size, pp.yearinstall,
                            pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                            pp.depth, pp.locate, pp.project_no, dma_nrw 
                    ORDER BY pt.pipe_id ) As lg ) As f )  As fc  ";

    //echo $geoSQL;
    //exit() ;

    $resultmap = pg_exec($connection, $geoSQL);

    if (!$resultmap) {
        echo json_encode(pg_last_error($db));
    } else {
        $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
    }
    echo $arr[0];
    exit();
    
} else if ($act == 'pipe_proj_save_map') {

    $data = array();
    //encode array from front 
    $arr_pipe = json_decode($pipe_id_form);

    //state for insert project name and id 
    $c_proj = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT COUNT(*) + 1 AS prs_id FROM dssnrw.ref_pipe_improve 
                WHERE fiscal_year = '{$fiscal_year}'  ";

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

        $istSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                    INSERT INTO dssnrw.pipe_improve (zone, pwa_code, pipe_id, fiscal_year, budget_type, project_prov_name, 
                        pipe_long, wkb_geometry, gen_geometry, created_date, remark) 
                    VALUES ({$zone}, '{$pwa_code}', {$key}, '{$fiscal_year}', {$budget_type}, '{$project_prov_name}', 
                        ST_Length(ST_Transform(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 32647)),
                        ST_GeomFromText('LINESTRING{$coor}', 4326 ),
                        ST_SnapToGrid(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 0.000001), 
                        now(), '{$remark}' ); COMMIT; ";

        //echo "$istSQL\n";
        pg_exec($connection, $istSQL);
    }
    //exit(); 
    $pipe_id_list .= ')';
    $pipe_id_list = str_replace(',)', ')', $pipe_id_list);

    $update_typesize = " SET CLIENT_ENCODING TO 'utf-8'; 
                        UPDATE dssnrw.pipe_improve ds
                        SET pipe_type = pp.pipe_type,
                            pipe_size = pp.pipe_size 
                            FROM oracle.r{$zone}_pipe pp
                            WHERE ds.pwa_code = pp.pwa_code 
                                AND ds.pipe_id = pp.pipe_id
                                AND pp.pwa_code = '{$pwa_code}' 
                                AND pp.pipe_id IN $pipe_id_list ; COMMIT; ";

    pg_exec($connection, $update_typesize);

    $data['status'] = 'true';
    $data['message'] = 'Complete for save pipe projects.';
    echo json_encode($data);
    exit();
}

echo json_encode($data);
