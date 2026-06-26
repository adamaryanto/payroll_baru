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

$tampil = $koneksi->query("SELECT B.no_absen , BB.nama_sub_department ,B.nama_karyawan , D.nama_departmen , C.tgl_rkk ,C.jam_masuk , C.jam_keluar , C.istirahat_keluar,
C.istirahat_masuk , A.status_rkk,
case when A.status_rkk = 'Digantikan' then '0'
 when A.status_rkk = 'Tidak Hadir' then '0'
 else A.upah

end as upahkaryawan
FROM tb_rkk_detail A 
LEFT JOIN ms_karyawan B on A.id_karyawan = B.id_karyawan
LEFT JOIN tb_rkk C ON A.id_rkk = C.id_rkk
LEFT JOIN ms_departmen D on A.id_departmen = D.id_departmen
   left join ms_sub_department BB on A.id_sub_department = BB.id_sub_department

WHERE A.id_rkk = '$idrkk'
 
");
  }

    ?>

<div class="row">
                <div class="col-md-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-primary"  >
                    <div class="box-header with-border" style=" background-color:#5F9EA0; border:1px ; color:white; ">
              <h3 class="box-title">Data Perancanaan Pengeluaran Upah</h3>
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
                                             <th >Nama SUb Bagian</th>
                                           
                                              <th >Tanggal</th>
                                              <th >Status</th>
                                             <th >Jam Masuk</th>
                                              <th >Jam Pulang</th>
                                               <th >Istirahat Keluar</th>
                                        <th >Istirahat Masuk</th>

                                       
                            
                                          <th >Upah</th>
                        

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
<td><?php echo $data['no_absen'] ?></td>
<td><?php echo $data['nama_karyawan'] ?></td>
<td><?php echo $data['nama_departmen'] ?></td>
<td><?php echo $data['nama_sub_department'] ?></td>
<td><?php echo $data['tgl_rkk'] ?></td>
<td><?php echo $data['status_rkk'] ?></td>
<td><?php echo $data['jam_masuk'] ?></td>
<td><?php echo $data['jam_keluar'] ?></td>
<td><?php echo $data['istirahat_keluar'] ?></td>
<td><?php echo $data['istirahat_masuk'] ?></td>





<td><?php echo number_format( $data['upahkaryawan'],0,',','.') ?></td>
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
                 window.location.href="pdfpayrollrkk.php?id=<?php echo $idrkk ; ?>";


            </script>
            <?php
}
if($excel){
    ?><script type="text/javascript">
                 window.location.href="excelpayrollrkk.php?id=<?php echo $idrkk ; ?>";

            </script>
            <?php
}
 


?>