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
$proj_cost = $_POST['proj_cost'];
$proj_name = $_POST['proj_name'];
$proj_remark = $_POST['proj_remark'];
$project_prov_id = $_POST['proj_id'];
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

        //insert edited data to pipe_improve ----------------------------|

        //create array for list of pipe id 
        $arr_pipe = json_decode($pipe_id_form);

        //delete from database 
        $del_sql = " DELETE FROM dssnrw.pipe_improve 
                 WHERE project_prov_id = '{$project_prov_id}' ";

        //echo $del_sql;
        //exit(); 

        $del_pipeid = pg_exec($connection, $del_sql);

        $data['status'] = 'true';
        $data['message'] = 'Complete for delete pipe_id in project.';

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
                    INSERT INTO dssnrw.pipe_improve (zone, pwa_code, pipe_id, project_prov_id,
                        pipe_long, wkb_geometry, gen_geometry, created_date, remark, uid_insert) 
                    VALUES ({$zone}, '{$pwa_code}', {$key}, '{$project_prov_id}',  
                        ST_Length(ST_Transform(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 32647)),
                        ST_GeomFromText('LINESTRING{$coor}', 4326 ),
                        ST_SnapToGrid(ST_GeomFromText('LINESTRING{$coor}', 4326 ), 0.000001), 
                        now(), '{$proj_remark}', '{$user_id}' ); COMMIT; ";

                //echo "$istSQL\n";
                //exit();
                pg_exec($connection, $istSQL);

                $data['status'] = 'true';
                $data['message'] = 'Complete for insert new pipe_id and coordinate in project.';
        }

        $istref_SQL  =  " SET CLIENT_ENCODING TO 'utf-8'; 
                                UPDATE dssnrw.ref_pipe_improve 
                                SET project_prov_name =  '{$proj_name}',  created_date = now(),
                                    remark = '{$proj_remark}', proj_cost = {$proj_cost},
                                    uid_insert = '{$user_id}' 
                                WHERE project_prov_id = '{$project_prov_id}'  ; 
                                
                                UPDATE dssnrw.pipe_improve pm
                                SET fiscal_year = rm.fiscal_year, budget_type = rm.budget_type, 
                                    project_prov_name =  rm.project_prov_name,  created_date = now(),
                                    remark = rm.remark, proj_cost = rm.proj_cost,
                                    uid_insert = rm.uid_insert 
                                FROM dssnrw.ref_pipe_improve rm
                                WHERE pm.project_prov_id = '{$project_prov_id}'  
                                    AND pm.project_prov_id = rm.project_prov_id ; " ;

        pg_exec($connection, $istref_SQL);
        //echo $istref_SQL;
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
                                AND pm.pipe_id IN $pipe_id_list ; COMMIT; " ; 

        pg_exec($connection, $update_typesize);

        $data['status'] = 'true';
        $data['message'] = 'Complete for edit pipe projects.';
}

echo json_encode($data);

//}
