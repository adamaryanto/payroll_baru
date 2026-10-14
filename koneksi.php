<?php 
$host = '127.0.0.1'; 
$user = 'root'; 
$pass = ''; 
$database = 'tes_import';

$koneksi = mysqli_connect($host, $user, $pass); 
mysqli_select_db($koneksi, $database); 

if (!$koneksi) {
    echo "failure";
}
?>
