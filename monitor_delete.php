<?php
/**
 * Dashboard Monitoring Test Hapus
 * Real-time check web database vs file mock
 */

include "koneksi.php";

// Data target
$targets = [
    ['id' => 510, 'no_absen' => '251', 'nama' => 'WARDIAH'],
    ['id' => 511, 'no_absen' => '252', 'nama' => 'DEDI'],
    ['id' => 512, 'no_absen' => '253', 'nama' => 'LIA MELIA']
];

// File mock
$mock_file = __DIR__ . '/mock_device_data.json';
$mock_data = file_exists($mock_file) ? json_decode(file_get_contents($mock_file), true) : ['users' => []];

echo "<h3>Dashboard Monitoring Test Hapus</h3>";
echo "<p>Refresh halaman ini setelah hapus untuk melihat perubahan real-time.</p>";

echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
echo "<tr style='background-color:#f8f9fa;'>";
echo "<th>ID Web</th><th>No Absen</th><th>Nama</th><th>Status di Web</th><th>Status di Mock Mesin</th><th>PIN2 di Mock</th><th>Action</th>";
echo "</tr>";

foreach ($targets as $target) {
    // Cek di database web
    $result = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan, status_karyawan FROM ms_karyawan WHERE id_karyawan = " . $target['id']);
    $web_exists = $result->num_rows > 0;
    $web_data = $web_exists ? $result->fetch_assoc() : null;
    
    // Cek di mock
    $mock_exists = false;
    $mock_info = '';
    $pin2 = '';
    foreach ($mock_data['users'] as $user) {
        if ($user['PIN'] == $target['no_absen']) {
            $mock_exists = true;
            $pin2 = $user['PIN2'];
            $mock_info = "✓ Ada (" . count($user['Fingerprints']) . " fingerprint)";
            break;
        }
    }
    
    echo "<tr>";
    echo "<td align='center'>" . $target['id'] . "</td>";
    echo "<td align='center'><strong>" . $target['no_absen'] . "</strong></td>";
    echo "<td>" . $target['nama'] . "</td>";
    
    // Status web
    if ($web_exists) {
        echo "<td align='center' style='background-color:#d4edda; color:#155724;'>✓ ADA<br><small>Status: " . $web_data['status_karyawan'] . "</small></td>";
    } else {
        echo "<td align='center' style='background-color:#f8d7da; color:#721c24;'>✗ DIHAPUS</td>";
    }
    
    // Status mock
    if ($mock_exists) {
        echo "<td align='center' style='background-color:#d4edda; color:#155724;'>" . $mock_info . "</td>";
    } else {
        echo "<td align='center' style='background-color:#f8d7da; color:#721c24;'>✗ DIHAPUS DARI MESIN</td>";
    }
    
    echo "<td align='center'>" . ($pin2 ?: '-') . "</td>";
    
    // Action button
    if ($web_exists) {
        echo "<td align='center'><a href='?page=karyawan&aksi=hapus&id=" . $target['id'] . "' onclick='return confirm(\"Hapus " . $target['nama'] . " (PIN " . $target['no_absen'] . ")?\")' style='padding:5px 15px; background-color:#e11d48; color:white; border-radius:4px; text-decoration:none;'>Hapus</a></td>";
    } else {
        echo "<td align='center' style='color:#6c757d;'>-</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

// Summary
echo "<h4>Ringkasan:</h4>";
echo "<ul>";
echo "<li><strong>Test Case:</strong> Hapus WARDIAH (ID 510, PIN 251)</li>";
echo "<li><strong>Expected:</strong> PIN 251 hilang dari mock, PIN 252 & 253 tetap ada</li>";
echo "<li><strong>Safety Check:</strong> PIN2 match tidak menyebabkan hapus user lain</li>";
echo "</ul>";

// Warning if PIN2 exists for other users
echo "<h4>Warning Check:</h4>";
$warnings = [];
foreach ($mock_data['users'] as $user) {
    if (!empty($user['PIN2']) && in_array($user['PIN2'], ['251', '252', '253'])) {
        $warnings[] = "PIN " . $user['PIN'] . " memiliki PIN2 = " . $user['PIN2'] . " (match dengan target)";
    }
}

if (empty($warnings)) {
    echo "<p style='color:green;'>✓ Tidak ada PIN2 yang match dengan target (aman).</p>";
} else {
    echo "<p style='color:orange;'>⚠ Warning:</p>";
    echo "<ul>";
    foreach ($warnings as $w) {
        echo "<li>" . $w . "</li>";
    }
    echo "</ul>";
    echo "<p>Dengan fix kita, user ini <strong>tidak akan terhapus</strong> karena hanya cek PIN utama.</p>";
}

?>