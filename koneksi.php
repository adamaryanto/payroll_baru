<?php 
$http_host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($http_host, 'localhost') !== false || strpos($http_host, '127.0.0.1') !== false || strpos($http_host, '.test') !== false) {
    // KONEKSI LOKAL (Laragon / PC Anda)
    $host = '127.0.0.1'; 
    $user = 'root'; 
    $pass = ''; 
    $database = 'db_hr';
} else {
    // KONEKSI ONLINE (cPanel Hosting)
    $host = '127.0.0.1'; 
    $user = 'usjakoqd_db_hr'; 
    $pass = 'snWK9uw?j}T;ghF,'; 
    $database = 'usjakoqd_db_hr';
}

$koneksi = mysqli_connect($host, $user, $pass); 
mysqli_select_db($koneksi, $database); 

if (!$koneksi) {
    echo "failure";
}
?>
