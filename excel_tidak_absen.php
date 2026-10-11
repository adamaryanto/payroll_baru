<?php
include "koneksi.php";

$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Log_Tidak_Absen_" . $TanggalAwal . "_to_" . $TanggalAkhir . ".xls");
echo "<meta charset='UTF-8'>";

if (!function_exists('mangkir_tgl_aktif')) {
	function mangkir_tgl_aktif($dari, $sampai, $tglYmd) {
		if (!empty($dari) && strcmp($tglYmd, $dari) < 0) return false;
		if (!empty($sampai) && strcmp($tglYmd, $sampai) > 0) return false;
		return true;
	}
}

// Pastikan tabel pengganti ada (sama seperti di tarik-data.php)
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_ganti (
	id INT AUTO_INCREMENT PRIMARY KEY,
	tgl DATE NOT NULL,
	id_karyawan_absen INT NOT NULL DEFAULT 0,
	id_karyawan_ganti INT NOT NULL DEFAULT 0,
	upah_bayar DOUBLE NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_ganti (tgl, id_karyawan_absen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ===== Logika TIDAK ABSEN — sama persis seperti panel di tarik-data.php =====
$noshow = [];
if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
    $taps = [];
    $qt = $koneksi->query("SELECT userid, tgl, MIN(detail_waktu) AS first_tap FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' GROUP BY userid, tgl");
    if ($qt) while ($rt = $qt->fetch_assoc()) $taps[$rt['tgl'] . '|' . trim((string)$rt['userid'])] = $rt['first_tap'] ?? '';
    $mkMenit = 0; $mkDari = ''; $mkSampai = '';
    $qmD = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_menit'");
    if ($qmD && $qmD->num_rows > 0) {
        $qmV = $koneksi->query("SELECT mangkir_menit, mangkir_dari_tgl, mangkir_sampai_tgl FROM tb_denda LIMIT 1");
        $rmV = $qmV ? $qmV->fetch_assoc() : [];
        $mkMenit = max(0, (int)($rmV['mangkir_menit'] ?? 0));
        $mkDari = trim((string)($rmV['mangkir_dari_tgl'] ?? ''));
        $mkSampai = trim((string)($rmV['mangkir_sampai_tgl'] ?? ''));
    }
    $tgl_aktif = [];
    foreach ($taps as $tk => $tv) { $dd = explode('|', $tk, 2); $tgl_aktif[$dd[0]] = true; }
    ksort($tgl_aktif);
    $rosterShift = [];
    $qr0 = $koneksi->query("SELECT h.tgl_rkk AS tgl, TRIM(k.no_absen) AS pin, j.jam_masuk AS sm, j.jam_keluar AS sk FROM tb_rkk_detail rd JOIN tb_rkk h ON h.id_rkk=rd.id_rkk JOIN ms_karyawan k ON k.id_karyawan=rd.id_karyawan LEFT JOIN tb_jadwal j ON j.id_jadwal=rd.id_jadwal WHERE h.tgl_rkk BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
    if ($qr0) while ($rw0 = $qr0->fetch_assoc()) $rosterShift[$rw0['tgl'] . '|' . trim((string)$rw0['pin'])] = [$rw0['sm'], $rw0['sk']];
    if (count($tgl_aktif) > 0 && count($tgl_aktif) <= 7) {
        $qa = $koneksi->query("SELECT k.id_karyawan, k.no_absen, k.nama_karyawan, o.OS_DHK AS oslbl, d.nama_departmen AS dept, sd.nama_sub_department AS subdept, jm.jam_masuk AS m_masuk, jm.jam_keluar AS m_keluar FROM ms_karyawan k LEFT JOIN ms_os_dhk o ON o.id_os_dhk=k.id_os_dhk LEFT JOIN ms_departmen d ON d.id_departmen=k.id_departmen LEFT JOIN ms_sub_department sd ON sd.id_sub_department=k.id_sub_department LEFT JOIN tb_jadwal jm ON jm.id_jadwal=k.id_jadwal WHERE k.status_karyawan='Aktif' AND k.no_absen!='' AND k.no_absen IS NOT NULL ORDER BY k.nama_karyawan");
        if ($qa) while ($ra = $qa->fetch_assoc()) {
            $pinT = trim((string)$ra['no_absen']);
            foreach (array_keys($tgl_aktif) as $dt) {
                $tkey = $dt . '|' . $pinT;
                $sm = isset($rosterShift[$tkey]) && !empty($rosterShift[$tkey][0]) ? $rosterShift[$tkey][0] : ($ra['m_masuk'] ?? '');
                $sk = isset($rosterShift[$tkey]) && !empty($rosterShift[$tkey][1]) ? $rosterShift[$tkey][1] : ($ra['m_keluar'] ?? '');
                if (!isset($taps[$tkey])) {
                    $exp = '';
                    if ($sm !== '' && $sk !== '') {
                        $sec = strtotime($sk) - strtotime($sm);
                        if ($sec < 0) $sec += 86400;
                        $h = (int)($sec / 3600); $m = (int)(($sec % 3600) / 60);
                        $exp = $h . ' jam' . ($m > 0 ? ' ' . $m . ' mnt' : '') . ' (' . substr($sm, 0, 5) . '-' . substr($sk, 0, 5) . ')';
                    }
                    $noshow[] = ['tgl' => $dt, 'id_karyawan' => $ra['id_karyawan'], 'no_absen' => $ra['no_absen'], 'nama_karyawan' => $ra['nama_karyawan'], 'oslbl' => $ra['oslbl'], 'dept' => $ra['dept'], 'subdept' => $ra['subdept'], 'seharusnya' => $exp, 'ket' => 'Tidak absen - tidak ada tap hari ini'];
                } elseif ($mkMenit > 0 && !empty($taps[$tkey]) && mangkir_tgl_aktif($mkDari, $mkSampai, $dt)) {
                    $ft = substr(trim((string)$taps[$tkey]), 11, 5);
                    if ($sm !== '' && preg_match('/^\d{2}:\d{2}$/', $ft)) {
                        $dl = strtotime("1970-01-01 $ft") - strtotime("1970-01-01 " . substr($sm, 0, 5));
                        if ($dl > 43200) $dl -= 86400;
                        elseif ($dl < -43200) $dl += 86400;
                        $dlm = (int)($dl / 60);
                        if ($dlm > $mkMenit) {
                            $noshow[] = ['tgl' => $dt, 'id_karyawan' => $ra['id_karyawan'], 'no_absen' => $ra['no_absen'], 'nama_karyawan' => $ra['nama_karyawan'], 'oslbl' => $ra['oslbl'], 'dept' => $ra['dept'], 'subdept' => $ra['subdept'], 'seharusnya' => 'telat ' . $dlm . ' mnt (batas ' . $mkMenit . ' mnt)', 'ket' => 'Telat ' . $dlm . ' menit - dihitung mangkir'];
                        }
                    }
                }
            }
        }
    }
}
$ganti_map = [];
if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
    $qg = $koneksi->query("SELECT g.*, k2.nama_karyawan AS nama_ganti FROM tb_ganti g LEFT JOIN ms_karyawan k2 ON k2.id_karyawan=g.id_karyawan_ganti WHERE g.tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
    if ($qg) while ($rg = $qg->fetch_assoc()) $ganti_map[$rg['tgl'] . '|' . $rg['id_karyawan_absen']] = $rg;
}

// Urutkan per tanggal lalu nama, lalu kelompokkan per departemen (seperti excel_tarik.php)
usort($noshow, function($a, $b) {
    $c = strcmp($a['tgl'], $b['tgl']);
    return $c !== 0 ? $c : strcmp($a['nama_karyawan'], $b['nama_karyawan']);
});
$grouped_by_dept = [];
foreach ($noshow as $ns) {
    $dept_name = !empty($ns['dept']) ? $ns['dept'] : 'LAIN-LAIN';
    if (!isset($grouped_by_dept[$dept_name])) $grouped_by_dept[$dept_name] = [];
    $grouped_by_dept[$dept_name][] = $ns;
}
ksort($grouped_by_dept);
?>

<table border="0">
    <tr>
        <th colspan="10" style="text-align:center; font-size:20px; font-weight:bold;">LAPORAN KARYAWAN TIDAK ABSEN / TIDAK MASUK</th>
    </tr>
    <tr>
        <th colspan="10" style="text-align:center; font-size:16px;">
            Tanggal: <?php echo date('d/m/Y', strtotime($TanggalAwal)) . ' s/d ' . date('d/m/Y', strtotime($TanggalAkhir)); ?>
        </th>
    </tr>
    <tr>
        <td colspan="10" style="height:15px;"></td>
    </tr>
</table>

<table border="1" style="border-collapse:collapse;">
    <tbody>
        <?php
        $grand_karyawan = 0;

        foreach ($grouped_by_dept as $dept_name => $rows) {
            echo "<tr>
                    <td colspan='10' align='center' style='background-color:#1e3a8a; color:white; font-weight:bold;'>
                        " . strtoupper($dept_name) . "
                    </td>
                  </tr>";

            echo "<tr>
                <th style='background:#e5e7eb;'>No</th>
                <th style='background:#e5e7eb;'>TANGGAL</th>
                <th style='background:#e5e7eb;'>NO ABSEN</th>
                <th style='background:#e5e7eb;'>NAMA KARYAWAN</th>
                <th style='background:#e5e7eb;'>DEPARTEMEN</th>
                <th style='background:#e5e7eb;'>SUB BAGIAN</th>
                <th style='background:#e5e7eb;'>OS/DHK</th>
                <th style='background:#e5e7eb;'>SEHARUSNYA</th>
                <th style='background:#e5e7eb;'>KETERANGAN</th>
                <th style='background:#e5e7eb;'>DIGANTIKAN OLEH</th>
            </tr>";

            $no = 1;
            foreach ($rows as $ns) {
                $gkey = $ns['tgl'] . '|' . $ns['id_karyawan'];
                $gex = $ganti_map[$gkey] ?? null;
                $nama_ganti = $gex ? ($gex['nama_ganti'] ?: '-') : '-';
                $grand_karyawan++;

                echo "<tr>
                    <td align='center'>$no</td>
                    <td align='center'>" . date('d/m/Y', strtotime($ns['tgl'])) . "</td>
                    <td align='center'>{$ns['no_absen']}</td>
                    <td>" . strtoupper($ns['nama_karyawan']) . "</td>
                    <td>" . strtoupper($ns['dept'] ?: '-') . "</td>
                    <td>" . ($ns['subdept'] ?: '-') . "</td>
                    <td align='center'>" . ($ns['oslbl'] ?: '-') . "</td>
                    <td>" . ($ns['seharusnya'] ?: '-') . "</td>
                    <td>" . ($ns['ket'] ?: '-') . "</td>
                    <td>" . strtoupper($nama_ganti) . "</td>
                </tr>";
                $no++;
            }
        }
        // GRAND TOTAL
        echo "<tr><td colspan='10' style='background:#203764; color:white; font-weight:bold; text-align:right; padding:10px;'>GRAND TOTAL TIDAK ABSEN ($grand_karyawan Orang)</td></tr>";
        ?>
    </tbody>
</table>
