<?php


if(isset($_GET['id'])){
   $idrkk = $_GET['id'];

  $tampildetail=$koneksi->query("select * from tb_rkk where id_rkk = '$idrkk' ");
$datadetail=$tampildetail->fetch_assoc();
$datatglrkk = $datadetail['tgl_rkk'];
$dataketerangan = $datadetail['keterangan'];
$datajammasuk   = $datadetail['jam_masuk'];
$datajamkeluar   = $datadetail['jam_keluar'];
$dataistirahatmasuk   = $datadetail['istirahat_masuk'];
$dataistirahatkeluar  = $datadetail['istirahat_keluar'];

}else{
  $datatglrkk = "";
  $dataketerangan = "";
$datajammasuk   = "";
$datajamkeluar   = "";
$dataistirahatmasuk   = "";
$dataistirahatkeluar  = "";
}

?>
<div class="row">
                <div class="col-md-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-primary"  >
                    <div class="box-header with-border" style=" background-color:#5F9EA0; border:1px ; color:white; ">
              <h3 class="box-title">Ubah Jadwal</h3>
            </div>
             <form method="POST"  enctype="multipart/form-data">
                        <div class="panel-body">
                           <div class="panel-body">
                           
                            <div class="row" style=" background-color:white; border:1px ; color:black; "> 
                            <div hidden="hidden"  class="form-group col-md-4">
                    <label class="font-weight-bold">Id Karyawan</label>
                    
                    <input  autocomplete="off" type="text" name="tid"   class="form-control"/>
                </div>
                <div class="form-group col-md-3">
                    <label class="font-weight-bold">Tanggal</label>
                    <input placeholder="*" autocomplete="off" type="date" name="ttgl1" value="<?php echo $datatglrkk; ?>" required class="form-control"/>
                    
                </div>

                 <div class="form-group col-md-4">
                    <label class="font-weight-bold">Keterangan / Shift </label>
                    <input placeholder="*" autocomplete="off" type="text" name="tketerangan" value ="<?php echo $dataketerangan ; ?>" required class="form-control"/>
                    
                </div>
                 <div class="form-group col-md-2">
                    <label class="font-weight-bold">Jam Masuk</label>
                   <input placeholder="*" autocomplete="off" type="time" name="tjammasuk" value="<?php echo $datajammasuk; ?>" required class="form-control"/>
                    
                </div>
                  <div class="form-group col-md-2">
                    <label class="font-weight-bold">Jam Keluar</label>
                   <input placeholder="*" autocomplete="off" type="time" name="tjamkeluar" value="<?php echo $datajamkeluar; ?>" required class="form-control"/>
                    
                </div>
                  <div class="form-group col-md-2">
                    <label class="font-weight-bold">Istirahat Masuk</label>
                   <input placeholder="*" autocomplete="off" type="time" name="tistirahatmasuk" value="<?php echo $dataistirahatmasuk; ?>" required class="form-control"/>
                    
                </div>
                 <div class="form-group col-md-2">
                    <label class="font-weight-bold">Istirahat Keluar</label>
                   <input placeholder="*" autocomplete="off" type="time" name="tistirahatkeluar" value="<?php echo $dataistirahatkeluar; ?>" required class="form-control"/>
                    
                </div>
          
            
                
                 

                </div>


                  



                  <div class="row" style=" background-color:white; border:1px ; color:black; "> 
                    <div class="form-group col-md-4">
                 <div>
                                            <input type="submit" name="simpan"  value="Simpan" class="btn btn-primary">
                                              <div class="col"> <h3><label style="color:red ;" >* </label><label>HArus Diisi</label> </h3> </div>
                                        </div>
                                           
                                        </div></div>
                                    </form>
                                  
                            </div>
                          
                           

                    </div>
                </div>
        </div>

<?php
$ttgl1 = @$_POST ['ttgl1'];
$tketerangan = @$_POST ['tketerangan'];
$tjammasuk = @$_POST ['tjammasuk'];
$tjamkeluar = @$_POST ['tjamkeluar'];
$tistirahatmasuk = @$_POST ['tistirahatmasuk'];
$tistirahatkeluar = @$_POST ['tistirahatkeluar'];
$simpan = @$_POST ['simpan'];
if($simpan) {
$sql = $koneksi->query("update tb_rkk set keterangan = '$tketerangan' , jam_masuk ='$tjammasuk' , jam_keluar ='$tjamkeluar' , istirahat_masuk ='$tistirahatmasuk' , istirahat_keluar = '$tistirahatkeluar' , tgl_rkk='$ttgl1' where id_rkk = '$idrkk' ");
if($sql) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: "Data Tersimpan",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                }).then((result) => {
                    window.location.href="?page=rkk";
                });
            </script>
        </body>
        </html>';
    }
}//simpan if
?>