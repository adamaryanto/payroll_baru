<?php
// Hapus by-nama via popup: preview kandidat -> eksekusi (tanpa pindah halaman).
session_start();
include "../../koneksi.php";
include "../../device_sync_helper.php";
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);
if (!$id && ($action === 'preview' || $action === 'exec')) { echo json_encode(['success'=>false,'msg'=>'ID kosong']); exit; }
if (!$id) { /* bulk actions pakai ids/items */ }
$q = $id ? $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1") : null;
$emp = ($q && $id) ? $q->fetch_assoc() : null;
if (!$emp && ($action === 'preview' || $action === 'exec')) { echo json_encode(['success'=>false,'msg'=>'Karyawan tidak ditemukan']); exit; }
$nama = $emp['nama_karyawan'] ?? '';

if ($action === 'preview') {
    $found = function_exists('find_users_by_name') ? find_users_by_name($koneksi, $nama) : [];
    $total = 0;
    foreach ($found as $f) $total += count($f['matches']);
    echo json_encode(['success'=>true,'nama'=>$nama,'no_absen'=>$emp['no_absen'],'total'=>$total,'devices'=>$found]);
    exit;
}

if ($action === 'exec') {
    $dbOnly = !empty($_POST['db_only']);
    $targets = [];
    foreach ((array)($_POST['targets'] ?? []) as $t) {
        $parts = explode(':', (string)$t);
        if (count($parts) < 2) continue;
        $did = trim($parts[0]); $pin = trim($parts[1]); $pin2 = trim($parts[2] ?? '');
        if ($did === '' || $pin === '' || !ctype_digit($did) || !is_numeric($pin)) continue;
        $targets[$did][$pin] = ['pin'=>$pin,'pin2'=>$pin2];
    }
    foreach ($targets as $did => $map) $targets[$did] = array_values($map);
    $results = [];
    if (!$dbOnly && !empty($targets)) {
        $results = delete_karyawan_from_devices($koneksi, $nama, false, $targets);
        foreach ($results as $did => $r) {
            if ($did === '_dry_run_mode' || $did === '_dry_run_info') continue;
            log_sync_activity($koneksi, 'karyawan_delete_popup', $did, $id, $r['status'] ?? 'error', "Hapus '$nama': " . ($r['message'] ?? ''));
        }
    }
    $del = $koneksi->query("DELETE FROM ms_karyawan WHERE id_karyawan=$id");
    if ($del) hapus_karyawan_dari_grup($koneksi, $id);
    echo json_encode(['success'=>(bool)$del,'results'=>$results,'db_only'=>$dbOnly,
        'msg'=>$del ? 'Data karyawan berhasil dihapus dari database.' : 'Gagal hapus database']);
    exit;
}

if ($action === 'delete_direct') {
        // Hapus langsung by NAMA (baca PIN+PIN2 dari mesin sekali, lalu hapus cepat by PIN2).
    $dbOnly = !empty($_POST['db_only']);
    $targets = [];
    $pin2_owners = [];
    if (!$dbOnly) {
        $found = function_exists('find_users_by_name') ? find_users_by_name($koneksi, $nama, $pin2_owners) : [];
        foreach ($found as $did => $d) {
            if (empty($d['online'])) continue;
            $list = [];
            foreach (($d['matches'] ?? []) as $m) {
                $p = trim((string)($m['pin'] ?? ''));
                if ($p === '' || !is_numeric($p)) continue;
                $list[] = ['pin' => $p, 'pin2' => trim((string)($m['pin2'] ?? ''))];
            }
            if (!empty($list)) $targets[$did] = $list;
        }
        if (empty($targets)) {
            echo json_encode(['success'=>false,'msg'=>"Nama '$nama' tidak ditemukan di mesin yang online. Pilih 'DB Saja' bila hanya ingin hapus database."]);
            exit;
        }
        // Deteksi tabrakan PIN2: kalau PIN2 (no. absen) dipakai orang lain di mesin lain
        $collisions = [];
        foreach ($targets as $did => $list) {
            foreach ($list as $m) {
                $p2 = $m['pin2'];
                if ($p2 === '') continue;
                $owners = array_values(array_unique($pin2_owners[$p2] ?? []));
                $others = [];
                foreach ($owners as $on) { if (strcasecmp(trim($on), $nama) !== 0) $others[] = $on; }
                if (!empty($others)) $collisions[$p2] = array_values(array_unique($others));
            }
        }
        if (!empty($collisions) && empty($_POST['confirm_pin2'])) {
            $detail = '';
            foreach ($collisions as $p2 => $names) {
                $detail .= "PIN2 $p2 dipakai juga oleh: " . implode(', ', $names) . ". ";
            }
            echo json_encode(['success'=>false,'need_confirm'=>true,'collisions'=>$collisions,
                'msg'=>"Peringatan: PIN2 (no. absen) yang akan dihapus dipakai orang lain di mesin lain. $detail Konfirmasi untuk tetap hapus (orang lain itu juga akan terhapus)."]);
            exit;
        }
    }
    $results = [];
    if (!$dbOnly && !empty($targets)) {
        $results = delete_karyawan_from_devices($koneksi, $nama, false, $targets, true);
        foreach ($results as $did => $r) {
            if ($did === '_dry_run_mode' || $did === '_dry_run_info') continue;
            log_sync_activity($koneksi, 'karyawan_delete_direct', $did, $id, $r['status'] ?? 'error', "Hapus langsung '$nama': " . ($r['message'] ?? ''));
        }
    }
    $del = $koneksi->query("DELETE FROM ms_karyawan WHERE id_karyawan=$id");
    if ($del) hapus_karyawan_dari_grup($koneksi, $id);
    echo json_encode(['success'=>(bool)$del,'results'=>$results,'db_only'=>$dbOnly,
        'msg'=>$del ? 'Data karyawan berhasil dihapus.' : 'Gagal hapus database']);
    exit;
}

if ($action === 'preview_bulk') {
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) { echo json_encode(['success'=>false,'msg'=>'Tidak ada yang dipilih']); exit; }
    $list = [];
    foreach ($ids as $kid) {
        $q = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$kid LIMIT 1");
        $emp = $q ? $q->fetch_assoc() : null;
        if (!$emp) continue;
        $found = function_exists('find_users_by_name') ? find_users_by_name($koneksi, $emp['nama_karyawan']) : [];
        $total = 0;
        foreach ($found as $f) $total += count($f['matches']);
        $list[] = ['id'=>$emp['id_karyawan'],'nama'=>$emp['nama_karyawan'],'no_absen'=>$emp['no_absen'],'total'=>$total,'devices'=>$found];
    }
    echo json_encode(['success'=>true,'employees'=>$list]);
    exit;
}

if ($action === 'exec_bulk') {
    $items = (array)($_POST['items'] ?? []);
    $dbOnly = !empty($_POST['db_only']);
    $per = [];
    foreach ($items as $kid => $tlist) {
        $kid = intval($kid);
        $q = $koneksi->query("SELECT id_karyawan, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$kid LIMIT 1");
        $emp = $q ? $q->fetch_assoc() : null;
        if (!$emp) { $per[] = ['id'=>$kid,'nama'=>'?','db_deleted'=>false,'results'=>[],'msg'=>'Tidak ada di DB']; continue; }
        $targets = [];
        foreach ((array)$tlist as $t) {
            $parts = explode(':', (string)$t);
            if (count($parts) < 2) continue;
            $did = trim($parts[0]); $pin = trim($parts[1]); $pin2 = trim($parts[2] ?? '');
            if ($did === '' || $pin === '' || !ctype_digit($did) || !is_numeric($pin)) continue;
            $targets[$did][$pin] = ['pin'=>$pin,'pin2'=>$pin2];
        }
        foreach ($targets as $did => $map) $targets[$did] = array_values($map);
        $results = [];
        if (!$dbOnly && !empty($targets)) {
            $results = delete_karyawan_from_devices($koneksi, $emp['nama_karyawan'], false, $targets);
            foreach ($results as $did => $r) {
                if ($did === '_dry_run_mode' || $did === '_dry_run_info') continue;
                log_sync_activity($koneksi, 'karyawan_bulk_delete_popup', $did, $kid, $r['status'] ?? 'error', "Hapus '{$emp['nama_karyawan']}': " . ($r['message'] ?? ''));
            }
        }
        $del = $koneksi->query("DELETE FROM ms_karyawan WHERE id_karyawan=$kid");
        if ($del) hapus_karyawan_dari_grup($koneksi, $kid);
        $per[] = ['id'=>$kid,'nama'=>$emp['nama_karyawan'],'db_deleted'=>(bool)$del,'results'=>$results];
    }
    echo json_encode(['success'=>true,'per'=>$per,'db_only'=>$dbOnly]);
    exit;
}

echo json_encode(['success'=>false,'msg'=>'Action tidak dikenal']);
