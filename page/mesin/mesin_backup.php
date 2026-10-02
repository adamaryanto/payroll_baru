<?php

// Get all devices
$devices = [];
$result = $koneksi->query("SELECT * FROM tb_mesin ORDER BY no_mesin");
while($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

// Always ensure we have at least 4 devices for display
$device_count = count($devices);
for($i = $device_count; $i < 4; $i++) {
    $devices[] = [
        'id_mesin' => '',
        'no_mesin' => $i + 1,
        'nama_mesin' => 'Mesin ' . ($i + 1),
        'ip_mesin' => '',
        'comm_key' => '0',
        'port' => '4370'
    ];
}

// Re-index devices to ensure 0-3 keys
$devices = array_values($devices);

// Include device sync helper for connection testing
include_once __DIR__ . '/../../device_sync_helper.php';

?>
<div class="container-fluid px-0">
    <div class="modern-card overflow-hidden">
        <div class="modern-panel-header">
            <h3 class="flex items-center gap-3">
                <i class="fas fa-microchip"></i>
                Setting Konfigurasi Mesin Absensi
            </h3>
        </div>
        
        <div class="p-6 md:p-8">
            <!-- Device Tabs -->
            <div class="mb-8">
                <div class="flex flex-wrap border-b border-slate-200">
                     <?php foreach($devices as $index => $device): 
                        // Test device connection
                        $is_online = false;
                        if(!empty($device['ip_mesin'])) {
                            $is_online = test_device_connection($device['ip_mesin'], $device['port']);
                        }
                    ?>
                    <button type="button" 
                            class="device-tab px-6 py-3 text-sm font-medium rounded-t-lg transition-all duration-200 border-b-2 <?php echo $index === 0 ? 'border-brand-500 text-brand-600 bg-brand-50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'; ?>"
                            onclick="showDeviceTab(<?php echo $index + 1; ?>)">
                        <i class="fas fa-fingerprint mr-2"></i>
                        <?php echo htmlspecialchars($device['nama_mesin']); ?>
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full <?php echo $is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <i class="fas fa-circle text-[8px] mr-1"></i>
                            <?php echo $is_online ? 'ONLINE' : 'OFFLINE'; ?>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php foreach($devices as $index => $device): ?>
                                <div id="device-<?php echo $index + 1; ?>" class="device-content <?php echo $index === 0 ? '' : 'd-none'; ?>">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h4 class="text-lg font-semibold text-slate-800">
                                <i class="fas fa-cog mr-2 text-brand-500"></i>
                                Konfigurasi <?php echo htmlspecialchars($device['nama_mesin']); ?>
                            </h4>
                            <p class="text-sm text-gray-500 mt-1">
                                <?php echo !empty($device['ip_mesin']) ? $device['ip_mesin'] . ':' . $device['port'] : 'IP belum diisi'; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600">
                                Device <?php echo $index + 1; ?>
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <i class="fas fa-circle text-[8px] mr-1"></i>
                                <?php echo $is_online ? 'TERKONEKSI' : 'OFFLINE'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Left Column -->
                        <div class="space-y-6">
                            <div class="form-group border-0 p-0 m-0">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                    <i class="fas fa-hashtag text-brand-500"></i> No Mesin <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="tnomesin[]" value="<?php echo htmlspecialchars($device['no_mesin']); ?>" 
                                       required class="modern-input w-full" placeholder="Contoh: <?php echo $index + 1; ?>">
                            </div>

                            <div class="form-group border-0 p-0 m-0">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                    <i class="fas fa-server text-brand-500"></i> Nama Mesin <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="tnamamesin[]" value="<?php echo htmlspecialchars($device['nama_mesin']); ?>" 
                                       required class="modern-input w-full" placeholder="Contoh: Mesin <?php echo $index + 1; ?>">
                            </div>

                            <div class="form-group border-0 p-0 m-0">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                    <i class="fas fa-network-wired text-brand-500"></i> IP Mesin <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="tipmesin[]" value="<?php echo htmlspecialchars($device['ip_mesin']); ?>" 
                                       required class="modern-input w-full" placeholder="Contoh: 192.168.1.<?php echo $index + 1; ?>">
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-6">
                            <div class="form-group border-0 p-0 m-0">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                    <i class="fas fa-key text-brand-500"></i> Comm Key <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="tcommkey[]" value="<?php echo htmlspecialchars($device['comm_key']); ?>" 
                                       required class="modern-input w-full" placeholder="Default: 0">
                            </div>

                            <div class="form-group border-0 p-0 m-0">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                    <i class="fas fa-plug text-brand-500"></i> Port <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="tport[]" value="<?php echo htmlspecialchars($device['port']); ?>" 
                                       required class="modern-input w-full" placeholder="Default: 4370">
                            </div>

                            <?php if($index === 0): ?>
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-8 flex gap-3">
                                <i class="fas fa-info-circle text-amber-500 mt-1"></i>
                                <div class="text-sm text-amber-800">
                                    <strong>Catatan Penting:</strong> 
                                    Pastikan mesin dalam keadaan terkoneksi dengan jaringan yang sama. Perubahan IP atau Port yang salah dapat menyebabkan kegagalan tarik data.
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Footer / Action Area -->
                <div class="mt-10 pt-6 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-slate-500 text-sm">
                        <span class="text-rose-500 font-bold">*</span> Harus diisi dengan benar
                    </div>
                    <button type="submit" name="simpan" value="Simpan" class="btn-modern btn-primary px-10 py-3 flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        Simpan Semua Konfigurasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Handle POST for saving multiple devices
if(isset($_POST['simpan'])) {
    // Get existing devices to preserve IDs
    $existing_devices = [];
    $result = $koneksi->query("SELECT id_mesin, no_mesin FROM tb_mesin ORDER BY no_mesin");
    while($row = $result->fetch_assoc()) {
        $existing_devices[] = $row;
    }
    
    // Process each device from form
    $device_count = isset($_POST['tnomesin']) ? count($_POST['tnomesin']) : 0;
    $success = true;
    
    for($i = 0; $i < $device_count; $i++) {
        $no_mesin = $_POST['tnomesin'][$i] ?? ($i + 1);
        $nama_mesin = $_POST['tnamamesin'][$i] ?? "Mesin " . ($i + 1);
        $ip_mesin = $_POST['tipmesin'][$i] ?? "";
        $comm_key = $_POST['tcommkey'][$i] ?? "0";
        $port = $_POST['tport'][$i] ?? "4370";
        
        // Escape input
        $no_mesin_esc = mysqli_real_escape_string($koneksi, $no_mesin);
        $nama_mesin_esc = mysqli_real_escape_string($koneksi, $nama_mesin);
        $ip_mesin_esc = mysqli_real_escape_string($koneksi, $ip_mesin);
        $comm_key_esc = mysqli_real_escape_string($koneksi, $comm_key);
        $port_esc = mysqli_real_escape_string($koneksi, $port);
        
        // Check if device exists (match by index)
        $id_mesin = null;
        if(isset($existing_devices[$i])) {
            $id_mesin = $existing_devices[$i]['id_mesin'];
        }
        
        if($id_mesin) {
            // Update existing device
            $id_mesin_esc = mysqli_real_escape_string($koneksi, $id_mesin);
            $sql = $koneksi->query("UPDATE tb_mesin SET 
                                   no_mesin = '$no_mesin_esc',
                                   nama_mesin = '$nama_mesin_esc',
                                   ip_mesin = '$ip_mesin_esc',
                                   comm_key = '$comm_key_esc',
                                   port = '$port_esc'
                                   WHERE id_mesin = '$id_mesin_esc'");
        } else {
            // Insert new device
            $sql = $koneksi->query("INSERT INTO tb_mesin (no_mesin, nama_mesin, ip_mesin, comm_key, port) 
                                   VALUES ('$no_mesin_esc', '$nama_mesin_esc', '$ip_mesin_esc', '$comm_key_esc', '$port_esc')");
        }
        
        if(!$sql) {
            $success = false;
        }
    }
    
    // Show success/error message
    if($success) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>
              Swal.fire({
                  icon: 'success',
                  title: 'Berhasil',
                  text: 'Konfigurasi semua mesin berhasil disimpan!',
                  confirmButtonColor: '#2563eb',
                  confirmButtonText: 'OK'
              }).then(() => {
                  window.location.href='?page=mesin';
              });
              </script>";
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>
              Swal.fire({
                  icon: 'error',
                  title: 'Gagal',
                  text: 'Terjadi kesalahan saat menyimpan konfigurasi mesin.',
                  confirmButtonColor: '#2563eb',
                  confirmButtonText: 'OK'
              });
              </script>";
    }
}
?>

<script>
// Simple tab switching function
function showDeviceTab(tabNumber) {
    // Hide all device contents
    for(let i = 1; i <= 4; i++) {
        const content = document.getElementById('device-' + i);
        if(content) {
            content.classList.add('d-none');
        }
        
        const tab = document.querySelector(`[onclick="showDeviceTab(${i})"]`);
        if(tab) {
            tab.classList.remove('border-brand-500', 'text-brand-600', 'bg-brand-50');
            tab.classList.add('border-transparent', 'text-slate-500');
        }
    }
    
    // Show selected device content
    const selectedContent = document.getElementById('device-' + tabNumber);
    if(selectedContent) {
        selectedContent.classList.remove('d-none');
    }
    
    // Highlight selected tab
    const selectedTab = document.querySelector(`[onclick="showDeviceTab(${tabNumber})"]`);
    if(selectedTab) {
        selectedTab.classList.remove('border-transparent', 'text-slate-500');
        selectedTab.classList.add('border-brand-500', 'text-brand-600', 'bg-brand-50');
    }
}

// Initialize first tab on page load
document.addEventListener('DOMContentLoaded', function() {
    showDeviceTab(1);
});
</script>