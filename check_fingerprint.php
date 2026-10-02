<?php
/**
 * Deteksi Status Fingerprint untuk 3 Karyawan
 * Simulasi cek di mesin fingerprint via file mock
 */

// Data karyawan yang mau di-check
$karyawan_list = [
    ['id' => 251, 'no_absen' => '251', 'nama' => 'WARDIAH', 'os' => 'CP'],
    ['id' => 252, 'no_absen' => '252', 'nama' => 'DEDI', 'os' => 'DHK'],
    ['id' => 253, 'no_absen' => '253', 'nama' => 'LIA MELIA', 'os' => 'DHK']
];

// File mock mesin
$mock_file = __DIR__ . '/mock_device_data.json';

// Fungsi untuk baca data mock
function baca_mock_mesin($file) {
    if (!file_exists($file)) {
        return ['users' => []];
    }
    return json_decode(file_get_contents($file), true);
}

// Fungsi untuk cek fingerprint di mesin
function cek_fingerprint_di_mesin($no_absen, $mock_data) {
    foreach ($mock_data['users'] as $user) {
        if ($user['PIN'] == $no_absen) {
            return [
                'ada' => true,
                'nama_di_mesin' => $user['Name'],
                'pin2' => $user['PIN2'],
                'jumlah_fingerprint' => count($user['Fingerprints'])
            ];
        }
    }
    return ['ada' => false];
}

// Baca data mock
$mock_data = baca_mock_mesin($mock_file);

echo "<h3>Status Fingerprint 3 Karyawan</h3>";
echo "<p>Data diambil dari file mock: " . basename($mock_file) . "</p>";

echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>ID</th><th>No Absen</th><th>Nama</th><th>OS</th><th>Status di Mesin</th><th>PIN2</th><th>Jml Fingerprint</th></tr>";

foreach ($karyawan_list as $karyawan) {
    $status = cek_fingerprint_di_mesin($karyawan['no_absen'], $mock_data);
    
    echo "<tr>";
    echo "<td align='center'>" . $karyawan['id'] . "</td>";
    echo "<td align='center'><strong>" . $karyawan['no_absen'] . "</strong></td>";
    echo "<td>" . $karyawan['nama'] . "</td>";
    echo "<td align='center'>" . $karyawan['os'] . "</td>";
    
    if ($status['ada']) {
        echo "<td align='center' style='background-color:#d4edda; color:#155724;'><strong>SUDAH ADA</strong></td>";
        echo "<td align='center'>" . ($status['pin2'] ?: '-') . "</td>";
        echo "<td align='center'>" . $status['jumlah_fingerprint'] . "</td>";
    } else {
        echo "<td align='center' style='background-color:#f8d7da; color:#721c24;'><strong>BELUM ADA</strong></td>";
        echo "<td align='center'>-</td>";
        echo "<td align='center'>0</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

// Info file mock
echo "<h4>Info File Mock:</h4>";
echo "<p>Path: " . realpath($mock_file) . "</p>";
echo "<p>Jumlah total user di mock: " . count($mock_data['users']) . "</p>";

// Jika file mock kosong, kita isi dengan data karyawan ini untuk simulasi
if (empty($mock_data['users'])) {
    echo "<h4 style='color:orange;'>File mock kosong. Mengisi dengan data karyawan...</h4>";
    
    $mock_data['users'] = [];
    foreach ($karyawan_list as $karyawan) {
        $mock_data['users'][] = [
            'PIN' => $karyawan['no_absen'],
            'PIN2' => '', // PIN2 kosong
            'Name' => $karyawan['nama'],
            'Fingerprints' => ["template_" . $karyawan['no_absen'] . "_base64=="]
        ];
    }
    
    file_put_contents($mock_file, json_encode($mock_data, JSON_PRETTY_PRINT));
    echo "<p>Data mock telah diisi. Refresh halaman untuk melihat status.</p>";
}

echo "<hr>";
echo "<h4>Cara test hapus:</h4>";
echo "<ol>";
echo "<li>Pilih salah satu karyawan (misal no absen 251)</li>";
echo "<li>Hapus karyawan tersebut via web interface</li>";
echo "<li>Refresh halaman ini untuk lihat apakah karyawan lain (252, 253) masih ada di mock</li>";
echo "</ol>";

?>