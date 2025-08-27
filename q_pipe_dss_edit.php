<?php 

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json'); 
error_reporting(~E_NOTICE);
@ini_set('display_errors', '0'); //ไม่แสดงerror
include 'connect.php';
//if ($_SERVER['REQUEST_METHOD'] === "REQUEST") { check session 

    $arrReg = array("5511"=>"9", "5512"=>"10", "5521"=>"6", "5522"=>"7", "5531"=>"1", "5532"=>"8", "5541"=>"2", "5542"=>"3", "5551"=>"4", "5552"=>"5");
	$zone = $arrReg[substr($_POST['pwa_code'],0,4)];
    $act=$_POST['act'];
    $pwa_code= $_POST['pwa_code'];
    $pipe_id_form = $_POST['pipe_id_form'];

    //input weight from user 
    $w_age = $_POST['w_age'];
    $w_leak = $_POST['w_leak'];
    $w_cost = $_POST['w_cost'];
    $w_elev = $_POST['w_elev'];
    $w_ptype = $_POST['w_ptype'];
    $w_pressure = $_POST['w_pressure'];

    //input for pipe project save 
    $fiscal_year = $_POST['fiscal_year'];
    $budget_type = $_POST['budget_type'];
    $project_prov_name = $_POST['project_prov_name'];
    $remark = $_POST['remark'];
    //$project_prov_name =iconv( "utf-8", "windows-874",$_POST['project_prov_name']);
    //$remark =iconv( "utf-8", "windows-874",$_POST['remark']);

    if ($act!='pipe_all') {
        //test array from input 
    $pipe_id_test = array (
        1 => array (
        "coordinates" => array (
            [100.751418936209, 14.6015288138665],
            [100.750762770071, 14.6016535557884],
            [100.750568026199, 14.6016853779692],
            [100.749774159075, 14.6018264211756]
        ),
        "pipe_size" => 100
        )
    );
    $pipe_id_test = (object)$pipe_id_test;

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
$strLeak = "SELECT MAX(pp.c_leak) max, 
            MIN(pp.c_leak) min, 
            ROUND(STDDEV(pp.c_leak), 2) AS sd, 
            ROUND(AVG(pp.c_leak), 2) AS mean,  
                    CASE WHEN CAST(AVG(pp.c_leak) + (0*STDDEV(pp.c_leak)) AS INTEGER) <= 0 THEN 1 
                        ELSE CAST(AVG(pp.c_leak) + (0*STDDEV(pp.c_leak)) AS INTEGER) END value_1, 
                    CAST(AVG(pp.c_leak) + (1*STDDEV(pp.c_leak)) AS INTEGER) value_2 FROM 
            (SELECT DISTINCT pp.pipe_id, 
                                    COUNT(l.gen_geometry) AS c_leak
                            FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l 
                            ON ST_Intersects(l.gen_geometry, pp.gen_geometry) 
                            WHERE pp.pwa_code = '{$pwa_code}' 
                            group by pp.pipe_id ) pp ";

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
$strCost = "SELECT MAX(pp.cost_repair) max, 
            MIN(pp.cost_repair) min, 
            ROUND(STDDEV(pp.cost_repair), 2) AS sd, 
            ROUND(AVG(pp.cost_repair), 2) mean,  
            CASE WHEN CAST(AVG(pp.cost_repair) + (0*STDDEV(pp.cost_repair)) AS INTEGER) <= 0 THEN 1  
                ELSE CAST(AVG(pp.cost_repair) + (0*STDDEV(pp.cost_repair)) AS INTEGER) END value_1, 
            CAST(AVG(pp.cost_repair) + (1*STDDEV(pp.cost_repair)) AS INTEGER) value_2 FROM 
            (SELECT DISTINCT pp.pipe_id, 
                                    sum(CASE WHEN repaircost IS NULL THEN 0 ELSE repaircost END) cost_repair 
                            FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l 
                            ON ST_Intersects(l.gen_geometry, pp.gen_geometry) 
                            WHERE pp.pwa_code = '{$pwa_code}' 
                            group by pp.pipe_id ) pp ";

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
    }
    
    ///echo json_encode($data);
    if ($act=='pipe_all') {

        $sql = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT * FROM dssnrw.data_summary 
                WHERE zone = '{$zone}' AND pwa_code = '{$pwa_code}' " ; 

        $result = pg_exec($connection, $sql);

        $numrows = pg_numrows($result);
        //echo $numrows;
        $data = array();
            
        if(!$result){
            echo json_encode(pg_last_error($db));
            exit();
            } else {
            for($ri = 0; $ri < $numrows; $ri++) {
                $row = pg_fetch_array($result, $ri);
                array_push($data, array(
                'update_data'=>$row['update_data'], 
                'c_pipe'=>$row['c_pipe'], 
                'l_pipe'=>$row['l_pipe'],
                'c_mt'=>$row['c_mt'],
                'c_valve'=>$row['c_valve'],
                'c_fire'=>$row['c_fire'],
                'c_leak'=>$row['c_leak'],
                ));
            }
        }
        echo json_encode($data);
        exit() ;
    
    }

    if ($act == 'f_weight') {
        $data = array() ;
        //conclusion in branch with freq and weight
        $sqlPipesumfreq = " SET CLIENT_ENCODING TO 'utf-8'; 
                                SELECT  COUNT(*) AS c_pipe, sum(pipe_long) AS l_pipe, 
                                SUM(c_leak) AS c_leak, sum(cost_repair) as cost_repair, 
                                ROUND(SUM(c_leak)/sum(DISTINCT pipe_long),2) AS avg_lk
                            FROM dssnrw.pipe_summary 
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
                    'cost_repair' => $row['cost_repair'],
                    'avg_lk' => $row['avg_lk'],
                );
            }
        }
    
        //conclusion with weight pipe type size freq 
        $sqlPipetypesizefreq =  " SET CLIENT_ENCODING TO 'utf-8'; 
                                    SELECT CASE WHEN sum(DISTINCT pp.pipe_long) != 0 THEN ROUND(COUNT(l.gen_geometry)/sum(DISTINCT pp.pipe_long),2) ELSE 0 END avg_lk,
                                    pp.pipe_type, pp.pipe_size, 
                                    MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',1) AS INTEGER) + 543 - pp.yearinstall) pipe_age,
                                    {$w_age} w_age, {$w_age} * (CASE WHEN  MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) < {$age_data['value_1']} THEN 1 
                                                            WHEN MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) >= {$age_data['value_1']}
                                                            AND MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) <= {$age_data['value_2']} THEN 2 
                                                            WHEN  MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) >  {$age_data['value_2']} THEN 3 
                                                            ELSE 0 END) pipe_age_ww, 
                                    COUNT(DISTINCT pp.pipe_id) as c_pipe, sum(DISTINCT pp.pipe_long) AS l_pipe, 
                                    COUNT(l.leak_id) as c_leak, {$w_leak} AS w_leak, 
                                    {$w_leak} * (CASE WHEN CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) < {$leak_data['value_1']} THEN 1 
                                        WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) >= {$leak_data['value_1']}
                                                AND CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) <= {$leak_data['value_2']} THEN 2
                                        WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                                    CASE WHEN sum(l.repaircost) IS NOT NULL THEN sum(l.repaircost) ELSE 0 END cost_repair, 
                                    {$w_cost} w_cost, 
                                    {$w_cost} * (CASE WHEN (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) < {$cost_data['value_1']} THEN 1
                                        WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) >= {$cost_data['value_1']} 
                                        AND  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) <= {$cost_data['value_2']} THEN 2
                                        WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) > {$cost_data['value_2']} THEN 3 
                                        ELSE 0 END) cost_repair_ww, 
                                    0 elev, 0 w_elev, 0 elev_ww, 0 ptype, 0 w_ptype, 0 ptype_ww, 0 pressure, 0 w_pressure, 0 pressure_ww,
                                    $w_dma w_dma, 
                                    ({$w_leak} * (CASE WHEN CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) < {$leak_data['value_1']} THEN 1 
                                    WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) >= {$leak_data['value_1']}
                                            AND CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) <= {$leak_data['value_2']} THEN 2
                                    WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) > {$leak_data['value_2']} THEN 3 END)) 
                                    + ({$w_cost} * (CASE WHEN (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) < {$cost_data['value_1']} THEN 1
                                    WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) >= {$cost_data['value_1']} 
                                    AND  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) <= {$cost_data['value_2']} THEN 2
                                    WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) > {$cost_data['value_2']} THEN 3 
                                    ELSE 0 END)) 
                                    + ({$w_age} * (CASE WHEN  MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) < {$age_data['value_1']} THEN 1 
                                                            WHEN MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) >= {$age_data['value_1']}
                                                            AND MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) <= {$age_data['value_2']} THEN 2 
                                                            WHEN  MAX(CAST(SPLIT_PART(CURRENT_DATE::TEXT,'-',
                                    1) AS INTEGER) + 543 - pp.yearinstall) >  {$age_data['value_2']} THEN 3 
                                                            ELSE 0 END)) sum_ww
                                    FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.gen_geometry, pp.gen_geometry) 
                                        LEFT JOIN (SELECT * FROM pwa_dma.dma_boundary WHERE pwa_code = '{$pwa_code}' ) dm
                                        ON ST_Intersects(pp.wkb_geometry, dm.wkb_geometry)   
                                    WHERE pp.pwa_code = '{$pwa_code}' 
                                    GROUP BY pp.pipe_type, pp.pipe_size, pp.yearinstall, l.repaircost
                                    ORDER BY pp.pipe_type, pp.pipe_size " ;
    
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
                    'pipe_age' => $row['pipe_age'],
                    'w_age' => $row['w_age'],
                    'pipe_age_ww' => $row['pipe_age_ww'],
                    'c_pipe' => $row['c_pipe'],
                    'l_pipe' => $row['l_pipe'],
                    'c_leak' => $row['c_leak'],
                    'w_leak' => $w_leak,
                    'c_leak_ww' =>$row['c_leak_ww'],
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
                    'sum_ww' => $row['sum_ww'],
                ));
            }
    
            $data['pw_typesize_freq'] = $pw_typesize;
        }
    
        //echo ' value_1 : '.$age_data['value_1']. 'and value_2 : '.$age_data['value_2'];
        //exit();
    
        //set data for geojson 
        $strPipetyesizefreq_map = " SET CLIENT_ENCODING TO 'utf-8';
                                    SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                                    FROM (SELECT 'Feature' As type 
                                    , ST_AsGeoJSON(lg.gen_geometry)::json As geometry 
                                    , row_to_json((SELECT l FROM (SELECT pipe_id, avg_lk, c_leak, w_leak, c_leak_ww,
                                                    cost_repair, w_cost, cost_repair_ww, pipe_type, pipe_size, pipe_age, 
                                                    w_age, pipe_age_ww, elev, w_elev, elev_ww, ptype, w_ptype, ptype_ww,
                                                    pressure, w_pressure, pressure_ww, sum_ww, pipe_long, project_no, contrac_date,   
                                                    asset_code, pipe_func, laying, product, depth, locate) As l 
                                    )) As properties 
                                    FROM (SELECT DISTINCT pp.pipe_id, 
                                                    CASE WHEN sum(DISTINCT pp.pipe_long) != 0 THEN ROUND(COUNT(l.gen_geometry)/sum(DISTINCT pp.pipe_long),
                                2) ELSE 0 END avg_lk,
                                                    COUNT(DISTINCT l.leak_id) as c_leak,
                                {$w_leak} w_leak,
                                {$w_leak} * 
                                                    (CASE WHEN CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) < {$leak_data['value_1']} THEN 1 
                                                        WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) >= {$leak_data['value_1']}
                                                                AND CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) <= {$leak_data['value_2']} THEN 2
                                                        WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) > {$leak_data['value_2']} THEN 3 END) c_leak_ww, 
                                                    CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END cost_repair,
                                {$w_cost} w_cost,
                                {$w_cost} * 
                                                    (CASE WHEN (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) < {$cost_data['value_1']} THEN 1
                                                        WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) >= {$cost_data['value_1']} 
                                                        AND  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) <= {$cost_data['value_2']} THEN 2
                                                        WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) > {$cost_data['value_2']} THEN 3 
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
                                                    ({$w_leak} * 
                                                    (CASE WHEN CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) < {$leak_data['value_1']} THEN 1 
                                                        WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) >= {$leak_data['value_1']}
                                                                AND CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) <= {$leak_data['value_2']} THEN 2
                                                        WHEN  CAST(COUNT(DISTINCT l.leak_id) AS INTEGER) > {$leak_data['value_2']} THEN 3 END )
                                                        + ({$w_cost} * 
                                                        (CASE WHEN (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) < {$cost_data['value_1']} THEN 1
                                                        WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) >= {$cost_data['value_1']} 
                                                        AND  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) <= {$cost_data['value_2']} THEN 2
                                                        WHEN  (CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END) > {$cost_data['value_2']} THEN 3 
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
                                                            ELSE 0 END) ) ) sum_ww, 
                                                    pp.pipe_long, 
                                                    CASE WHEN pp.project_no IS NULL THEN '' ELSE pp.project_no END project_no, 
                                                    pp.contrac_date, 
                                                    CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                                                    pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pp.gen_geometry 
                                            FROM oracle.r1_pipe pp LEFT JOIN oracle.r1_leakpoint l 
                                            ON ST_Intersects(l.gen_geometry, pp.gen_geometry) 
                                            WHERE pp.pwa_code = '5531027' AND pp.gen_geometry IS NOT NULL
                                            GROUP BY pp.pipe_id, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long,
                                                    pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                                                    pp.depth, pp.locate, pp.project_no, pp.gen_geometry, l.repaircost
                                            ORDER BY pp.pipe_id ) As lg ) As f )  As fc  ";
        //echo $strPipetyesizefreq_map;
        //exit();
    
    $Pipetyesizefreq_map_result = pg_exec($connection, $strPipetyesizefreq_map);
    
    if(!$Pipetyesizefreq_map_result){
        $data['status'] = 'false';
        $data['message'] = json_encode(pg_last_error($db));
        $data['pw_typesize_freq_map'] = array();
        } else {
            $data['status'] = 'true';
            $data['message'] = 'complete for create geojson in pipetypesize frequency';
            $arr = pg_fetch_array($Pipetyesizefreq_map_result, 0, PGSQL_NUM);
            }
            $data['pw_typesize_freq_map'] = escapeJsonString($arr[0]) ;  
    
    }
    
    else if ($act == 'pipe_sum') {

        $sql = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT COUNT(DISTINCT pp.pipe_id) as c_pipe, sum(DISTINCT pp.pipe_long) AS l_pipe, 
                COUNT(l.wkb_geometry) as c_leak,sum(DISTINCT l.repaircost) as cost_repair 
                FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.wkb_geometry,pp.wkb_geometry) 
                WHERE pp.pwa_code = '{$pwa_code}' AND 
                pp.pipe_id in ({$pipe_id_form})  ";
    }

    else if ($act == 'pipe_typesize') {

        $sql = " SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT pp.pipe_type, pp.pipe_size, COUNT(DISTINCT pp.pipe_id) as c_pipe, sum(DISTINCT pp.pipe_long) AS l_pipe, 
                COUNT(l.leak_id) as c_leak,sum(DISTINCT l.repaircost) as cost_repair 
                FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.wkb_geometry, pp.wkb_geometry)
                WHERE pp.pwa_code = '{$pwa_code}' AND 
                pp.pipe_id in ({$pipe_id_form})
                GROUP BY pp.pipe_type, pp.pipe_size  
                ORDER BY pp.pipe_type, pp.pipe_size ";
    }

    else if ($act == 'pipe_detail') {

        $sql = "   SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT pp.pipe_id, pp.project_no, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.contrac_date, 
                    pp.asset_code, pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate
                    FROM oracle.r{$zone}_pipe pp 
                    WHERE pp.pwa_code = '{$pwa_code}' AND 
                    pp.pipe_id IN ({$pipe_id_form})
                    ORDER BY pp.pipe_id ";
    }

    else if ($act == 'pipe_detail_map') {

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

        if(!$resultmap){
            echo pg_last_error($db);
            } else {
            $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
            }
            header('Content-Type: application/json; charset=utf-8');
            echo $arr[0];          
            pg_close($connection);  
            exit();  
    }

    else if ($act == 'pipe_sum_freq') {

        $sql = " SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT COUNT(DISTINCT pp.pipe_id) as c_pipe, sum(DISTINCT pp.pipe_long) AS l_pipe, 
                    COUNT(l.wkb_geometry) as c_leak,sum(DISTINCT l.repaircost) as cost_repair, 
                    CASE WHEN sum(DISTINCT pp.pipe_long) != 0 THEN ROUND(COUNT(l.wkb_geometry)/sum(DISTINCT pp.pipe_long),6) ELSE 0 END avg_lk
                    FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.wkb_geometry,pp.wkb_geometry) 
                    WHERE pp.pwa_code = '{$pwa_code}' AND 
                    pp.pipe_id in ({$pipe_id_form})  ";
    }

    else if ($act == 'pipe_typesize_freq') {

        $sql = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT CASE WHEN sum(DISTINCT pp.pipe_long) != 0 THEN ROUND(COUNT(l.wkb_geometry)/sum(DISTINCT pp.pipe_long),6) ELSE 0 END avg_lk,
                pp.pipe_type, pp.pipe_size, COUNT(DISTINCT pp.pipe_id) as c_pipe, sum(DISTINCT pp.pipe_long) AS l_pipe, 
                COUNT(l.leak_id) as c_leak,sum(DISTINCT l.repaircost) as cost_repair 
                FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.wkb_geometry, pp.wkb_geometry)
                WHERE pp.pwa_code = '{$pwa_code}' AND 
                pp.pipe_id in ({$pipe_id_form})
                GROUP BY pp.pipe_type, pp.pipe_size
                ORDER BY pp.pipe_type, pp.pipe_size  ";
    }

    else if ($act == 'pipe_detail_freq') {

        $sql = " SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT pp.pipe_id, 
                CASE WHEN sum(pp.pipe_long) != 0 THEN ROUND(COUNT(l.wkb_geometry)/sum(pp.pipe_long),6) ELSE 0 END avg_lk,
                COUNT(l.leak_id) as c_leak,
                CASE WHEN l.repaircost IS NULL THEN 0 ELSE l.repaircost END cost_repair,
                pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, 
                CASE WHEN pp.project_no IS NULL THEN '' ELSE pp.project_no END project_no, 
                pp.contrac_date, 
                CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate
                FROM oracle.r{$zone}_pipe pp LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.wkb_geometry, pp.wkb_geometry)
                WHERE pp.pwa_code = '{$pwa_code}' AND 
                pp.pipe_id in ({$pipe_id_form}) 
                GROUP BY pp.pipe_id, pp.pipe_type, pp.pipe_size, pp.yearinstall, pp.pipe_long, pp.project_no,
                pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, 
                l.repaircost
                ORDER BY pp.pipe_id, pp.pipe_type, pp.pipe_size ";
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

        /* $i = 0;

        while($i < count($pipe_id_form))
        {
            echo json_encode($pipe_id_form->{1}) ;
            $i++;
        } */

        //$pipe_id_form = $pipe_id_form ;
        //var_dump($pipe_id_form);
        //echo $pipe_id_form;
        //exit(); 

        $arr_pipe = json_decode($pipe_id_form);

        $delSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                    DELETE FROM dssnrw.p_temp; COMMIT; " ;

        pg_exec($connection, $delSQL);  

        $pipe_id_list = '(' ; 

        foreach ($arr_pipe as $key=>$value) {

            $latlon = (array)json_encode($value->{'coordinates'});
            
            
            foreach ($latlon as $x) {
                $coor = '(';
                //var_dump($x);
                $y = explode('],',$x);
                //var_dump($y);
                foreach ($y as $val) {
                    $str_pair = str_replace('[','', $val);
                    $str_pair = str_replace(']','',$str_pair);
                    $str_pair = str_replace(',',' ',$str_pair);
                    //echo "$str_pair\n";
                    $coor.=$str_pair.', ';
                      
                }
                
            }
            $coor.=$str_pair.')';
            $coor = str_replace(',)',')', $coor); 
            $pipe_id_list.=$key.',' ;

            $istSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                        INSERT INTO dssnrw.p_temp (pipe_id, pwa_code, wkb_geometry) 
                        VALUES ({$key}, '{$pwa_code}', ST_GeomFromText('LINESTRING{$coor}', 4326 ) ); COMMIT; ";
            //echo "$istSQL\n";
            pg_exec($connection, $istSQL);
            //exit();  
        }
        $pipe_id_list.=')' ;
        $pipe_id_list = str_replace(',)',')', $pipe_id_list); 
        
        $gen_geom = " SET CLIENT_ENCODING TO 'utf-8'; 
                        ALTER TABLE dssnrw.p_temp
                        DROP COLUMN IF EXISTS gen_geometry; COMMIT;
                        DROP INDEX IF EXISTS p_temp_gengeometry_idx; COMMIT;
                        SELECT 
                        AddGeometryColumn ('dssnrw','p_temp','gen_geometry',4326,'GEOMETRY',2); COMMIT;
                        CREATE INDEX p_temp_gengeometry_idx ON dssnrw.p_temp USING gist (gen_geometry); COMMIT;
                        UPDATE dssnrw.p_temp SET gen_geometry = ST_SnapToGrid(wkb_geometry, 0.000001); COMMIT; " ;  

        pg_exec($connection, $gen_geom);  
        
        $geoSQL = "SET CLIENT_ENCODING TO 'utf-8'; 
                SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                FROM (SELECT 'Feature' As type 
                , ST_AsGeoJSON(lg.wkb_geometry)::json As geometry 
                , row_to_json((SELECT l FROM (SELECT pipe_id, avg_lk, c_leak, w_leak, c_leak_ww,
                                cost_repair, w_cost, cost_repair_ww, pipe_type, pipe_size, pipe_age, 
                                w_age, pipe_age_ww, elev, w_elev, elev_ww, ptype, w_ptype, ptype_ww,
                                pressure, w_pressure, pressure_ww, sum_ww, pipe_long, project_no, contrac_date,   
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
                                        ELSE 0 END) ) ) sum_ww, 
                                ST_Length(pt.wkb_geometry::geography)::INTEGER pipe_long, 
                                CASE WHEN pp.project_no IS NULL THEN '' ELSE pp.project_no END project_no, 
                                pp.contrac_date, 
                                CASE WHEN pp.asset_code IS NULL THEN '' ELSE pp.asset_code END asset_code, 
                                pp.pipe_func, pp.laying, pp.product, pp.depth, pp.locate, pt.wkb_geometry 
                        FROM dssnrw.p_temp pt LEFT JOIN oracle.r{$zone}_leakpoint l ON ST_Intersects(l.gen_geometry, pt.gen_geometry)
                        LEFT JOIN oracle.r{$zone}_pipe pp ON pt.pipe_id = pp.pipe_id AND pt.pwa_code = pp.pwa_code
                        GROUP BY pt.pipe_id, pp.pipe_type, pp.pipe_size, pp.yearinstall,
                                pp.contrac_date, pp.asset_code, pp.pipe_func, pp.laying, pp.product, 
                                pp.depth, pp.locate, pp.project_no
                        ORDER BY pt.pipe_id ) As lg ) As f )  As fc  ";
    
        //echo $geoSQL;
        //exit() ;
        /* $geoSQL = " SET CLIENT_ENCODING TO 'utf-8'; 
                    SELECT row_to_json(fc) FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features 
                    FROM (SELECT 'Feature' As type 
                    , ST_AsGeoJSON(lg.gen_geometry)::json As geometry 
                    , row_to_json((SELECT l FROM (SELECT pipe_id, c_leak) As l 
                    )) As properties 
                    FROM
                    (SELECT pt.pipe_id, COUNT(DISTINCT l.leak_id) c_leak, pt.gen_geometry FROM dssnrw.p_temp pt
                    LEFT JOIN oracle.r2_leakpoint l 
                    ON ST_Intersects(l.gen_geometry, pt.gen_geometry)
                    LEFT JOIN oracle.r2_pipe pp
                    ON pt.pipe_id = pp.pipe_id 
                    WHERE l.pwa_code = '5541014' AND pp.pwa_code = '5541014'
                    GROUP BY pt.pipe_id  ) AS lg) AS f) AS fc "; */

        $resultmap = pg_exec($connection, $geoSQL); 

        if(!$resultmap){
            echo json_encode(pg_last_error($db));
            } else {
            $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
            }
            echo $arr[0];          
            //pg_close($connection);
            exit();
            // header('Content-Type: application/json; charset=utf-8');
            /*foreach ($arr as $x) {
                echo "{$x} <br>";
              } */
           
            /* echo 'uhhhhhhhhh'; 
            var_dump($arr[0]);
            exit(); 
            echo $arr[0];          
            pg_close($connection);  */
            
        //echo $coor; 
        
        //echo json_encode($pipe_id_test->{1}) ;
        //exit() ;

        /* $resultmap = pg_exec($connection, $strSQL);

        if(!$resultmap){
            echo pg_last_error($db);
            } else {
            $arr = pg_fetch_array($resultmap, 0, PGSQL_NUM);
            }
            /*header('Content-Type: application/json; charset=utf-8');
            foreach ($arr as $x) {
                echo "{$x} <br>";
              } 
            //exit();
            echo $arr[0];          
            pg_close($connection);  
            exit(); */
    }

    else if ($act == 'pipe_proj_save_map') {

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

        $pipe_id_list = '(' ; 

        foreach ($arr_pipe as $key=>$value) {

            $latlon = (array)json_encode($value->{'coordinates'});
                        
            foreach ($latlon as $x) {
                $coor = '(';
                //var_dump($x);
                $y = explode('],',$x);
                //var_dump($y);
                foreach ($y as $val) {
                    $str_pair = str_replace('[','', $val);
                    $str_pair = str_replace(']','',$str_pair);
                    $str_pair = str_replace(',',' ',$str_pair);
                    //echo "$str_pair\n";
                    $coor.=$str_pair.', ';
                      
                }
                
            }
            $coor.=$str_pair.')';
            $coor = str_replace(',)',')', $coor); 
            $pipe_id_list.=$key.',' ;

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
        $pipe_id_list.=')' ;
        $pipe_id_list = str_replace(',)',')', $pipe_id_list); 
        
        $update_typesize = " SET CLIENT_ENCODING TO 'utf-8'; 
                            UPDATE dssnrw.pipe_improve ds
                            SET pipe_type = pp.pipe_type,
                                pipe_size = pp.pipe_size 
                                FROM oracle.r{$zone}_pipe pp
                                WHERE ds.pwa_code = pp.pwa_code 
                                    AND ds.pipe_id = pp.pipe_id
                                    AND pp.pwa_code = '{$pwa_code}' 
                                    AND pp.pipe_id IN $pipe_id_list ; COMMIT; " ;  

        pg_exec($connection, $update_typesize);  

        $data['status'] = 'true';
        $data['message'] = 'Complete for save pipe projects.';
        echo json_encode($data);
        exit();
    }


    //echo $sql;
    //exit();
    // $sql = "SET CLIENT_ENCODING TO 'utf-8'; select * from meter_analysis.sp_getmeter_incase('%','$wwcode')";

    // $result = pg_query($connection,"select * from meter_analysis.sp_rp_compare_usewater('2022','12',3)");

    $result = pg_exec($connection, $sql);

    $numrows = pg_numrows($result);
    //echo $numrows;
    $data = array();

    if(!$result){
        echo json_encode(pg_last_error($db));
        exit();
     } else {
        for($ri = 0; $ri < $numrows; $ri++) {
            $row = pg_fetch_array($result, $ri);

            if ($act=='pipe_all') {
                array_push($data, array(
                'update_data'=>$row['update_data'], 
                'c_pipe'=>$row['c_pipe'], 
                'l_pipe'=>$row['l_pipe'],
                'c_mt'=>$row['c_mt'],
                'c_valve'=>$row['c_valve'],
                'c_fire'=>$row['c_fire'],
                'c_leak'=>$row['c_leak'],
                ));
            }

            else if ($act=='pipe_sum') {
                array_push($data, array(
				'c_pipe'=>$row['c_pipe'], 
                'l_pipe'=>$row['l_pipe'],
                'c_leak'=>$row['c_leak'],
                'cost_repair'=>$row['cost_repair'],
                ));
            }

            else if ($act=='pipe_typesize') {
                array_push($data, array(
                'pipe_type'=>$row['pipe_type'],
                'pipe_size'=>$row['pipe_size'],
				'c_pipe'=>$row['c_pipe'], 
                'l_pipe'=>$row['l_pipe'],
                'c_leak'=>$row['c_leak'],
                'cost_repair'=>$row['cost_repair'],
                ));
            }

            else if ($act=='pipe_detail') {
                array_push($data, array(
                'pipe_id'=>$row['pipe_id'],
                'project_no'=>$row['project_no'],
                'pipe_type'=>$row['pipe_type'],
                'pipe_size'=>$row['pipe_size'],
                'yearinstall'=>$row['yearinstall'], 
				'pipe_long'=>$row['pipe_long'], 
                'contrac_date'=>$row['contrac_date'],
                'asset_code'=>$row['asset_code'],
                'pipe_func'=>$row['pipe_func'],
                'laying'=>$row['laying'],
                'product'=>$row['product'],
                'depth'=>$row['depth'],
                'locate'=>$row['locate'],
                ));
            }

            else if ($act=='pipe_sum_freq') {
                array_push($data, array(
				'c_pipe'=>$row['c_pipe'], 
                'l_pipe'=>$row['l_pipe'],
                'c_leak'=>$row['c_leak'],
                'cost_repair'=>$row['cost_repair'],
                'avg_lk'=>$row['avg_lk'],
                ));
            }

            else if ($act == 'pipe_typesize_freq') {
                array_push($data, array(
                'avg_lk'=>$row['avg_lk'],
                'pipe_type'=>$row['pipe_type'],
                'pipe_size'=>$row['pipe_size'],
				'c_pipe'=>$row['c_pipe'], 
                'l_pipe'=>$row['l_pipe'],
                'c_leak'=>$row['c_leak'],
                'cost_repair'=>$row['cost_repair'],
                ));
            }

            else if ($act == 'pipe_detail_freq') {
                array_push($data, array(
                'pipe_id'=>$row['pipe_id'],
                'avg_lk'=>$row['avg_lk'],
                'c_leak'=>$row['c_leak'],
                'cost_repair'=>$row['cost_repair'],
                'pipe_type'=>$row['pipe_type'],
                'pipe_size'=>$row['pipe_size'],
                'yearinstall'=>$row['yearinstall'],
                'pipe_long'=>$row['pipe_long'], 
                'project_no'=>$row['project_no'],
                'contrac_date'=>$row['contrac_date'],
                'asset_code'=>$row['asset_code'],
                'pipe_func'=>$row['pipe_func'],
                'laying'=>$row['laying'],
                'product'=>$row['product'],
                'depth'=>$row['depth'],
                'locate'=>$row['locate'],
                ));
            }
        }  
     }
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($data);

//}

?>