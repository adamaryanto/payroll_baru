<?php

if(isset($_GET['id'])) {
   $idrkk = $_GET['id'];
$tampilrkk = $koneksi->query("select * from tb_rkk where id_rkk = '$idrkk' ");
$datarkk=$tampilrkk->fetch_assoc();
$tglrkk = $datarkk['tgl_rkk'];
$jammasuk = substr($datarkk['jam_masuk'],0,3);
$jamkeluar = substr($datarkk['jam_keluar'],0,3);
$istirahatmasuk = substr($datarkk['istirahat_masuk'],0,3);
$istirahatkeluar = substr($datarkk['istirahat_keluar'],0,3);

$tampil = $koneksi->query("
   SELECT DISTINCT A.tgl , A.userid , 
  
 TIME_FORMAT(D.jam_masuk, '%H:%i')AS masukkerja  , 
 TIME_FORMAT(D.jam_keluar, '%H:%i')AS pulangkerja ,
  
  
  (SELECT  TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE tgl = A.tgl AND userid = A.userid AND
 (detail_waktu LIKE '%$jammasuk%' OR STATUS = '0'
 ) ORDER BY detail_waktu ASC LIMIT 1) AS 'masukkonvert' , 
 
 CASE WHEN (SELECT TIMEDIFF(masukkonvert,masukkerja)) < '0'
 THEN (SELECT TIMEDIFF(masukkonvert,masukkerja)) ELSE '' END AS 'masuklebihawal' ,
 
 CASE WHEN (SELECT TIMEDIFF(masukkonvert,masukkerja)) > '0'
 THEN (SELECT TIMEDIFF(masukkonvert,masukkerja)) ELSE '' END AS 'terlambat' ,

  TIME_FORMAT(D.istirahat_keluar, '%H:%i')AS  istirahatkeluar ,
 TIME_FORMAT(D.istirahat_masuk, '%H:%i')AS  istirahatmasuk ,
 
(SELECT  TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE tgl = A.tgl AND userid = A.userid
 AND (detail_waktu LIKE '%$istirahatkeluar%' OR STATUS = '2')  ORDER BY detail_waktu ASC LIMIT 1) AS 'istirahatkeluarkonvert' ,
(SELECT  TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE tgl = A.tgl AND userid = A.userid
 AND (detail_waktu LIKE '%$istirahatmasuk%' OR STATUS = '3')  ORDER BY detail_waktu ASC LIMIT 1) AS 'istirahatkonvert' ,
 
 (SELECT  TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE tgl = A.tgl AND userid = A.userid
 AND (detail_waktu LIKE '%$jamkeluar%' OR STATUS = '1')  ORDER BY detail_waktu DESC LIMIT 1) AS 'pulangkonvert' ,
 
  CASE WHEN (SELECT TIMEDIFF(pulangkonvert,pulangkerja)) < '0'
 THEN (SELECT TIMEDIFF(pulangkonvert,pulangkerja)) ELSE '' END AS 'pulanglebihawal' ,
 
 CASE WHEN (SELECT TIMEDIFF(pulangkonvert,pulangkerja)) > '0'
 THEN (SELECT TIMEDIFF(pulangkonvert,pulangkerja)) ELSE '' END AS 'lembur' ,
 
  CASE WHEN (SELECT TIMEDIFF(masukkonvert,masukkerja)) > '0'
 THEN (SELECT denda_masuk FROM tb_denda) ELSE '0' END AS 'dendamasuk' ,
 
 CASE WHEN (SELECT TIMEDIFF(istirahatkonvert,istirahatmasuk)) > '0'
 THEN (SELECT denda_istirahat FROM tb_denda) ELSE '0' END AS 'dendaistirahat' ,
  
  B.nama_karyawan , C.upah, C.status_rkk, C.id_rkk_detail, 
(SELECT nama_departmen FROM ms_departmen WHERE id_departmen = C.id_departmen) AS namadepartmen,
BB.nama_sub_department , C.status_rkk

   FROM tb_record A   
   LEFT JOIN ms_karyawan B ON A.userid = B.no_absen
   LEFT JOIN tb_rkk_detail C ON B.id_karyawan = C.id_karyawan
   LEFT JOIN tb_rkk D ON C.id_rkk = D.id_rkk
   left join ms_sub_department BB on C.id_sub_department = BB.id_sub_department
 WHERE A.tgl = '$tglrkk'  AND B.nama_karyawan <> ''
 AND C.id_rkk = '$idrkk' AND (C.status_rkk = 'Hadir' OR C.status_rkk = 'Pengganti')
 ORDER BY namadepartmen , A.tgl ASC
 
 
");
  }

    ?>

<div class="row">
                <div class="col-md-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-primary"  >
                    <div class="box-header with-border" style=" background-color:#5F9EA0; border:1px ; color:white; ">
              <h3 class="box-title">Data Payroll</h3>
            </div>
             <form method="POST"  enctype="multipart/form-data">
                        <div class="panel-body">
                           
                      
              
            <div class="row" style=" background-color:white; border:1px ; color:black; "> 
                 <div class="form-group col-md-6">
                     
                       <input type="submit" name="excel"  value="Excel" class="btn btn-success">
                        <input type="submit" name="print"  value="Pdf" class="btn btn-info">
                </div>
                </div>

                
                                    </form>
                                    <div class="form-group "></div>

                            <div class="table-responsive">
                                 <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                
               

          
               

                </div>
                                <table class="table table-bordered table-striped" id="dataTables-example">
                                    <thead >
                                        <tr>
                                        <th width="5%">No</th>
                                           <th >NIK</th>
                                            <th >Nama Karyawan</th>
                                            <th >Nama Bagian</th>
                                            <th >Sub Bagian</th>
                                               <th >Status</th>
                                           
                                              <th >Tanggal</th>
                                             <th >Jam Masuk</th>
                                              <th >Jam Pulang</th>
                                                <th >Absen Masuk</th>

                                        <th hidden>Masuk lebih awal</th>
                                        <th hidden style=" background-color:#FFEBCD; border:1px ; color:black; ">Terlambat</th>

                                        <th hidden>Istirahat Keluar</th>
                                        <th hidden>Istirahat Masuk</th>
                                        <th >Istirahat Keluar</th>
                                        <th >Istirahat Masuk</th>

                                       
                                        <th >Absen Pulang</th>
                                        <th hidden style=" background-color:#FFEBCD; border:1px ; color:black; ">Pulang Lebih Awal</th>
                                        <th >Lembur</th>
                                          <th >Upah</th>
                                        <th >Potongan</th>
                                         <th >Upah Dibayar</th>
                        

                                        </tr>
                                    </thead>
                                    <tbody>
                                                                <?php


$no = 1;


    while ($data=$tampil->fetch_assoc())
    {

?>


                                        <tr>
<td><?php echo $no ?></td>
<td><?php echo $data['userid'] ?></td>
<td><?php echo $data['nama_karyawan'] ?></td>
<td><?php echo $data['namadepartmen'] ?></td>
<td><?php echo $data['nama_sub_department'] ?></td>
<td><?php echo $data['status_rkk'] ?></td>
<td><?php echo $data['tgl'] ?></td>
<td ><?php echo $data['masukkerja'] ?></td>
<td><?php echo $data['pulangkerja'] ?></td>
<td
style=" background-color:<?php

if ($data['terlambat'] != ''){echo "#FFEBCD";}
 ?>"

><?php echo $data['masukkonvert'] ?></td>

<td hidden><?php echo $data['masuklebihawal'] ?></td>
<td hidden style=" background-color:#FFEBCD; border:1px ; color:black; "><?php echo $data['terlambat'] ?></td>
<td hidden><?php echo $data['istirahatkeluar'] ?></td>
<td hidden><?php echo $data['istirahatmasuk'] ?></td>
<td><?php echo $data['istirahatkeluarkonvert'] ?></td>
<td><?php echo $data['istirahatkonvert'] ?></td>



<td

style=" background-color:<?php

if ($data['pulanglebihawal'] != ''){echo "#FFEBCD";}
 ?>"

><?php echo $data['pulangkonvert'] ?></td>
<td  hidden style=" background-color:#FFEBCD; border:1px ; color:black; "><?php echo $data['pulanglebihawal'] ?></td>
<td><?php echo $data['lembur'] ?></td>

<td><?php echo number_format( $data['upah'],0,',','.') ?></td>
<td><?php echo number_format( $data['dendamasuk'] + $data['dendaistirahat'],0,',','.') ?></td>
<td><?php echo number_format( $data['upah']-$data['dendamasuk'] - $data['dendaistirahat'],0,',','.') ?></td>

<!--
<td>
    
<a  href="?page=order&id=<?php echo $data['id_transaksi'];?>"  class="btn btn-success"> Update</a>


</td>
-->
                                      
                                            
                                        </tr>

                                       <?php  $no++; } ?>

                                    </tbody>   
                                    </table>
                            </div>

                           

                    </div>
                </div>
        </div>
    </div>
  
    <script>
 $(document).ready( function () {
$('#dataTables-example').DataTable({
    pageLength: 100,
    "searching": true
}
);

} );
</script>

<?php

$ttgl1 = @$_POST ['ttgl1'];
$ttgl2 = @$_POST ['ttgl2'];
$tdepartmen = @$_POST ['tdepartmen'];
$tshift = @$_POST ['tshift'];

$simpan = @$_POST ['simpan'];
$print = @$_POST ['print'];
$excel = @$_POST ['excel'];
if($simpan){
    ?><script type="text/javascript">
                 window.location.href="?page=payroll&ttgl1=<?php echo $ttgl1 ; ?>&ttgl2=<?php echo$ttgl2 ; ?>&tdepartmen=<?php echo $tdepartmen ?>&tshift=<?php echo $tshift ?>";

            </script>
            <?php
}
if($print){
    ?><script type="text/javascript">
                 window.location.href="pdfpayroll.php?id=<?php echo $idrkk ; ?>";


            </script>
            <?php
}
if($excel){
    ?><script type="text/javascript">
                 window.location.href="excelpayroll.php?id=<?php echo $idrkk ; ?>";

            </script>
            <?php
}
 


?>