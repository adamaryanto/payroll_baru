<?php
/**
 * Setup Fingerprint Dummy System
 */

include "koneksi.php";

echo "<h3>Setting up Fingerprint Dummy System</h3>";

// 1. Create fingerprint template table
$sql1 = "CREATE TABLE IF NOT EXISTS tb_fingerprint_dummy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pin VARCHAR(20) NOT NULL,
    finger_id INT NOT NULL DEFAULT 0,
    template_data TEXT NOT NULL,
    size INT NOT NULL DEFAULT 0,
    valid TINYINT(1) DEFAULT 1,
    device_id INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_finger (pin, finger_id, device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($koneksi->query($sql1)) {
    echo "<p>✓ Table tb_fingerprint_dummy created.</p>";
} else {
    echo "<p style='color:red;'>✗ Failed to create table: " . $koneksi->error . "</p>";
}

// 2. Insert dummy fingerprint data for our test users
$test_users = [
    ['pin' => '251', 'name' => 'WARDIAH', 'finger_count' => 2],
    ['pin' => '252', 'name' => 'DEDI', 'finger_count' => 1],
    ['pin' => '253', 'name' => 'LIA MELIA', 'finger_count' => 3],
    ['pin' => '999', 'name' => 'KARYAWAN LAIN (PIN2=251)', 'finger_count' => 2],
    ['pin' => '888', 'name' => 'KARYAWAN LAIN (PIN2=252)', 'finger_count' => 1]
];

foreach ($test_users as $user) {
    // Delete existing data
    $koneksi->query("DELETE FROM tb_fingerprint_dummy WHERE pin = '{$user['pin']}'");
    
    // Insert new fingerprint templates
    for ($finger_id = 0; $finger_id < $user['finger_count']; $finger_id++) {
        $template = base64_encode("DUMMY_FINGERPRINT_" . $user['pin'] . "_" . $finger_id . "_" . time());
        $size = strlen($template);
        
        $sql = "INSERT INTO tb_fingerprint_dummy (pin, finger_id, template_data, size, device_id) 
                VALUES ('{$user['pin']}', $finger_id, '$template', $size, 1)";
        
        if ($koneksi->query($sql)) {
            echo "<div style='background:#e0f7fa; padding:5px; margin:2px;'>";
            echo "✓ Fingerprint for PIN {$user['pin']} ({$user['name']}) finger $finger_id created.";
            echo "</div>";
        }
    }
}

// 3. Create override functions file
$override_content = '<?php
/**
 * Override Fingerprint Functions for Dummy System
 */

// Override get_fingerprint_templates
function get_fingerprint_templates($source_device_ip, $source_port, $comm_key, $pin) {
    global $koneksi;
    
    $templates = [];
    $result = $koneksi->query("SELECT finger_id, template_data, size, valid 
                               FROM tb_fingerprint_dummy 
                               WHERE pin = \'" . $koneksi->real_escape_string($pin) . "\' 
                               AND device_id = 1 
                               ORDER BY finger_id");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $templates[] = [
                \'finger_id\' => $row[\'finger_id\'],
                \'template\' => $row[\'template_data\'],
                \'size\' => $row[\'size\'],
                \'valid\' => $row[\'valid\']
            ];
        }
    }
    
    return $templates;
}

// Override set_fingerprint_template
function set_fingerprint_template($target_device_ip, $target_port, $comm_key, $pin, $finger_id, $template_data, $size = 0, $valid = 1) {
    global $koneksi;
    
    if (empty($template_data)) return false;
    
    if ($size == 0) {
        $size = strlen(base64_decode($template_data));
    }
    
    // Store in dummy table
    $sql = "INSERT INTO tb_fingerprint_dummy (pin, finger_id, template_data, size, valid, device_id) 
            VALUES (\'" . $koneksi->real_escape_string($pin) . "\', 
                    " . (int)$finger_id . ", 
                    \'" . $koneksi->real_escape_string($template_data) . "\', 
                    " . (int)$size . ", 
                    " . (int)$valid . ", 
                    1)
            ON DUPLICATE KEY UPDATE 
            template_data = VALUES(template_data),
            size = VALUES(size),
            valid = VALUES(valid)";
    
    return $koneksi->query($sql);
}

// Log function for testing
function log_fingerprint_activity($action, $pin, $finger_id, $message) {
    file_put_contents(__DIR__ . \'/fingerprint_test.log\', 
        date(\'Y-m-d H:i:s\') . " | $action | PIN:$pin | Finger:$finger_id | $message\n", 
        FILE_APPEND);
}

echo "<!-- Fingerprint dummy functions loaded -->\n";
?>';

file_put_contents(__DIR__ . '/fingerprint_dummy_override.php', $override_content);
echo "<p>✓ Fingerprint override functions file created.</p>";

// 4. Summary
echo "<h4>Setup Summary:</h4>";
echo "<ul>";
echo "<li>✓ Table tb_fingerprint_dummy created</li>";
echo "<li>✓ Dummy fingerprint data inserted for 5 users</li>";
echo "<li>✓ Override functions file created</li>";
echo "</ul>";

echo "<h4>Test Data Created:</h4>";
$result = $koneksi->query("SELECT pin, COUNT(*) as finger_count FROM tb_fingerprint_dummy GROUP BY pin");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>PIN</th><th>Fingerprint Count</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td align='center'>{$row['pin']}</td><td align='center'>{$row['finger_count']}</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li>Include 'fingerprint_dummy_override.php' before device_sync_helper.php</li>";
echo "<li>Test hapus WARDIAH (PIN 251)</li>";
echo "<li>Check if fingerprint of PIN 999 (with PIN2=251) remains</li>";
echo "</ol>";

?>