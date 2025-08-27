<?php
//phpinfo();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// สร้างสเปรดชีตใหม่
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// สมมติว่าคุณมีข้อมูลจำนวนมากใน array
$data = [];
for ($i = 1; $i <= 150000; $i++) {
    $data[] = ['Row ' . $i, 'Data ' . $i];
}

// เขียนข้อมูลลงในสเปรดชีต
$sheet->fromArray($data, null, 'A1');

// สร้างไฟล์ Excel
$writer = new Xlsx($spreadsheet);

$filename = 'my_spreadsheet.xlsx';
//$writer->save('large_data.xlsx');
//echo "สร้างไฟล์ Excel ที่มีข้อมูลจำนวนมากสำเร็จ!";

// ตั้งค่า header สำหรับดาวน์โหลดไฟล์ Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// ส่งไฟล์ไปยัง output stream ซึ่งจะทำให้ผู้ใช้สามารถดาวน์โหลดไฟล์ได้
$writer->save('php://output');

exit();



?>