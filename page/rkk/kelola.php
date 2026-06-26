<?php
if (isset($_GET['id'])) {
  $idrkk = isset($_GET['id']) ? intval($_GET['id']) : 0;

  if ($idrkk <= 0) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "error",
                title: "ID Tidak Valid",
                text: "ID RKK tidak ditemukan atau tidak sah!",
                confirmButtonColor: "#2563eb"
            }).then(() => {
                window.location.href = "?page=rkk";
            });
        </script>
    </body>
    </html>';
    exit;
  }
  $tampildetail = $koneksi->query("select * from tb_rkk where id_rkk = '$idrkk' ");
  $datadetail = $tampildetail->fetch_assoc();
  $datatglrkk = $datadetail['tgl_rkk'];
  $dataketerangan = $datadetail['keterangan'];
  $datadetailrkk   = $datadetail['detail_rkk'];
  $datajamkerja   = $datadetail['jam_kerja'];
  $datastatusrkk   = $datadetail['status_rkk'];

  $tampil = $koneksi->query("SELECT 
    A.*, 
    -- Jika id_karyawan 0, tampilkan '-', jika tidak tampilkan no_absen asli
    IF(A.id_karyawan = 0, '-', B.no_absen) as no_absen_tampil, 
    -- Jika id_karyawan 0, ambil dari kolom manual, jika tidak ambil dari master
    IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_tampil,
    BB.nama_sub_department, 
    D.nama_departmen, 
    C.tgl_rkk, 
    J.keterangan as nama_shift,
    J.jam_masuk,
    J.jam_keluar,
    CASE WHEN A.status_rkk = 'Digantikan' THEN 0 ELSE A.upah END as upahkaryawan, 
    A.potongan_telat, 
    A.potongan_istirahat, 
    A.potongan_lainnya,
    -- Subquery untuk pengganti tetap menggunakan logika ID (asumsi pengganti harus dari database)
    (SELECT K3.nama_karyawan 
     FROM tb_rkk_update U1 
     JOIN tb_rkk_update U2 ON U1.id_rkk_detail = U2.id_rkk_detail 
     JOIN ms_karyawan K3 ON U2.id_karyawan = K3.id_karyawan 
     JOIN tb_rkk_detail RD2 ON U1.id_rkk_detail = RD2.id_rkk_detail
     WHERE U1.id_karyawan = A.id_karyawan 
     AND U1.status = 'Pengganti' 
     AND U2.status = 'Digantikan' 
     AND RD2.id_rkk = A.id_rkk
     LIMIT 1) as menggantikan,
    (SELECT K4.nama_karyawan 
     FROM tb_rkk_update U3 
     JOIN tb_rkk_update U4 ON U3.id_rkk_detail = U4.id_rkk_detail 
     JOIN ms_karyawan K4 ON U4.id_karyawan = K4.id_karyawan 
     WHERE U3.id_rkk_detail = A.id_rkk_detail 
     AND U3.status = 'Digantikan' 
     AND U4.status = 'Pengganti' 
     LIMIT 1) as digantikan_oleh
FROM tb_rkk_detail A 
LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
LEFT JOIN tb_rkk C ON A.id_rkk = C.id_rkk
LEFT JOIN ms_departmen D ON A.id_departmen = D.id_departmen
LEFT JOIN ms_sub_department BB ON A.id_sub_department = BB.id_sub_department 
LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal 
WHERE A.id_rkk = '$idrkk'
");
} else {
  $datatglrkk = "";
  $dataketerangan = "";
  $datadetailrkk   = "";
  $datajamkerja   = "";
  $datastatusrkk   = 3;
}

if ($datastatusrkk == 3) {
  $status = "Hidden";
} elseif ($datastatusrkk == 2) {
  if ($_SESSION['role'] != "Owner") {
    $status = "Hidden";
  } else {
    $status = "";
  }
} elseif ($datastatusrkk == 1) {
  if ($_SESSION['role'] != "Owner") {
    $status = "Hidden";
  } else {
    $status = "";
  }
} else {
  $status = "";
}

?>

<div class="container-fluid px-3 mt-4 mb-4">
  <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
    <div class="border-b border-gray-200 py-4 px-4 md:px-5 bg-white flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <h3 class="text-xl font-bold text-blue-600 m-0"><i class="fas fa-info-circle mr-2"></i> Daftar Rencana Kerja</h3>

      <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
        <a href="?page=rkk" class="inline-flex items-center bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 text-[14px] md:text-base font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto justify-center">
          <i class="fas fa-arrow-left mr-1.5"></i> Kembali
        </a>
        <?php if ($_SESSION['role'] == "Owner") : ?>
          <?php if ($datastatusrkk == 1 || $datastatusrkk == 0) : ?>
            <button type="button"
              class="inline-flex items-center border-0 bg-emerald-600 hover:bg-emerald-700 text-white text-[14px] md:text-base font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto justify-center btn-header-action"
              data-id="<?= $idrkk; ?>"
              data-action="app"
              data-text="Approve Rencana Kerja ini?">
              <i class="fas fa-check-circle mr-1.5"></i> Approve
            </button>
          <?php elseif ($datastatusrkk == 2 || $datastatusrkk == 3) : ?>
            <button type="button"
              class="inline-flex items-center bg-rose-600 hover:bg-rose-700 text-white text-[14px] md:text-base font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto justify-center btn-header-action"
              data-id="<?= $idrkk; ?>"
              data-action="unapp"
              data-text="Batalkan Approve Rencana Kerja ini?">
              <i class="fas fa-times-circle mr-1.5"></i> Un-Approve
            </button>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($datastatusrkk < 2) : ?>
          <a href="?page=rkk&aksi=karyawan&id=<?= $idrkk; ?>" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white text-[14px] md:text-base font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto justify-center">
            <i class="fas fa-user-plus mr-1.5"></i> Tetapkan Karyawan
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="p-4 md:p-5 bg-gray-50 border-b border-gray-100">
      <div class="row">
        <div class="col-md-3 col-sm-12 mb-3 mb-md-0">
          <label class="font-bold text-gray-700 text-sm">Tanggal:</label>
          <input type="text" value="<?= date('d/m/Y', strtotime($datatglrkk)); ?>" readonly class="form-control text-base py-2" />
        </div>

        <div class="col-md-9 col-sm-12 mb-3 mb-md-0">
          <label class="font-bold text-gray-700 text-sm">Keterangan:</label>
          <input type="text" value="<?= $dataketerangan; ?>" readonly class="form-control text-base py-2" />
        </div>
      </div>
    </div>

    <div class="p-0">
      <div class="table-responsive px-3 md:px-4 py-4">
        <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
          <thead class="bg-gray-100 border-b border-gray-300">
            <tr>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">No</th>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Karyawan</th>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Penempatan</th>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Shift</th>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Jam</th>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Upah</th>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Potongan</th>
              <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            while ($data = $tampil->fetch_assoc()) : ?>
              <tr>
                <td data-label="No"><?= $no++ ?></td>
                <td data-label="Karyawan">
                  <span class="text-base font-bold text-gray-900"><?= $data['nama_tampil'] ?></span><br>
                  <span class="text-sm text-gray-500"><?= $data['no_absen_tampil'] ?></span>

                  <?php if (!empty($data['menggantikan'])) : ?>
                    <div class="text-xs text-blue-600 font-bold italic mt-1">
                      <i class="fas fa-exchange-alt mr-1"></i> Menggantikan <?= $data['menggantikan'] ?>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($data['digantikan_oleh'])) : ?>
                    <div class="text-xs text-red-600 font-bold italic mt-1">
                      <i class="fas fa-user-times mr-1"></i> Digantikan oleh <?= $data['digantikan_oleh'] ?>
                    </div>
                  <?php endif; ?>

                  <div class="mt-2">
                    <?php if ($data['status_rkk'] == 'Hadir') : ?>
                      <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-2 py-1 rounded-full">Hadir</span>
                    <?php elseif ($data['status_rkk'] == 'Tidak Hadir') : ?>
                      <span class="bg-rose-100 text-rose-800 text-xs font-bold px-2 py-1 rounded-full">Tidak Hadir</span>
                    <?php elseif ($data['status_rkk'] == 'Digantikan') : ?>
                      <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2 py-1 rounded-full">Digantikan</span>
                    <?php elseif ($data['status_rkk'] == 'Pengganti') : ?>
                      <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded-full">Pengganti</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td data-label="Penempatan">
                  <span class="text-base font-medium"><?= $data['nama_departmen'] ?></span><br>
                  <span class="text-sm text-gray-600"><?= $data['nama_sub_department'] ?></span>
                </td>
                <td data-label="Shift"><span class="badge bg-gray-200 text-gray-800 px-2 py-1 text-sm rounded"><?= $data['nama_shift'] ?></span></td>
                <td data-label="Jam"><span class="text-sm font-medium"><?= $data['jam_masuk'] ?> - <?= $data['jam_keluar'] ?></span></td>
                <td data-label="Upah"><span class="text-base font-semibold text-emerald-600">Rp <?= number_format($data['upahkaryawan'], 0, ',', '.') ?></span></td>
                <td data-label="Potongan" class="text-sm leading-relaxed text-amber-700">
                  <div>Telat: Rp <?= number_format($data['potongan_telat'], 0, ',', '.') ?></div>
                  <div>Istirahat: Rp <?= number_format($data['potongan_istirahat'], 0, ',', '.') ?></div>
                  <div>Lain: Rp <?= number_format($data['potongan_lainnya'], 0, ',', '.') ?></div>
                </td>
                <td data-label="Aksi">
                  <div class="aksi-buttons md:flex md:flex-col justify-center items-center">
                    <?php if ($data['status_rkk'] != 'Digantikan' && $datastatusrkk < 2) : ?>
                      <a href="?page=rkk&aksi=karyawanupdate&id=<?= $data['id_rkk_detail']; ?>"
                        class="px-3 py-2 text-sm font-bold text-amber-700 bg-amber-50 hover:bg-amber-600 hover:text-white rounded border border-amber-300 transition-colors text-center w-full md:w-auto mb-1">
                        <i class="fas fa-sync-alt mr-1"></i> Ganti
                      </a>
                    <?php endif; ?>
                    <?php if (strtolower($_SESSION['role']) != 'admin hr') : ?>
                      <a href="?page=rkk&aksi=detail&id=<?= $data['id_rkk_detail']; ?>"
                        class="px-3 py-2 text-sm font-bold text-blue-700 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-300 transition-colors text-center w-full md:w-auto mb-1">
                        <i class="fas fa-eye mr-1"></i> Detail
                      </a>
                    <?php endif; ?>
                    <?php if ($datastatusrkk < 2) : ?>
                      <button type="button"
                        class="px-3 py-2 text-sm font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-300 transition-colors text-center w-full md:w-auto mb-1 btn-delete-detail"
                        data-id="<?= $idrkk; ?>"
                        data-detail-id="<?= $data['id_rkk_detail']; ?>"
                        data-name="<?= $data['nama_tampil']; ?>">
                        <i class="fas fa-trash-alt mr-1"></i> Hapus
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_GET['msg'])): ?>
  <script>
    Swal.fire({
      icon: '<?= $_GET['msg'] == "deleted" ? "success" : ($_GET['msg'] == "approved" ? "success" : ($_GET['msg'] == "unapproved" ? "info" : "error")) ?>',
      title: '<?= $_GET['msg'] == "deleted" ? "Berhasil!" : ($_GET['msg'] == "approved" ? "Approved!" : ($_GET['msg'] == "unapproved" ? "Dibatalkan!" : "Gagal!")) ?>',
      text: '<?= $_GET['msg'] == "deleted" ? "Data berhasil dihapus" : ($_GET['msg'] == "approved" ? "RKK berhasil di-approve" : ($_GET['msg'] == "unapproved" ? "Approve berhasil dibatalkan" : "Terjadi kesalahan")) ?>',
      confirmButtonColor: '#2563eb'
    });
  </script>
<?php endif; ?>

<style>
  /* Card Styling */
  .panel-primary {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    overflow: hidden;
  }

  /* Table Styling Default */
  .table thead th {
    background-color: #f8f9fa;
    color: #333;
    text-transform: uppercase;
    font-size: 13px;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6 !important;
    vertical-align: middle;
  }

  .table tbody td {
    vertical-align: middle !important;
    font-size: 14px;
  }

  .table-hover tbody tr:hover {
    background-color: rgba(95, 158, 160, 0.1) !important;
    transition: 0.3s;
  }

  /* 1. Reset wrapper agar tidak menggunakan float bawaan DataTables */
  .dataTables_wrapper {
    display: block !important;
  }

  /* 2. Memaksa area atas (Length & Filter) menjadi satu baris sejajar */
  .dataTables_wrapper::before,
  .dataTables_wrapper::after {
    display: none !important;
  }

  /* 3. Membuat container fleksibel untuk Length (kiri) dan Filter (kanan) */
  #dataTables-example_wrapper .row:first-child {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 20px !important;
    width: 100% !important;
  }

  /* 4. Styling Tampil _MENU_ (Kiri) */
  .dataTables_length {
    display: flex !important;
    align-items: center !important;
  }

  .dataTables_length label {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 0 !important;
  }

  .dataTables_length select {
    padding: 5px 10px !important;
    border: 1px solid #e0e6ed !important;
    border-radius: 8px !important;
  }

  /* 5. Styling Cari: (Kanan) */
  .dataTables_filter {
    text-align: right !important;
    display: flex !important;
    justify-content: flex-end !important;
  }

  .dataTables_filter label {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 0 !important;
  }

  .dataTables_filter input {
    padding: 6px 12px !important;
    border: 1px solid #e0e6ed !important;
    border-radius: 8px !important;
    width: 200px !important;
  }

  /* --- STYLING PAGINATE (PREV/NEXT) --- */
  .dataTables_wrapper .dataTables_paginate {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    gap: 4px !important;
    padding-top: 15px !important;
  }

  .dataTables_paginate .paginate_button {
    border: 1px solid #e2e8f0 !important;
    background: white !important;
    border-radius: 6px !important;
    padding: 5px 12px !important;
    color: #475569 !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
  }

  .dataTables_paginate .paginate_button:hover {
    background: #f8fafc !important;
    color: #2563eb !important;
    border-color: #cbd5e1 !important;
  }

  .dataTables_paginate .paginate_button.current {
    background: #2563eb !important;
    border-color: #2563eb !important;
    color: white !important;
  }

  .dataTables_paginate .paginate_button.disabled {
    background: #f1f5f9 !important;
    color: #94a3b8 !important;
    cursor: not-allowed !important;
  }

  /* --- STYLING INFO --- */
  .dataTables_wrapper .dataTables_info {
    padding-top: 20px !important;
    color: #64748b !important;
    font-size: 13px !important;
  }

  /* Responsive Mobile View Khusus Tabel */
  @media screen and (max-width: 768px) {
    #dataTables-example_wrapper .row:first-child {
      flex-direction: column !important;
      align-items: flex-start !important;
    }

    .dataTables_length,
    .dataTables_filter {
      width: 100% !important;
      justify-content: flex-start !important;
    }

    .dataTables_filter input {
      width: 100% !important;
      max-width: 100%;
    }

    .table-modern thead {
      display: none !important;
    }

    .table-modern tbody tr {
      display: block;
      margin-bottom: 1.5rem;
      border: 1px solid #cbd5e1;
      border-radius: 12px;
      padding: 15px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table-modern tbody td {
      display: flex;
      flex-direction: column;
      padding: 10px 0 !important;
      border: none !important;
      border-bottom: 1px solid #f1f5f9 !important;
      font-size: 15px;
    }

    .table-modern tbody td:last-child {
      border-bottom: none !important;
    }

    .table-modern tbody td:before {
      content: attr(data-label);
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      font-size: 12px;
      margin-bottom: 4px;
    }

    .aksi-buttons {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      gap: 10px;
      width: 100%;
      margin-top: 10px;
    }
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  $(document).ready(function() {

    var table = $('#dataTables-example').DataTable({
      pageLength: 10,
      autoWidth: false,
      responsive: false,
      lengthMenu: [
        [10, 25, 50, -1],
        [10, 25, 50, "Semua"]
      ],

      language: {
        search: "Cari Data:",
        searchPlaceholder: "Ketik nama/shift...",
        lengthMenu: "Tampilkan _MENU_ baris",
        info: "Menampilkan _START_ s/d _END_ dari total _TOTAL_ data",
        paginate: {
          previous: "Prev",
          next: "Next"
        }
      }
    });

    // SweetAlert Header Action
    $(document).on('click', '.btn-header-action', function() {
      const id = $(this).data('id');
      const action = $(this).data('action');
      const text = $(this).data('text');

      Swal.fire({
        title: 'Konfirmasi',
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'app' ? '#059669' : '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = '?page=rkk&aksi=accept&id=' + id + '&iddetail=' + action;
        }
      });
    });


    // SweetAlert Delete Detail Action
    $(document).on('click', '.btn-delete-detail', function() {
      const id = $(this).data('id');
      const detailId = $(this).data('detail-id');
      const name = $(this).data('name');

      Swal.fire({
        title: 'Yakin hapus?',
        html: `<b>${name}</b> akan dihapus permanen!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = '?page=rkk&aksi=hapusdetail&id=' + id + '&iddetail=' + detailId;
        }
      });
    });
  });
</script>