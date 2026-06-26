<?php


//if(isset($_GET['id'])){
   // $idu = $_GET['id'];
     $idu = '1';
$tampil=$koneksi->query("SELECT * FROM tb_pt WHERE id_pt = '$idu' ");
$data=$tampil->fetch_assoc();
$idpt = $data['id_pt'];
$nama = $data['nama'];
$alamat = $data['alamat'];
$kota = $data['kota'];
$telepon = $data['telepon'];
$email = $data['email'];
$modal = $data['modal'];

?>

<div class="row">
                <div class="col-md-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-primary"  >
                    <div class="box-header with-border" style=" background-color:#5F9EA0; border:1px ; color:white; ">
              <h3 class="box-title">Data Perusahaan</h3>
            </div>
             <form method="POST"  enctype="multipart/form-data">
                        <div class="panel-body">
                           
                            <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                            <div hidden="hidden"  class="form-group col-md-12">
                    <label class="font-weight-bold">Id PT</label>
                    
                    <input  autocomplete="off" type="text" name="tid" value="<?php echo $idpt; ?>"  class="form-control"/>
                </div>
                </div>
                <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                 <div class="form-group col-md-6">
                    <label class="font-weight-bold">Nama</label>
                    <input placeholder="*" autocomplete="off" type="text" name="tnama" value="<?php echo $nama; ?>" required class="form-control"/>
                    
                </div>
                </div>
                 <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                 <div class="form-group col-md-6">
                    <label class="font-weight-bold">Alamat</label>
                    <input placeholder="*" autocomplete="off" type="text" name="talamat" value="<?php echo $alamat; ?>" required class="form-control"/>
                    
                </div>
                </div>
                 <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                 <div class="form-group col-md-6">
                    <label class="font-weight-bold">Kota</label>
                    <input placeholder="*" autocomplete="off" type="text" name="tkota" value="<?php echo $kota; ?>" required class="form-control"/>
                    
                </div>
                </div>
                 <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                 <div class="form-group col-md-6">
                    <label class="font-weight-bold">Telepon</label>
                    <input placeholder="*" autocomplete="off" type="text" name="ttelepon" value="<?php echo $telepon; ?>" required class="form-control"/>
                    
                </div>
                </div>
                 <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                 <div class="form-group col-md-6">
                    <label class="font-weight-bold">Email</label>
                    <input placeholder="*" autocomplete="off" type="text" name="temail" value="<?php echo $email; ?>" required class="form-control"/>
                    
                </div>
                </div>
                  <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                 <div class="form-group col-md-6">
                    <label class="font-weight-bold">Modal</label>
                    <input placeholder="*" autocomplete="off" type="text" name="tmodal" value="<?php echo $modal; ?>" required class="form-control"/>
                    
                </div>
                </div>
              
            

                  <div class="row" style=" background-color:whitesmoke; border:1px ; color:black; "> 
                    <div class="form-group col-md-4">
                 <div>
                                            <input type="submit" name="simpan"  value="Simpan" class="btn btn-primary">
                                              <div class="col"> <h3><label style="color:red ;" >* </label><label>Harus Diisi</label> </h3> </div>
                                        </div>
                                         
                                        </div></div>
                                    </form>
                                    <div class="form-group "></div>

                                                     

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

$tid = @$_POST['tid'] ;
$tnama = @$_POST ['tnama'];
$talamat = @$_POST ['talamat'];
$tkota = @$_POST ['tkota'];
$ttelepon = @$_POST ['ttelepon'];
$temail = @$_POST ['temail'];
$tmodal = @$_POST ['tmodal'];


$simpan = @$_POST ['simpan'];
if($simpan) {
    $td = str_replace(",",".",$tmodal);
$sql = $koneksi->query("update tb_pt set nama='$tnama',alamat='$talamat',kota='$tkota',telepon='$ttelepon',email='$temail',modal='$td' where id_pt = '$tid'  ");
if($sql) {
        ?>
                <script type="text/javascript">
                alert("Data Tersimpan");
                window.location.href="?page=setup";

            </script>
            <?php
    }
}//simpan if

?>