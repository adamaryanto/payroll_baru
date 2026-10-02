<?php
// Cek apakah karyawan 251-253 ada di database
include "koneksi.php";

echo "<h3>Cek Data Karyawan 251-253 di Database</h3>";

$result = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan, status_karyawan FROM ms_karyawan WHERE no_absen IN ('251','252','253')");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>No Absen</th><th>Nama</th><th>Status</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td align='center'>" . $row['id_karyawan'] . "</td>";
        echo "<td align='center'>" . $row['no_absen'] . "</td>";
        echo "<td>" . $row['nama_karyawan'] . "</td>";
        echo "<td align='center'>" . $row['status_karyawan'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>Tidak ada data karyawan dengan no_absen 251, 252, 253 di tabel ms_karyawan.</p>";
}

// Cek total karyawan
$total = $koneksi->query("SELECT COUNT(*) as total FROM ms_karyawan")->fetch_assoc();
echo "<p>Total karyawan di database: " . $total['total'] . "</p>";

// Cek beberapa karyawan terakhir
echo "<h4>10 Karyawan Terbaru:</h4>";
$recent = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan ORDER BY id_karyawan DESC LIMIT 10");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>No Absen</th><th>Nama</th></tr>";
while($row = $recent->fetch_assoc()) {
    echo "<tr>";
    echo "<td align='center'>" . $row['id_karyawan'] . "</td>";
    echo "<td align='center'>" . $row['no_absen'] . "</td>";
    echo "<td>" . $row['nama_karyawan'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>