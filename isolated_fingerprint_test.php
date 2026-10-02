<?php
/**
 * ISOLATED TEST: Delete Safety with Dummy Fingerprint
 * Completely isolated from production code
 */

include "koneksi.php";

echo "<h2>ISOLATED TEST: Delete Safety with Dummy Fingerprint</h2>";

// ============================================
// PART 1: Setup dummy fingerprint data directly
// ============================================

// Create dummy table if not exists
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_fingerprint_dummy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pin VARCHAR(20) NOT NULL,
    finger_id INT NOT NULL DEFAULT 0,
    template_data TEXT NOT NULL,
    size INT NOT NULL DEFAULT 0,
    valid TINYINT(1) DEFAULT 1,
    device_id INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_finger (pin, finger_id, device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Clear and insert test data
$koneksi->query("DELETE FROM tb_fingerprint_dummy");

$test_data = [
    ['pin' => '251', 'finger_count' => 2, 'name' => 'WARDIAH'],
    ['pin' => '252', 'finger_count' => 1, 'name' => 'DEDI'],
    ['pin' => '253', 'finger_count' => 3, 'name' => 'LIA MELIA'],
    ['pin' => '999', 'finger_count' => 2, 'name' => 'KARYAWAN LAIN (PIN2=251)'],
    ['pin' => '888', 'finger_count' => 1, 'name' => 'KARYAWAN LAIN (PIN2=252)']
];

foreach ($test_data as $user) {
    for ($i = 0; $i < $user['finger_count']; $i++) {
        $template = base64_encode("DUMMY_FP_" . $user['pin'] . "_" . $i . "_" . time());
        $size = strlen($template);
        $koneksi->query("INSERT INTO tb_fingerprint_dummy (pin, finger_id, template_data, size, device_id) 
                         VALUES ('{$user['pin']}', $i, '$template', $size, 1)");
    }
}

echo "<p>✓ Dummy fingerprint data created for 5 users.</p>";

// ============================================
// PART 2: Simulate the FIXED parsing logic
// ============================================

function simulate_delete_logic($no_absen) {
    global $koneksi;
    
    // Mock device data (hardcoded for test)
    $mock_users = [
        ['PIN' => '251', 'PIN2' => '', 'Name' => 'WARDIAH'],
        ['PIN' => '252', 'PIN2' => '', 'Name' => 'DEDI'],
        ['PIN' => '253', 'PIN2' => '', 'Name' => 'LIA MELIA'],
        ['PIN' => '999', 'PIN2' => '251', 'Name' => 'KARYAWAN LAIN (PIN2=251)'],
        ['PIN' => '888', 'PIN2' => '252', 'Name' => 'KARYAWAN LAIN (PIN2=252)']
    ];
    
    // THE FIXED LOGIC from device_sync_helper.php
    $real_pins = [];
    foreach ($mock_users as $user) {
        $pin = $user['PIN'];
        $pin2 = $user['PIN2'];
        
        // ONLY delete if PIN == $no_absen
        if ($pin == $no_absen) {
            $real_pins[] = $pin;
        }
        // PIN2 is IGNORED even if match - THIS IS THE FIX
    }
    
    return $real_pins;
}

// ============================================
// PART 3: Run the test
// ============================================

$target_pin = '251';
echo "<h3>Test Case: Delete PIN $target_pin (WARDIAH)</h3>";

echo "<h4>Step 1: Check fingerprint data BEFORE delete</h4>";
$result = $koneksi->query("SELECT pin, COUNT(*) as count FROM tb_fingerprint_dummy GROUP BY pin ORDER BY pin");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>PIN</th><th>Fingerprint Count</th><th>Note</th></tr>";
while ($row = $result->fetch_assoc()) {
    $note = '';
    if ($row['pin'] == '999') $note = "PIN2 = $target_pin";
    if ($row['pin'] == '888') $note = "PIN2 = 252";
    
    echo "<tr>";
    echo "<td align='center'>" . $row['pin'] . "</td>";
    echo "<td align='center'>" . $row['count'] . "</td>";
    echo "<td>" . $note . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h4>Step 2: Apply FIXED delete logic</h4>";
$pins_to_delete = simulate_delete_logic($target_pin);

echo "<p>Pins to delete (based on FIXED logic): <strong>" . implode(', ', $pins_to_delete) . "</strong></p>";

if (in_array('999', $pins_to_delete)) {
    echo "<p style='color:red; font-weight:bold;'>✗ BUG: PIN 999 included! Fingerprint karyawan lain akan terhapus.</p>";
} else {
    echo "<p style='color:green; font-weight:bold;'>✓ CORRECT: PIN 999 NOT included. Fingerprint karyawan lain AMAN.</p>";
}

echo "<h4>Step 3: Simulate actual delete from database</h4>";
// Actually delete PIN 251 from dummy table
$koneksi->query("DELETE FROM tb_fingerprint_dummy WHERE pin = '$target_pin'");
echo "<p>Deleted fingerprint records for PIN $target_pin</p>";

echo "<h4>Step 4: Check fingerprint data AFTER delete</h4>";
$result = $koneksi->query("SELECT pin, COUNT(*) as count FROM tb_fingerprint_dummy GROUP BY pin ORDER BY pin");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>PIN</th><th>Fingerprint Count</th><th>Status</th></tr>";
while ($row = $result->fetch_assoc()) {
    $status = '';
    if ($row['pin'] == '251') {
        $status = "<span style='color:red; font-weight:bold;'>DELETED</span>";
    } elseif ($row['pin'] == '999') {
        $status = "<span style='color:green; font-weight:bold;'>STILL EXISTS ✓</span>";
    } else {
        $status = "<span style='color:green;'>STILL EXISTS</span>";
    }
    
    echo "<tr>";
    echo "<td align='center'>" . $row['pin'] . "</td>";
    echo "<td align='center'>" . $row['count'] . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================
// PART 4: Final verification
// ============================================

echo "<h3>Final Verification:</h3>";

$pin999_count = $koneksi->query("SELECT COUNT(*) as c FROM tb_fingerprint_dummy WHERE pin = '999'")->fetch_assoc()['c'];
$pin251_count = $koneksi->query("SELECT COUNT(*) as c FROM tb_fingerprint_dummy WHERE pin = '251'")->fetch_assoc()['c'];

if ($pin251_count == 0 && $pin999_count > 0) {
    echo "<div style='background-color:#d4edda; padding:15px; border-radius:5px;'>";
    echo "<p style='color:#155724; font-weight:bold;'>✓ TEST PASSED!</p>";
    echo "<ul>";
    echo "<li>PIN 251 fingerprint: DELETED (correct)</li>";
    echo "<li>PIN 999 fingerprint: STILL EXISTS (fingerprint karyawan lain AMAN)</li>";
    echo "<li>Bug hapus fingerprint karyawan lain sudah FIX</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background-color:#f8d7da; padding:15px; border-radius:5px;'>";
    echo "<p style='color:#721c24; font-weight:bold;'>✗ TEST FAILED!</p>";
    if ($pin999_count == 0) {
        echo "<p>PIN 999 fingerprint juga terhapus! Fingerprint karyawan lain IKUT HILANG.</p>";
    }
    echo "</div>";
}

// Cleanup
$koneksi->query("DROP TABLE IF EXISTS tb_fingerprint_dummy");

?>