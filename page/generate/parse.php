<?php
function Parse_Data($data,$p1,$p2){
	$data=" ".$data;
	$hasil="";
	$awal=strpos($data,$p1);
	if($awal!=""){
		$akhir=strpos($data,$p2);
		$hasil=substr($data,$awal+strlen($p1),$akhir-$awal-strlen($p1));
	}
	return $hasil;	
}
?>
