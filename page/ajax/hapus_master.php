<?php
include "../../koneksi.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $route = $_POST['route'] ?? '';

    if (!$id || !$route) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Mapping route/entity to table and PK
    $mapping = [
        'bagian' => ['table' => 'ms_departmen', 'pk' => 'id_departmen'],
        'subbagian' => ['table' => 'ms_sub_department', 'pk' => 'id_sub_department'],
        'jabatan' => ['table' => 'ms_jabatan', 'pk' => 'id_jabatan'],
        'jadwal' => ['table' => 'tb_jadwal', 'pk' => 'id_jadwal'],
        'agama' => ['table' => 'ms_agama', 'pk' => 'id_agama'],
        'golongan' => ['table' => 'ms_golongan', 'pk' => 'id_golongan'],
        'status_kawin' => ['table' => 'ms_status_kawin', 'pk' => 'id_status_kawin'],
        'os_dhk' => ['table' => 'ms_os_dhk', 'pk' => 'id_os_dhk'],
    ];

    if (!isset($mapping[$route])) {
        echo json_encode(['success' => false, 'message' => 'Unknown entity']);
        exit;
    }

    $table = $mapping[$route]['table'];
    $pk = $mapping[$route]['pk'];

    // Attempt delete
    try {
        $sql = $koneksi->query("DELETE FROM $table WHERE $pk = '$id'");
        if ($sql) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $koneksi->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Data sedang digunakan dan tidak bisa dihapus.']);
    }
}
?>
