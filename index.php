<?php
session_start();

include "koneksi.php";

if ($_SESSION['iduser'] != "" && $_SESSION['nama'] != "") {
  $uid = $_SESSION['iduser'];
  $nama =  $_SESSION['nama'];
  $pass =  $_SESSION['passuser'];
  
  // Mengambil role dari session (tambahkan baris ini)
  $role = $_SESSION['role'] ?? ''; 

  // Process AJAX update lembur before outputting any HTML
  if (isset($_POST['update_lembur_ajax'])) {
      $user_role = strtolower($_SESSION['role'] ?? '');
      $can_edit = ($user_role === 'kepala pabrik' || $user_role === 'admin master' || $user_role === 'owner');
      if (!$can_edit) {
          echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk mengedit lembur.']);
          exit;
      }

      $userid = $koneksi->real_escape_string($_POST['userid']);
      $tgl = $koneksi->real_escape_string($_POST['tgl']);
      $lembur_jam = (float)$_POST['lembur_jam'];
      
      $koneksi->query("CREATE TABLE IF NOT EXISTS tb_record_lembur (
          userid VARCHAR(50) NOT NULL,
          tgl DATE NOT NULL,
          lembur_jam DECIMAL(10,2) DEFAULT 0,
          PRIMARY KEY (userid, tgl)
      ) ENGINE=InnoDB");

      $koneksi->query("INSERT INTO tb_record_lembur (userid, tgl, lembur_jam) 
                       VALUES ('$userid', '$tgl', '$lembur_jam') 
                       ON DUPLICATE KEY UPDATE lembur_jam = '$lembur_jam'");
      echo json_encode(['success' => true]);
      exit;
  }
?>
  <!DOCTYPE html>
  <html>

  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>PAYROLL</title>
    <link rel="shortcut icon" href="images/favicon_round.png" type="image/png">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="assets/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        corePlugins: {
          preflight: false, 
        },
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'sans-serif']
            },
            colors: {
              brand: {
                50: '#eff6ff',
                100: '#dbeafe',
                500: '#3b82f6',
                600: '#2563eb',
                900: '#1e3a8a',
              },
              sidebar: '#ffffff',
              sidebarhover: '#f8fafc'
            }
          }
        }
      }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
  <link rel="stylesheet" href="css/modern.css">
    <style>
      /* Override AdminLTE styles using Tailwind-like clean styles */
      body { font-family: 'Inter', sans-serif !important; background-color: #f1f5f9 !important; }
      .main-header.navbar { background-color: white !important; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); border-bottom: 1px solid #e2e8f0; padding: 0 1rem; }
      .navbar-light .navbar-nav .nav-link { color: #475569 !important; }
      .brand-link { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%) !important; color: white !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 0 !important; }
      .brand-link:hover { color: #f8fafc !important; }
      .main-sidebar { background-color: #ffffff !important; box-shadow: 1px 0 10px rgba(0, 0, 0, 0.05); border-right: 1px solid #e2e8f0; overflow-x: hidden !important; }
      .nav-sidebar>.nav-header { background-color: #ffffff !important; color: #94a3b8 !important; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 15px 15px 10px 15px; }
      .nav-sidebar .nav-item>.nav-link { color: #475569 !important; font-weight: 500; transition: all 0.2s ease; border-radius: 0.5rem; margin-bottom: 2px; }
      .nav-sidebar .nav-item:hover>.nav-link, .nav-sidebar .nav-item>.nav-link.active { background-color: #eff6ff !important; color: #2563eb !important; }
      .nav-sidebar .nav-item>.nav-link>i { color: #64748b !important; margin-right: 8px; transition: all 0.2s ease; }
      .nav-sidebar .nav-item:hover>.nav-link>i, .nav-sidebar .nav-item>.nav-link.active>i { color: #2563eb !important; }
      .nav-treeview { background-color: #f8fafc !important; padding-left: 10px; border-radius: 0.5rem; }
      .nav-treeview>.nav-item>.nav-link { color: #64748b !important; padding: 8px 15px 8px 25px; transition: all 0.2s; }
      .nav-treeview>.nav-item>.nav-link:hover { color: #2563eb !important; background-color: transparent !important; transform: translateX(4px); }
      .nav-treeview>.nav-item>.nav-link>i { color: #94a3b8 !important; }
      .nav-treeview>.nav-item>.nav-link:hover>i { color: #2563eb !important; }
      .content-wrapper { background-color: #f1f5f9 !important; }
    </style>
  </head>

  <body class="hold-transition sidebar-mini layout-fixed">

    <div class="wrapper">

      <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
          </li>
        </ul>

        <ul class="navbar-nav ml-auto">
          <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
              <img src="images/iconhr.jpeg" class="user-image img-circle elevation-1" alt="User Image">
              <span class="d-none d-md-inline"><?php echo $_SESSION['nama']; ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="border:none; border-radius:0.5rem; box-shadow:0 10px 15px -3px rgba(0, 0, 0, 0.1);">
              <li class="user-header bg-primary" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%) !important;">
                <img src="images/iconhr.jpeg" class="img-circle elevation-2" alt="User Image">
                <p>
                  <?php echo $_SESSION['nama']; ?> - <?php echo strtoupper($role != '' ? $role : 'Administrator'); ?>
                  <small>Account</small>
                </p>
              </li>
              <li class="user-footer bg-light flex justify-between p-3">
                <a href="?page=user&aksi=ubah&id=<?php echo $_SESSION['iduser']; ?>" class="btn btn-default btn-flat rounded">Edit Profile</a>
                <a href="logout.php" class="btn btn-default btn-flat rounded">Sign out</a>
              </li>
            </ul>
          </li>
        </ul>
      </nav>

      <aside class="main-sidebar sidebar-light-primary elevation-4">
        <a href="?page=" class="brand-link">
          <img src="images/iconhr.jpeg" alt="Admin Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
          <span class="brand-text font-weight-light"><b>ADMIN</b></span>
        </a>

        <div class="sidebar">
          <nav class="mt-3">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
              <li class="nav-header">MENU ADMIN</li>

              <li class="nav-item">
                <a href="?page=" class="nav-link">
                  <i class="nav-icon fas fa-tachometer-alt"></i>
                  <p>Dashboard</p>
                </a>
              </li>

              <?php if (strtolower($role) != 'owner') { ?>
              <li class="nav-item">
                <a href="#" class="nav-link">
                  <i class="nav-icon fas fa-cogs"></i>
                  <p>MASTER <i class="right fas fa-angle-left"></i></p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item"><a href="?page=karyawan" class="nav-link"><i class="fas fa-users nav-icon"></i><p>Karyawan</p></a></li>
                  <li class="nav-item"><a href="?page=bagian" class="nav-link"><i class="far fa-building nav-icon"></i><p>Bagian</p></a></li>
                  <?php if (strtolower($role) != 'admin hr') { ?>
                  <li class="nav-item"><a href="?page=user" class="nav-link"><i class="fas fa-user-shield nav-icon"></i><p>User</p></a></li>
                  <?php } ?>
                  <li class="nav-item"><a href="?page=jadwal" class="nav-link"><i class="far fa-clock nav-icon"></i><p>Jadwal</p></a></li>
                  <li class="nav-item"><a href="?page=denda" class="nav-link"><i class="fas fa-exclamation-triangle nav-icon"></i><p>Denda</p></a></li>
                  <li class="nav-item"><a href="?page=lembur" class="nav-link"><i class="fas fa-business-time nav-icon"></i><p>Lembur</p></a></li>
                  <li class="nav-item"><a href="?page=boneless&aksi=master" class="nav-link"><i class="fas fa-car nav-icon"></i><p>Biaya Mobil Bonless</p></a></li>
                </ul>
              </li>
              <?php } ?>

              <li class="nav-item">
                <a href="?page=rkk" class="nav-link">
                  <i class="nav-icon far fa-calendar-plus"></i>
                  <p>Rencana Upah</p>
                </a>
              </li>
              
              <li class="nav-item">
                <a href="?page=realisasi" class="nav-link">
                  <i class="nav-icon fas fa-check-double"></i>
                  <p>Realisasi Upah</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="?page=realisasi&aksi=slipk" class="nav-link">
                  <i class="nav-icon fas fa-file-invoice-dollar"></i>
                  <p>Cetak Slip</p>
                </a>
              </li>

              <?php if (strtolower($role) != 'owner') { ?>
              <li class="nav-item">
                <a href="?page=generate&aksi=tarik" class="nav-link"><i class="nav-icon fas fa-download"></i><p>Tarik Data</p></a>
              </li>

              <li class="nav-item">
                <a href="#" class="nav-link">
                  <i class="nav-icon fas fa-clipboard-list"></i>
                  <p>SIAC <i class="right fas fa-angle-left"></i></p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item"><a href="?page=sakit" class="nav-link"><i class="fas fa-user-injured nav-icon"></i><p>Sakit</p></a></li>
                  <li class="nav-item"><a href="?page=ijin" class="nav-link"><i class="fas fa-clock nav-icon"></i><p>Izin</p></a></li>
                  <li class="nav-item"><a href="?page=alfa" class="nav-link"><i class="fas fa-user-times nav-icon"></i><p>Alfa</p></a></li>
                  <li class="nav-item"><a href="?page=cuti" class="nav-link"><i class="fas fa-plane-departure nav-icon"></i><p>Cuti</p></a></li>
                  <li class="nav-item"><a href="?page=siac" class="nav-link"><i class="far fa-star nav-icon"></i><p>Data Karyawan SIAC</p></a></li>
                </ul>
              </li>

              <?php if (strtolower($role) == 'admin master') { ?>
              <li class="nav-item">
              <a href="?page=mesin" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Setting Device</p></a>
              </li>
              <?php } ?>
              <?php } ?>

            </ul>
          </nav>
        </div>
      </aside>

      <div class="content-wrapper">
        <section class="content-header">
          <div id="page-wrapper">
            <div id="page-inner">
              <div class="row">
                <div class="col-md-12">
                  <?php
                  $page = @$_GET['page'];
                  $aksi = @$_GET['aksi'];

                  if ($page == 'grup') {
                    if ($aksi == "") { include "page/grup/grup.php"; } 
                    elseif ($aksi == "hapus") { include "page/grup/hapus.php"; }
                  } else if ($page == 'karyawan') {
                    if ($aksi == "") { include "page/karyawan/karyawan.php"; } 
                    elseif ($aksi == "hapus") { include "page/karyawan/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/karyawan/tambah.php"; } 
                    elseif ($aksi == "ubah") { include "page/karyawan/ubah.php"; } 
                    elseif ($aksi == "view") { include "page/karyawan/view.php"; } 
                  } else if ($page == 'bagian') {
                    if ($aksi == "") { include "page/bagian/bagian.php"; } 
                    elseif ($aksi == "hapus") { include "page/bagian/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/bagian/tambah.php"; } 
                    elseif ($aksi == "ubah") { include "page/bagian/ubah.php"; }
                  } else if ($page == 'jadwal') {
                    if ($aksi == "") { include "page/jadwal/jadwal.php"; } 
                    elseif ($aksi == "hapus") { include "page/jadwal/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/jadwal/tambah.php"; } 
                    elseif ($aksi == "ubah") { include "page/jadwal/ubah.php"; }
                  } else if ($page == 'user') {
                    $id_edit = $_GET['id'] ?? '';
                    if (strtolower($role) == 'admin hr' && $id_edit != $_SESSION['iduser']) {
                      echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='index.php';</script>";
                    } else {
                      if ($aksi == "") { include "page/user/user.php"; } 
                      elseif ($aksi == "hapus") { include "page/user/hapus.php"; } 
                      elseif ($aksi == "tambah") { include "page/user/tambah.php"; } 
                      elseif ($aksi == "ubah") { include "page/user/ubah.php"; } 
                    }
                  } else if ($page == 'sakit') {
                    if ($aksi == "") { include "page/siac/sakit/sakit.php"; } 
                    elseif ($aksi == "hapus") { include "page/siac/sakit/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/siac/sakit/tambah.php"; } 
                    elseif ($aksi == "cari") { include "page/siac/sakit/karyawan.php"; }
                  } else if ($page == 'cuti') {
                    if ($aksi == "") { include "page/siac/cuti/cuti.php"; } 
                    elseif ($aksi == "hapus") { include "page/siac/cuti/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/siac/cuti/tambah.php"; } 
                    elseif ($aksi == "cari") { include "page/siac/cuti/karyawan.php"; }
                  } else if ($page == 'alfa') {
                    if ($aksi == "") { include "page/siac/alfa/alfa.php"; } 
                    elseif ($aksi == "hapus") { include "page/siac/alfa/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/siac/alfa/tambah.php"; } 
                    elseif ($aksi == "cari") { include "page/siac/alfa/karyawan.php"; }
                  } else if ($page == 'ijin') {
                    if ($aksi == "") { include "page/siac/ijin/ijin.php"; } 
                    elseif ($aksi == "hapus") { include "page/siac/ijin/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/siac/ijin/tambah.php"; } 
                    elseif ($aksi == "cari") { include "page/siac/ijin/karyawan.php"; }
                  } else if ($page == 'denda') {
                    if ($aksi == "") { include "page/denda/denda.php"; }
                  } else if ($page == 'lembur') {
                    if ($aksi == "") { include "page/lembur/lembur.php"; }
                  } else if ($page == 'mesin') {
                    if ($aksi == "") { include "page/mesin/mesin.php"; }
                  } else if ($page == 'generate') {
                    if ($aksi == "") { include "page/generate/generate.php"; } 
                    elseif ($aksi == "tarik") { include "page/generate/tarik-data.php"; }
                  } else if ($page == 'rkk') {
                    if ($aksi == "") { include "page/rkk/rkk.php"; } 
                    elseif ($aksi == "hapus") { include "page/rkk/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/rkk/tambah.php"; } 
                    elseif ($aksi == "ubah") { include "page/rkk/ubah.php"; } 
                    elseif ($aksi == "karyawan") { include "page/rkk/karyawan.php"; } 
                    elseif ($aksi == "update" || $aksi == "karyawanupdate") { include "page/rkk/karyawanupdate.php"; } 
                    elseif ($aksi == "history") { include "page/rkk/history.php"; } 
                    elseif ($aksi == "payroll") { include "page/rkk/payroll.php"; } 
                    elseif ($aksi == "payrollrkk") { include "page/rkk/payrollrkk.php"; } 
                    elseif ($aksi == "kelola") { include "page/rkk/kelola.php"; } 
                    elseif ($aksi == "hapusdetail") { include "page/rkk/hapus.php"; } 
                    elseif ($aksi == "hapus_massal") { include "page/rkk/hapus_massal.php"; } 
                    elseif ($aksi == "detail") { include "page/rkk/detail.php"; } 
                    elseif ($aksi == "accept") { include "page/rkk/app.php"; }
                  } else if ($page == 'siac') {
                    if ($aksi == "") { include "page/siac/karyawan.php"; } 
                    elseif ($aksi == "sakit") { include "page/siac/sakit/sakit.php"; } 
                    elseif ($aksi == "ijin") { include "page/siac/ijin/ijin.php"; } 
                    elseif ($aksi == "alfa") { include "page/siac/alfa/alfa.php"; } 
                    elseif ($aksi == "cuti") { include "page/siac/cuti/cuti.php"; }
                  } elseif ($page == "") {
                    include "home.php";
                  } else if ($page == 'realisasi') {
                    if (strtolower($role) == 'admin hr' && $aksi == 'detail') {
                        echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='index.php';</script>";
                    } else {
                        if ($aksi == "") { include "page/realisasi/realisasi.php"; } 
                        elseif ($aksi == "rkk") { include "page/realisasi/rkk.php"; } 
                        elseif ($aksi == "tambah") { include "page/realisasi/tambah.php"; } 
                        elseif ($aksi == "kelola") { include "page/realisasi/kelola.php"; } 
                        elseif ($aksi == "accept") { include "page/realisasi/app.php"; } 
                        elseif ($aksi == "unapprove") { include "page/realisasi/app.php"; } 
                        elseif ($aksi == "detail") { include "page/realisasi/detail.php"; } 
                        elseif ($aksi == "karyawan") { include "page/realisasi/karyawan.php"; } 
                        elseif ($aksi == "slip") { include "page/realisasi/slip.php"; }
                        elseif ($aksi == "slipk") { include "page/realisasi/slipk.php"; }
                        elseif ($aksi == "hapus_massal") { include "page/realisasi/hapus_massal.php"; }
                    }
                  } else if ($page == 'boneless') {
                    if ($aksi == "") { include "page/boneless/boneless.php"; } 
                    elseif ($aksi == "hapus") { include "page/boneless/hapus.php"; } 
                    elseif ($aksi == "tambah") { include "page/boneless/tambah.php"; } 
                    elseif ($aksi == "ubah") { include "page/boneless/ubah.php"; }
                    elseif ($aksi == "master") { include "page/boneless/m_boneless.php"; }
                  }
                  ?>
                </div>
              </div>
            </div>
        </section>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
      <script src="assets/bower_components/jquery-ui/jquery-ui.min.js"></script>
      <script>
        $.widget.bridge('uibutton', $.ui.button);
      </script>
      <script src="assets/dataTables/jquery.dataTables.js"></script>
      <script src="assets/dataTables/dataTables.bootstrap.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

      <script>
        $(document).ready(function() {
          $('#dataTables-example').dataTable();

          $('.select2-manage').each(function() {
            const $select = $(this);
            const placeholder = $select.data('placeholder') || '- Pilih -';
            const deleteUrl = $select.data('delete-route');

            $select.select2({
              theme: 'bootstrap4',
              placeholder: placeholder,
              allowClear: true,
              tags: !!$select.data('tags'),
              templateResult: formatState,
              templateSelection: formatSelection
            });

            function formatState(state) {
              if (!state.id || state.id === 'add_new' || !deleteUrl) return state.text;
              
              const $state = $(
                '<div class="flex justify-between items-center w-full group">' +
                '<span class="text-gray-700">' + state.text + '</span>' +
                '<button type="button" class="btn-delete-item ml-2 h-6 w-6 flex items-center justify-center rounded-full text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200 opacity-0 group-hover:opacity-100" data-id="' + state.id + '">' +
                '<i class="fas fa-times text-[10px]"></i>' +
                '</button>' +
                '</div>'
              );

              $state.find('.btn-delete-item').on('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                const itemId = $(state.element).data('id') || state.id; 
                const itemText = state.text;

                if (confirm('Hapus "' + itemText + '" dari master data?')) {
                  $.post('page/ajax/hapus_master.php', {
                    id: itemId,
                    route: deleteUrl
                  }, function(res) {
                    if (res.success) {
                      $select.find('option[data-id="' + itemId + '"], option[value="' + itemId + '"]').remove();
                      $select.trigger('change');
                      const currentOpen = $select.data('select2').isOpen();
                      if (currentOpen) {
                        $select.select2('close').select2('open');
                      }
                    } else {
                      alert('Gagal menghapus: ' + (res.message || 'Data mungkin sedang digunakan.'));
                    }
                  }, 'json');
                }
              });

              return $state;
            }

            function formatSelection(state) {
              return state.text;
            }
          });
        });
      </script>

  </body>
  </html>
<?php
} else {
  header("location:login.php");
}
?>