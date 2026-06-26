<?php
include "../../koneksi.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$value = isset($_POST['value']) ? trim($_POST['value']) : '';
$route = isset($_POST['route']) ? $_POST['route'] : '';
$extra = isset($_POST['extra']) ? $_POST['extra'] : [];

if ($value === '' || $route === '') {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$success = false;
$newId = 0;
$message = '';

// Mapping route to table, column, and extra data
switch ($route) {
    case 'agama':
        $sql = $koneksi->query("INSERT INTO ms_agama (agama) VALUES ('$value')");
        break;
    case 'golongan':
        $sql = $koneksi->query("INSERT INTO ms_golongan (golongan) VALUES ('$value')");
        break;
    case 'status_kawin':
        $sql = $koneksi->query("INSERT INTO ms_status_kawin (status_kawin) VALUES ('$value')");
        break;
    case 'os_dhk':
        $sql = $koneksi->query("INSERT INTO ms_os_dhk (OS_DHK) VALUES ('$value')");
        break;
    case 'jabatan':
        $sql = $koneksi->query("INSERT INTO ms_jabatan (jabatan, id_perusahaan) VALUES ('$value', '1')");
        break;
    case 'bagian':
        $sql = $koneksi->query("INSERT INTO ms_departmen (nama_departmen, id_perusahaan) VALUES ('$value', '1')");
        break;
    case 'subbagian':
        $sql = $koneksi->query("INSERT INTO ms_sub_department (nama_sub_department, id_perusahaan) VALUES ('$value', '1')");
        break;
    case 'jadwal':
        $sql = $koneksi->query("INSERT INTO tb_jadwal (keterangan, jam_masuk, jam_keluar, istirahat_masuk, istirahat_keluar) VALUES ('$value', '08:00:00', '17:00:00', '12:00:00', '13:00:00')");
        break;
    default:
        $message = 'Route tidak dikenal.';
        break;
}

if (isset($sql)) {
    if ($sql) {
        $success = true;
        $newId = $koneksi->insert_id;
    } else {
        $message = 'Gagal menyimpan ke database: ' . $koneksi->error;
    }
}

echo json_encode(['success' => $success, 'id' => $newId, 'value' => $value, 'message' => $message]);
