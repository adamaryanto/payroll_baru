<?php
// Endpoint AJAX tahapan Tarik Data realisasi (dipakai progress bar + % di kelola.php).
// step=pull : tarik mesin -> tb_record            (return saved/online)
// step=sync : sinkron baris rencana               (return count)
// step=tdr  : proses tap luar rencana             (return added)
// Selalu JSON. Butuh login.
error_reporting(0);
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['iduser'])) { echo json_encode(['ok' => false, 'msg' => 'Sesi habis, login ulang.']); exit; }
include "../../koneksi.php";
include_once __DIR__ . '/../../device_sync_helper.php';
require_once __DIR__ . '/sync_lib.php';

function jexit($arr) { echo json_encode($arr); exit; }

$id = (int)($_REQUEST['id'] ?? 0);
$step = strtolower(trim((string)($_REQUEST['step'] ?? '')));
// Override tanggal manual (datepicker); null = otomatis ikut tgl realisasi
$tgl_ov = (isset($_REQUEST['tgl']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_REQUEST['tgl'])) ? (string)$_REQUEST['tgl'] : null;
if ($id <= 0) jexit(['ok' => false, 'msg' => 'ID realisasi tidak valid.']);

try {
    if ($step === 'pull') {
        $qr = $koneksi->query("SELECT tgl_realisasi FROM tb_realisasi WHERE id_realisasi = '$id'");
        $dr = $qr ? $qr->fetch_assoc() : null;
        if (!$dr || empty($dr['tgl_realisasi'])) jexit(['ok' => false, 'msg' => 'Tanggal realisasi tidak ditemukan.']);
        $t0 = $tgl_ov ?: $dr['tgl_realisasi'];
        $t1 = date('Y-m-d', strtotime('+1 day', strtotime($t0)));
        $t0s = microtime(true);
        $pr = function_exists('pull_attendance_from_all_devices') ? pull_attendance_from_all_devices($koneksi, $t0, $t1) : null;
        if (function_exists('log_sync_activity')) {
            try {
                if (function_exists('create_sync_log_table')) create_sync_log_table($koneksi);
                foreach (($pr['per_device'] ?? []) as $dev_id => $rs) {
                    if (!empty($rs['online'])) log_sync_activity($koneksi, 'attendance_pull', $dev_id, null, 'success', "Pulled {$rs['records_pulled']} records, saved {$rs['records_saved']} (via realisasi ajax)");
                }
            } catch (Throwable $e) {}
        }
        jexit(['ok' => true,
            'saved' => (int)($pr['records_saved'] ?? 0),
            'online' => (int)($pr['online_devices'] ?? 0),
            'total_dev' => (int)($pr['total_devices'] ?? 0),
            'tgl' => $t0,
            'secs' => round(microtime(true) - $t0s, 2)]);
    }

    if ($step === 'sync') {
        $t0s = microtime(true);
        $count = syncRealisasiData($koneksi, $id, null, false, $tgl_ov);
        jexit(['ok' => true, 'count' => (int)$count, 'secs' => round(microtime(true) - $t0s, 2), 'tgl' => $tgl_ov]);
    }

    if ($step === 'tdr') {
        if (!function_exists('realisasi_proses_tdr')) jexit(['ok' => false, 'msg' => 'Fungsi TDR tidak tersedia.']);
        $t0s = microtime(true);
        $r = realisasi_proses_tdr($koneksi, $id, $tgl_ov);
        jexit(['ok' => true, 'added' => (int)($r['added'] ?? 0), 'secs' => round(microtime(true) - $t0s, 2), 'tgl' => $tgl_ov]);
    }

    jexit(['ok' => false, 'msg' => 'Step tidak dikenal.']);
} catch (Throwable $e) {
    jexit(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()]);
}
