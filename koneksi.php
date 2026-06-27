<?php 
$host = '127.0.0.1'; 
$user = 'root'; 
$pass = ''; 
$database = 'db_hr';

$koneksi = mysqli_connect($host, $user, $pass); 
mysqli_select_db($koneksi, $database); 

if (!$koneksi) {
    echo "failure";
}
?>
