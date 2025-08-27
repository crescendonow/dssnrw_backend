<?php

@ini_set('display_errors', '0'); //äÁèáÊ´§error
session_start();
if ($_SESSION['uid']!='' && ($_SESSION['permission']=='21232f297a57a5a743894a0e4a801fc3' || $_SESSION['permission']=='ee11cbb19052e40b07aac0ca060c23ee') && $_SESSION['loginstatus']==1){

date_default_timezone_set('Asia/Bangkok');
$response="../service/".$_GET['file_name'];
//if ($response!=0){
	/** PHPExcel */
	require_once 'Classes2/PHPExcel.php';

	/** PHPExcel_IOFactory - Reader */
	include 'Classes2/PHPExcel/IOFactory.php';


	$inputFileName = $response;  
	$inputFileType = PHPExcel_IOFactory::identify($inputFileName);  
	$objReader = PHPExcel_IOFactory::createReader($inputFileType);  
	$objReader->setReadDataOnly(true);  
	$objPHPExcel = $objReader->load($inputFileName);  
	
	$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
	$highestRow = $objWorksheet->getHighestRow();
	$highestColumn = $objWorksheet->getHighestColumn();

	$col_found='';
	$row_found='';
	$row_max= $objWorksheet->getHighestRow();


	$foundInCells = array();

	foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
		$ws = $worksheet->getTitle();
		foreach ($worksheet->getRowIterator() as $row) {
			$cellIterator = $row->getCellIterator();
			$cellIterator->setIterateOnlyExistingCells(true);
			foreach ($cellIterator as $cell) {
				if ($cell->getValue() === 'รหัสผู้ใช้น้ำ') {
					//$foundInCells[] = $ws . '!' . $cell->getCoordinate();
					//echo $cell->getValue()."-----".$cell->getCoordinate()."-----".$cell->getColumn()."-----".$cell->getRow();
					$col_found =$cell->getColumn();
					$row_found =$cell->getRow();

					break;
				}
			}
		}
	}
	if($col_found == ''){
		foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
			$ws = $worksheet->getTitle();
			foreach ($worksheet->getRowIterator() as $row) {
				$cellIterator = $row->getCellIterator();
				$cellIterator->setIterateOnlyExistingCells(true);
				foreach ($cellIterator as $cell) {
					if (strtolower($cell->getValue()) === 'custcode') {
						//$foundInCells[] = $ws . '!' . $cell->getCoordinate();
						//echo $cell->getValue()."-----".$cell->getCoordinate()."-----".$cell->getColumn()."-----".$cell->getRow();
						$col_found =$cell->getColumn();
						$row_found =$cell->getRow();	
						break;
					}
				}
			}
		}

	}
	else{

		
	}

	if($col_found == ''){
		header('Content-Type: application/json; charset=utf-8');
		$res = array();
		array_push($res, array(
					'status' => 'ไม่พบคอลัมภ์รหัสผู้ใช้น้ำ',
					));
		echo json_encode($res);	
		
		exit();

	}


	$namedDataArray = array();
	$r = -1;
	for ($row = ($row_found+1); $row <= $row_max; ++$row) {
		array_push($namedDataArray, array(
			'CUSTCODE' => $objPHPExcel->getActiveSheet()->getCell($col_found.$row)->getValue(),
			));
		//$namedDataArray[$r]['CUSTCODE'] = $objPHPExcel->getActiveSheet()->getCell($col_found.$row)->getValue(); //set default header column to CUSTCODE
		//$namedDataArray[$r]['CUSTCODE'] = $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(1, 8)->getValue(); //set default header column to CUSTCODE
		//$namedDataArray[$r]['CUSTCODE'] = $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(1, 8)->getCalculatedValue(); //set default header column to CUSTCODE
	}

	//echo $namedDataArray[-1]['CUSTCODE'];
	
	//exit();

	//$headingsArray = $objWorksheet->rangeToArray('A1:'.$highestColumn.'1',null, true, true, true);
	//$headingsArray = $headingsArray[1];

	// $r = -1;
	// $namedDataArray = array();
	// for ($row = 2; $row <= $highestRow; ++$row) {
	// 	$dataRow = $objWorksheet->rangeToArray('A'.$row.':'.$highestColumn.$row,null, true, true, true);
	// 	if ((isset($dataRow[$row]['A'])) && ($dataRow[$row]['A'] > '')) {
	// 		++$r;
	// 		foreach($headingsArray as $columnKey => $columnHeading) {
	// 			//$namedDataArray[$r][$columnHeading] = $dataRow[$row][$columnKey];
	// 			$namedDataArray[$r]['CUSTCODE'] = $dataRow[$row][$columnKey]; //set default header column to CUSTCODE
	// 			//echo $r.'-'.$dataRow[$row][$columnKey].'<br>';
	// 		}
	// 	}
		
		
	// }






	//-----------------------------------INSERT EXCEL TO Postgres---------------------------------
	include 'connect.php';
	//Drop table
	$strSQL="";
	$temp_table="meterstat.custcode_upload_".$_SESSION['uid'];	
	$strSQL="";
	$query="";
	pg_exec($connection, "DROP TABLE IF EXISTS $temp_table;");
	pg_exec($connection, "CREATE TABLE $temp_table (LIKE meterstat.custcode_upload_template)");
	$i=0;
	$j=0;
	$reg=$_GET['reg'];
	$pwa_code=$_GET['pwa_code'];
	$data=array();
	foreach ($namedDataArray as $result) {
		$i++;
		//echo $result["custcode"].'<br>';
		$custcode_lenght=strlen($result['CUSTCODE']);
		//echo $custcode_lenght.'-'.$result['CUSTCODE'].'<br>';
		if($custcode_lenght==7 || $custcode_lenght==11){
			$j++;
			$strSQL="INSERT INTO $temp_table (ogc_fid,custcode,pwa_code,reg) VALUES($j,'".$result['CUSTCODE']."','".$pwa_code."','".$reg."');";
			$query=pg_exec($connection,$strSQL);			
			//echo $strSQL;
			
		}else{
		}
		

		

	}

	/*if($i==$j && $j>0){
		echo 'success all $j/$i';
	}else{
		echo $j.'/'.$i;
	}*/
	$reg=$_GET['reg'];
	$pwa_code=$_GET['pwa_code'];

	$fields = "dma_no,custcode,usetype,custname,custaddr,custpost,custtel,custstat,nearlocate,meterno,meterstat meterstat,metersize,mtrmkcode,metermake,mtrrdroute,mtrseq,prsmtrcnt,lstmtrddt,prswtusg,lstwtusg1,lstwtusg2,lstwtusg3,lstwtusg4,lstwtusg5,lstwtusg6,lstwtusg7,lstwtusg8,lstwtusg9,lstwtusg10,lstwtusg11,lstwtusg12,avg_before12_cis,avgwtusg,bgncustdt,bgnmtrdt,pwa_code";

	//$fields = "custcode,usetype,regis_no,custname,custaddr,custpost,custtel,custstat,nearlocate,meterno,meterstat meterstat,metersize,mtrmkcode,metermake,mtrrdroute,mtrseq,prsmtrcnt,lstmtrddt,prswtusg,lstwtusg1,lstwtusg2,lstwtusg3,lstwtusg4,lstwtusg5,lstwtusg6,lstwtusg7,lstwtusg8,lstwtusg9,lstwtusg10,lstwtusg11,lstwtusg12,avgwtusg,bgncustdt,bgnmtrdt,pwa_code";

		$sql ="SET CLIENT_ENCODING TO 'utf-8'; SELECT row_to_json(fc) ";
		$sql.= "FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features ";
		$sql.= "FROM (SELECT 'Feature' As type ";
		$sql.= ", ST_AsGeoJSON(lg.wkb_geometry)::json As geometry ";
		$sql.= ", row_to_json((SELECT l FROM (SELECT ".$fields.") As l ";
		$sql.= ")) As properties ";
		//$sql.= "FROM ".$table_meter." As lg WHERE length(meterno)=14 AND substring(meterno,4,1) IN('6','7','8') AND pwa_code='".$pwa_code."'";
		$sql.= "FROM (SELECT b.* FROM meterstat.custcode_upload_"  .$_SESSION['uid'] . " a INNER JOIN (SELECT * FROM giswebm_stamp.r".$reg."_bl_customer WHERE pwa_code='$pwa_code') b ON a.custcode=b.custcode) As lg WHERE ";
		$sql.="wkb_geometry IS NOT NULL) As f )  As fc";		
//	echo $sql;
	$result = pg_exec($connection, $sql);	
	$arr = pg_fetch_array($result, 0, PGSQL_NUM);
	echo $arr[0];	
	pg_close($connection);	
	/*

	mysqli_query($connect,"DROP TABLE IF EXISTS $temp_table;");
	mysqli_query($connect,"CREATE TABLE $temp_table LIKE template_custcode"); 
	foreach ($namedDataArray as $result) {
		//echo $result["custcode"].'<br>';
		$strSQL="INSERT INTO $temp_table (custcode) VALUES('".$result["custcode"]."');";
		mysqli_query($connect,$strSQL);
	}
	mysqli_close($connect);
	*/
//}
}

?>