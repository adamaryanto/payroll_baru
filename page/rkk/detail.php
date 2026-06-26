<?php
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 1. Logika Simpan (Ditaruh di atas agar data terbaru langsung ter-fetch di bawah)
    if (isset($_POST['simpan'])) {
        $dataidrkk_post = $_POST['dataidrkk']; // Pass via hidden field to be safe
        $tshift = $_POST['tshift'];
        $tupah = $_POST['tupah'];
        $tpottelat = $_POST['tpottelat'];
        $tpotistirahat = $_POST['tpotistirahat'];
        $tpotlainnya = $_POST['tpotlainnya'];
        $tdepartmen = $_POST['tdepartmen'];
        $tsubdepartment = $_POST['tsubdepartment'];
        $ttgl2 = date("Y-m-d H:i:s");

        // Server-side validation status RKK
        $cek_rkk = $koneksi->query("SELECT status_rkk FROM tb_rkk WHERE id_rkk = '$dataidrkk_post'");
        $data_rkk = $cek_rkk->fetch_assoc();
        
        if ($data_rkk['status_rkk'] < 2) {
            // Handle Tags (New Entries)
            if (!empty($tdepartmen) && !is_numeric($tdepartmen)) {
                $name_dept = $koneksi->real_escape_string($tdepartmen);
                $koneksi->query("INSERT INTO ms_departmen (nama_departmen) VALUES ('$name_dept')");
                $tdepartmen = $koneksi->insert_id;
            }
            if (!empty($tsubdepartment) && !is_numeric($tsubdepartment)) {
                $name_sub = $koneksi->real_escape_string($tsubdepartment);
                // Link to dept if available
                $dept_id_val = is_numeric($tdepartmen) ? $tdepartmen : 0;
                $koneksi->query("INSERT INTO ms_sub_department (nama_sub_department, id_departmen) VALUES ('$name_sub', '$dept_id_val')");
                $tsubdepartment = $koneksi->insert_id;
            }

            $tampil_j = $koneksi->query("SELECT * from tb_jadwal WHERE id_jadwal = '$tshift' ");
            $data_j = $tampil_j->fetch_assoc();
            $tjammasuk = $data_j['jam_masuk'];
            $tjamkeluar = $data_j['jam_keluar'];
            $tistirahatmasuk = $data_j['istirahat_masuk'];
            $tistirahatkeluar = $data_j['istirahat_keluar'];

            $sql_upd = $koneksi->query("UPDATE tb_rkk_detail SET
                upah = '$tupah',
                id_departmen = '$tdepartmen',
                id_sub_department = '$tsubdepartment',
                id_jadwal = '$tshift',
                potongan_telat = '$tpottelat',
                potongan_istirahat = '$tpotistirahat',
                potongan_lainnya = '$tpotlainnya',
                tgl_updt = '$ttgl2'
                WHERE id_rkk_detail = '$id'");
            
            if ($sql_upd) {
                echo "<script>alert('Data Tersimpan'); window.location.href='?page=rkk&aksi=kelola&id=$dataidrkk_post';</script>";
                exit;
            } else {
                echo "<script>alert('Gagal menyimpan ke database!');</script>";
            }
        } else {
            echo "<script>alert('Gagal: RKK sudah Approved/Realized!'); window.location.href='?page=rkk&aksi=kelola&id=$dataidrkk_post';</script>";
            exit;
        }
    }

    // 2. Fetch Data untuk Tampilan
    $tampildetail = $koneksi->query("
    SELECT A.*, J.jam_masuk, J.jam_keluar, J.istirahat_masuk, J.istirahat_keluar, J.keterangan as nama_shift, B.nama_karyawan, B.no_absen,
    BD.nama_departmen, BC.nama_sub_department, B.jenis_kelamin,
    R.keterangan as keterangan_rkk, R.tgl_rkk, R.detail_rkk, R.jam_kerja, R.status_rkk
    FROM tb_rkk_detail A
    LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal
    LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
    LEFT JOIN ms_departmen BD ON A.id_departmen = BD.id_departmen
    LEFT JOIN ms_sub_department BC ON A.id_sub_department = BC.id_sub_department
    LEFT JOIN tb_rkk R ON A.id_rkk = R.id_rkk
    WHERE A.id_rkk_detail = '$id' ");

    $datadetail = $tampildetail->fetch_assoc();
    $dataidrkk = $datadetail['id_rkk'] ?? 0;
    $datatglrkk = $datadetail['tgl_rkk'] ?? "";
    $dataketeranganrkk = $datadetail['keterangan_rkk'] ?? "";
    $datadetailrkk   = $datadetail['detail_rkk'] ?? "";
    $datajamkerja   = $datadetail['jam_kerja'] ?? 0;
    $datastatusrkk  = $datadetail['status_rkk'] ?? 0;
    $readonly       = ($datastatusrkk >= 2) ? "readonly" : "";
    $disabled       = ($datastatusrkk >= 2) ? "disabled" : "";

    $datajammasuk = $datadetail['jam_masuk'] ?? "";
    $datajamkeluar = $datadetail['jam_keluar'] ?? "";
    $dataistirahatmasuk = $datadetail['istirahat_masuk'] ?? "";
    $datajamistirahatkeluar = $datadetail['istirahat_keluar'] ?? "";
    $dataidjadwal = $datadetail['id_jadwal'] ?? 0;
    $dataketerangan = $datadetail['nama_shift'] ?? "";

    $datanoabsen = $datadetail['no_absen'] ?? "";
    $datanamakaryawan = $datadetail['nama_karyawan'] ?? "";
    $databagian = $datadetail['nama_departmen'] ?? "";
    $datasubbagian = $datadetail['nama_sub_department'] ?? "";
    $datajenkel = $datadetail['jenis_kelamin'] ?? "";
    $dataiddepartmen = $datadetail['id_departmen'] ?? 0;
    $dataidsubdepartment = $datadetail['id_sub_department'] ?? 0;

    $dataupah = $datadetail['upah'] ?? 0;
    $datapotongantelat = $datadetail['potongan_telat'] ?? 0;
    $datapotonganistirahat = $datadetail['potongan_istirahat'] ?? 0;
    $datapotonganlainnya = $datadetail['potongan_lainnya'] ?? 0;
}
?>

<style>
    /* Styling Dasar Modern */
    .card-modern {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        padding: 25px;
        margin-bottom: 25px;
        border: none;
    }
    .section-divider {
        display: flex;
        align-items: center;
        color: #2563eb;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 30px 0 15px 0;
        font-size: 0.9rem;
    }
    .section-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e5e7eb;
        margin-left: 15px;
    }
    /* Input Styling */
    .form-control {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 10px 15px;
        height: auto;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(95, 158, 160, 0.2);
    }
    label { font-size: 0.75rem; color: #6b7280; margin-bottom: 8px; }

    /* Tombol Modern */
    .btn-custom {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-primary-custom { background-color: #2563eb; color: white; border: none; }
    .btn-primary-custom:hover { background-color: #4a7d7f; color: white; transform: translateY(-1px); }
    .btn-warning-custom { background-color: #f39c12; color: white; border: none; }
    .btn-warning-custom:hover { background-color: #d68910; color: white; }
</style>

<div class="row">
    <div class="col-md-12">
        <form method="POST">
            <input type="hidden" name="dataidrkk" value="<?= $dataidrkk ?>">
            <div class="card-modern">
               <h3 class="text-blue-600 font-bold"><i class="fas fa-file-invoice-dollar mr-2"></i> Detail Rencana Upah</h3>

                <div class="section-divider">Rencana Kerja</div>
                <div class="row">
                    <div class="form-group col-md-3">
                        <label>TANGGAL</label>
                        <input type="date" name="ttgl1" value="<?= $datatglrkk ?>" class="form-control" readonly style="background: #f9fafb;" />
                    </div>
                    <div class="form-group col-md-6">
                        <label>KETERANGAN</label>
                        <input type="text" name="tketerangan" value="<?= $dataketeranganrkk ?>" class="form-control" readonly style="background: #f9fafb;" />
                    </div>
                    <div class="form-group col-md-3">
                        <label>JAM KERJA</label>
                        <input type="number" name="tjamkerja" value="<?= $datajamkerja ?>" class="form-control" readonly style="background: #f9fafb;" />
                    </div>
                </div>

                <div class="section-divider">Informasi Karyawan</div>
                <div class="row">
                    <div class="form-group col-md-4">
                        <label>NO. ABSEN</label>
                        <input type="text" value="<?= $datanoabsen ?>" class="form-control" readonly style="background: #f9fafb;" />
                    </div>
                    <div class="form-group col-md-4">
                        <label>NAMA</label>
                        <input type="text" value="<?= $datanamakaryawan ?>" class="form-control" readonly style="background: #f9fafb;" />
                    </div>
                    <div class="form-group col-md-2">
                        <label>BAGIAN</label>
                        <select class="form-control select2-manage" name="tdepartmen" data-tags="true" <?= $disabled ?>>
                             <option value="<?= $dataiddepartmen ?>"><?= $databagian ?></option>
                             <?php
                             $sqldept = $koneksi->query("SELECT * FROM ms_departmen ORDER BY nama_departmen ASC");
                             while ($d = $sqldept->fetch_array()) { echo "<option value='$d[id_departmen]'>$d[nama_departmen]</option>"; }
                             ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>SUB BAGIAN</label>
                        <select class="form-control select2-manage" name="tsubdepartment" data-tags="true" <?= $disabled ?>>
                             <option value="<?= $dataidsubdepartment ?>"><?= $datasubbagian ?></option>
                             <?php
                             $sqlsub = $koneksi->query("SELECT * FROM ms_sub_department ORDER BY nama_sub_department ASC");
                             while ($s = $sqlsub->fetch_array()) { echo "<option value='$s[id_sub_department]'>$s[nama_sub_department]</option>"; }
                             ?>
                        </select>
                    </div>
                </div>

                <div class="section-divider text-danger">Detail Upah & Shift</div>
                <div class="row">
                    <div class="form-group col-md-3">
                        <label>SHIFT</label>
                        <select class="form-control select2-manage" name="tshift" required <?= $disabled ?>>
                           <option value="<?= $dataidjadwal ?>"><?= $dataketerangan ?></option>
                           <?php
                           $sql_j = $koneksi->query("SELECT * FROM tb_jadwal ORDER BY keterangan ASC");
                           while ($j = $sql_j->fetch_array()) { echo "<option value='$j[id_jadwal]'>$j[keterangan]</option>"; }
                           ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>UPAH</label>
                        <input type="number" name="tupah" value="<?= $dataupah ?>" class="form-control" required <?= $readonly ?> />
                    </div>
                    <div class="form-group col-md-2">
                        <label>POT. TELAT</label>
                        <input type="number" name="tpottelat" value="<?= $datapotongantelat ?>" class="form-control" required <?= $readonly ?> />
                    </div>
                    <div class="form-group col-md-2">
                        <label>POT. ISTIRAHAT</label>
                        <input type="number" name="tpotistirahat" value="<?= $datapotonganistirahat ?>" class="form-control" required <?= $readonly ?> />
                    </div>
                    <div class="form-group col-md-3">
                        <label>POT. LAINNYA</label>
                        <input type="number" name="tpotlainnya" value="<?= $datapotonganlainnya ?>" class="form-control" required <?= $readonly ?> />
                    </div>
                </div>

                <div style="margin-top: 25px; border-top: 1px solid #f3f4f6;">
                    <a href="?page=rkk&aksi=kelola&id=<?= $dataidrkk ?>" class="btn btn-warning-custom btn-custom">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>
                    <?php if ($datastatusrkk < 2) : ?>
                        <button type="submit" name="simpan" value="1" class="btn btn-primary-custom btn-custom">
                            <i class="fa fa-save"></i> Simpan Perubahan
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>