<?php
// Tambah index DB yang dipakai Tarik Data (sekali saja, aman diulang).
// Jalankan dari browser setelah login, atau via CLI: php perf_indexes.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);

if (PHP_SAPI !== 'cli') {
    session_start();
    if (empty($_SESSION['iduser'])) {
        http_response_code(403);
        exit("Login dulu.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
}

include __DIR__ . '/koneksi.php';
if (!isset($koneksi) || !$koneksi) exit("Koneksi DB gagal.\n");

$wanted = [
    'tb_record' => [
        'idx_record_tgl'        => '(tgl)',
        'idx_record_user_tgl'   => '(userid, tgl)',
        'idx_record_dev_user'   => '(source_device_id, userid, tgl)',
        'idx_record_tgl_time'   => '(tgl, detail_waktu)',
    ],
    'tb_realisasi_detail' => [
        'idx_rd_realisasi' => '(id_realisasi)',
    ],
    'ms_karyawan' => [
        'idx_kar_noabsen' => '(no_absen)',
    ],
];

foreach ($wanted as $table => $indexes) {
    $has = [];
    $q = @$koneksi->query("SHOW INDEX FROM `$table`");
    if (!$q) { echo "[$table] tidak ada / gagal dibaca, dilewati.\n"; continue; }
    while ($r = $q->fetch_assoc()) $has[$r['Key_name']] = true;

    foreach ($indexes as $name => $cols) {
        if (isset($has[$name])) { echo "[$table] $name sudah ada.\n"; continue; }
        $t0 = microtime(true);
        echo "[$table] membuat $name $cols ... ";
        $ok = @$koneksi->query("ALTER TABLE `$table` ADD INDEX `$name` $cols");
        echo ($ok ? 'OK' : 'GAGAL: ' . $koneksi->error) . ' (' . round(microtime(true) - $t0, 2) . " dtk)\n";
        if (function_exists('ob_flush')) { @ob_flush(); @flush(); }
    }
}

echo "Selesai.\n";
