<?php
/**
 * Override Fingerprint Functions for Dummy System
 */

// Override get_fingerprint_templates
function get_fingerprint_templates($source_device_ip, $source_port, $comm_key, $pin) {
    global $koneksi;
    
    $templates = [];
    $result = $koneksi->query("SELECT finger_id, template_data, size, valid 
                               FROM tb_fingerprint_dummy 
                               WHERE pin = '" . $koneksi->real_escape_string($pin) . "' 
                               AND device_id = 1 
                               ORDER BY finger_id");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $templates[] = [
                'finger_id' => $row['finger_id'],
                'template' => $row['template_data'],
                'size' => $row['size'],
                'valid' => $row['valid']
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
            VALUES ('" . $koneksi->real_escape_string($pin) . "', 
                    " . (int)$finger_id . ", 
                    '" . $koneksi->real_escape_string($template_data) . "', 
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
    file_put_contents(__DIR__ . '/fingerprint_test.log', 
        date('Y-m-d H:i:s') . " | $action | PIN:$pin | Finger:$finger_id | $message\n", 
        FILE_APPEND);
}

echo "<!-- Fingerprint dummy functions loaded -->\n";
?>