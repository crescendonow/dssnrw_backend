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

$inputFileName = '../upload_file/pipeid_banmor_oldver.xls'; // เปลี่ยนเป็นชื่อไฟล์ของคุณ

//recieve variable from post 
$pwa_code = $_REQUEST['pwa_code'];
$user_id = $_REQUEST['user_id'];

//make directory for store file 
$folder_name = "../upload_file/xlsx_{$pwa_code}" ;
//$inputFileName = glob($folder_name . '/*'); // get all file names
//echo $inputFileName[1];
//exit();

if (!file_exists($folder_name)) {
    // สร้างโฟลเดอร์ พร้อมสิทธิ์ 0755 และสร้างโฟลเดอร์ซ้อนกันได้
    if (mkdir($folder_name, 0755, true)) {
        echo "สร้างโฟลเดอร์สำเร็จ: $folderName";
    } else {
        echo "ไม่สามารถสร้างโฟลเดอร์ได้";
    }
} else { 
    foreach ($inputFileName as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "โฟลเดอร์มีอยู่แล้ว: $folder_name : ดำเนินการลบไฟล์ในโฟลเดอร์แล้ว";
}


// โหลดไฟล์ Excel
$spreadsheet = IOFactory::load($inputFileName);

// เลือกแผ่นงานแรก
$sheet = $spreadsheet->getActiveSheet();

// วนลูปอ่านค่าจากแต่ละแถว
foreach ($sheet->getRowIterator() as $row) {
    $cellIterator = $row->getCellIterator();

    $cellIterator->setIterateOnlyExistingCells(false); // เพื่อให้ได้ค่าว่างด้วย

    foreach ($cellIterator as $cell) {
        echo $cell->getValue() . "\t";
    }
    echo PHP_EOL;
}
?>
