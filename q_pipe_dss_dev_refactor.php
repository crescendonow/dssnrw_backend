<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true);
header('Content-Type: application/json; charset=utf-8');
error_reporting(~E_NOTICE);
@ini_set('display_errors', '1');

require_once 'connect.php';
require_once 'function_dssnrw.php';

use PhpOffice\PhpSpreadsheet\IOFactory; // for upload_excel flow

// Helper: read param from $_POST/$_GET with default
function param($key, $default=null) {
    if (isset($_POST[$key])) return $_POST[$key];
    if (isset($_GET[$key])) return $_GET[$key];
    return $default;
}

$act       = param('act');           // action selector
$pwa_code = param('pwa_code');
$zone     = param('zone');
$pipe_id_form = param('pipe_id_form');  // JSON array of pipe_id or coordinates (depending on handler)

// Weights (optional; defaults can be 0 or supplied by client)
$w_age     = param('w_age', 0);
$w_leak    = param('w_leak', 0);
$w_cost    = param('w_cost', 0);
$w_elev    = param('w_elev', 0);
$w_ptype   = param('w_ptype', 0);
$w_pressure= param('w_pressure', 0);
$w_branch  = param('w_branch', 0);
$w_dma     = param('w_dma', 0);

// Project fields (for save map)
$proj_fiscal = param('proj_fiscal');
$proj_budget = param('proj_budget');
$proj_name   = param('proj_name');
$proj_remark = param('proj_remark');
$proj_cost   = param('proj_cost');

$data = [ 'status' => 'true', 'message' => 'start API' ];

// Short-circuit: OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode([ 'status' => 'ok' ]);
    exit();
}

// Router
switch ($act) {
    case 'upload_excel':
        handle_upload_excel($connection, $pwa_code, $zone, $data);
        break;

    case 'pipe_detail_map':
        handle_pipe_detail_map($connection, $pwa_code, $zone, $pipe_id_form, $data);
        break;

    case 'f_weight':
        handle_f_weight($connection, $pwa_code, $zone,
            $w_age, $w_leak, $w_cost, $w_elev, $w_ptype, $w_pressure, $w_branch, $w_dma,
            $data
        );
        break;

    case 'pipe_detail_freq_map':
        // build thresholds here then pass-through
        $age_data = get_age_data();
        $leak_data = get_summary_data($connection,
            "SELECT MAX(c_leak) max, MIN(c_leak) min, ROUND(STDDEV(c_leak), 2) AS sd, ROUND(AVG(c_leak), 2) AS mean,
                    CASE WHEN CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) <= 0 THEN 1
                         ELSE CAST(AVG(c_leak) + (0*STDDEV(c_leak)) AS INTEGER) END value_1,
                    CAST(AVG(c_leak) + (1*STDDEV(c_leak)) AS INTEGER) value_2
             FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}'"
        );
        $cost_data = get_summary_data($connection,
            "SELECT MAX(cost_repair) max, MIN(cost_repair) min, ROUND(STDDEV(cost_repair), 2) AS sd, ROUND(AVG(cost_repair), 2) mean,
                    CASE WHEN CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) <= 0 THEN 1
                         ELSE CAST(AVG(cost_repair) + (0*STDDEV(cost_repair)) AS INTEGER) END value_1,
                    CAST(AVG(cost_repair) + (1*STDDEV(cost_repair)) AS INTEGER) value_2
             FROM dssnrw.pipe_summary WHERE pwa_code = '{$pwa_code}'"
        );
        handle_pipe_detail_freq_map(
            $connection, $pwa_code, $zone, $pipe_id_form,
            $w_age, $w_leak, $w_cost, $w_elev, $w_ptype, $w_pressure, $w_branch, $w_dma,
            $age_data, $leak_data, $cost_data,
            $data
        );
        break;

    case 'pipe_proj_save_map':
        handle_pipe_proj_save_map(
            $connection, $pwa_code, $zone, $pipe_id_form,
            $proj_fiscal, $proj_budget, $proj_name, $proj_remark, $proj_cost, $data
        );
        break;

    default:
        echo json_encode([ 'status' => 'false', 'message' => 'Unknown act', 'act' => $act ]);
        pg_close($connection);
        exit();
}

pg_close($connection);
exit();
?>
