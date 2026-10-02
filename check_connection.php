<?php
/**
 * Cek Koneksi Database Web dengan File Mock
 */

include "koneksi.php";

// Data 3 karyawan target
$target_ids = [251, 252, 253];

echo "<h3>Cek Koneksi Data Karyawan Web ↔ File Mock</h3>";

// 1. Cek di database web
echo "<h4>1. Data di Database Web (ms_karyawan):</h4>";
$sql = "SELECT id_karyawan, no_absen, nama_karyawan, status_karyawan FROM ms_karyawan WHERE id_karyawan IN (" . implode(',', $target_ids) . ") ORDER BY id_karyawan";
$result = $koneksi->query($sql);

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>No Absen</th><th>Nama</th><th>Status</th><th>Ada di DB?</th></tr>";

$karyawan_data = [];
while ($row = $result->fetch_assoc()) {
    $karyawan_data[$row['no_absen']] = $row;
    echo "<tr>";
    echo "<td align='center'>" . $row['id_karyawan'] . "</td>";
    echo "<td align='center'><strong>" . $row['no_absen'] . "</strong></td>";
    echo "<td>" . $row['nama_karyawan'] . "</td>";
    echo "<td align='center'>" . $row['status_karyawan'] . "</td>";
    echo "<td align='center' style='background-color:#d4edda; color:#155724;'>✓ ADA</td>";
    echo "</tr>";
}

// Cek jika ada yang tidak ditemukan
foreach ($target_ids as $id) {
    $found = false;
    foreach ($karyawan_data as $k) {
        if ($k['id_karyawan'] == $id) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "<tr>";
        echo "<td align='center'>" . $id . "</td>";
        echo "<td align='center'>?</td>";
        echo "<td>?</td>";
        echo "<td>?</td>";
        echo "<td align='center' style='background-color:#f8d7da; color:#721c24;'>✗ TIDAK ADA</td>";
        echo "</tr>";
    }
}
echo "</table>";

// 2. Cek di file mock
echo "<h4>2. Data di File Mock Mesin (mock_device_data.json):</h4>";

$mock_file = __DIR__ . '/mock_device_data.json';
if (!file_exists($mock_file)) {
    echo "<p style='color:red;'>File mock tidak ditemukan.</p>";
} else {
    $mock_data = json_decode(file_get_contents($mock_file), true);
    
    echo "<p>Jumlah total user di mock: " . count($mock_data['users']) . "</p>";
    
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>PIN</th><th>PIN2</th><th>Name</th><th>Fingerprints</th><th>Match dengan DB?</th></tr>";
    
    foreach ($mock_data['users'] as $user) {
        $match = false;
        $match_info = '';
        
        // Cek apakah PIN ini ada di data karyawan kita
        foreach ($karyawan_data as $k) {
            if ($k['no_absen'] == $user['PIN']) {
                $match = true;
                $match_info = "✓ Match: " . $k['nama_karyawan'];
                break;
            }
        }
        
        // Juga cek PIN2
        $pin2_match = false;
        foreach ($karyawan_data as $k) {
            if ($k['no_absen'] == $user['PIN2']) {
                $pin2_match = true;
                $match_info .= " (PIN2 match dengan " . $k['nama_karyawan'] . ")";
                break;
            }
        }
        
        echo "<tr>";
        echo "<td align='center'>" . $user['PIN'] . "</td>";
        echo "<td align='center'>" . ($user['PIN2'] ?: '-') . "</td>";
        echo "<td>" . $user['Name'] . "</td>";
        echo "<td align='center'>" . (count($user['Fingerprints']) ? count($user['Fingerprints']) . " template" : "0") . "</td>";
        
        if ($match) {
            echo "<td align='center' style='background-color:#d4edda; color:#155724;'>" . $match_info . "</td>";
        } elseif ($pin2_match) {
            echo "<td align='center' style='background-color:#fff3cd; color:#856404;'>⚠ PIN2 match</td>";
        } else {
            echo "<td align='center'>-</td>";
        }
        
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Ringkasan koneksi
echo "<h4>3. Ringkasan Koneksi:</h4>";

echo "<ul>";
foreach ($target_ids as $id) {
    // Cari data karyawan ini
    $found_in_db = false;
    $no_absen = '';
    $nama = '';
    
    foreach ($karyawan_data as $k) {
        if ($k['id_karyawan'] == $id) {
            $found_in_db = true;
            $no_absen = $k['no_absen'];
            $nama = $k['nama_karyawan'];
            break;
        }
    }
    
    if (!$found_in_db) {
        echo "<li>ID $id: <span style='color:red;'>Tidak ditemukan di database web</span></li>";
        continue;
    }
    
    // Cek di mock
    $found_in_mock = false;
    if (isset($mock_data)) {
        foreach ($mock_data['users'] as $user) {
            if ($user['PIN'] == $no_absen) {
                $found_in_mock = true;
                break;
            }
        }
    }
    
    if ($found_in_mock) {
        echo "<li>ID $id ($no_absen - $nama): <span style='color:green;'>✓ Terhubung ke mock (PIN utama)</span></li>";
    } else {
        echo "<li>ID $id ($no_absen - $nama): <span style='color:orange;'>⚠ Tidak ada di mock (belum sync)</span></li>";
    }
}
echo "</ul>";

echo "<hr>";
echo "<h4>Status Test Hapus:</h4>";
echo "<p>Jika semua 3 karyawan sudah terhubung ke mock (PIN utama), maka:</p>";
echo "<ul>";
echo "<li>Hapus karyawan 251 → hanya PIN '251' yang akan dihapus dari mock</li>";
echo "<li>PIN '252' dan '253' tetap aman</li>";
echo "<li>PIN2 yang mungkin match (misal PIN2='251' di user lain) tidak akan terhapus</li>";
echo "</ul>";

?>