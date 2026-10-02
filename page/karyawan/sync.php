<?php
// Include sync helper
include_once __DIR__ . '/../../device_sync_helper.php';
create_sync_log_table($koneksi);

$devices = get_all_devices($koneksi);
$sync_results = [];

// Handle sync request
if(isset($_POST['sync_all'])) {
    // Sync all karyawan to all devices
    foreach($devices as $device) {
        $result = sync_all_karyawan_to_device($koneksi, $device['id_mesin']);
        $sync_results[$device['id_mesin']] = $result;
        
        // Log the bulk sync
        log_sync_activity($koneksi, 'bulk_sync', $device['id_mesin'], null, 
                         $result['status'] ?? 'error', $result['message'] ?? 'Unknown error');
    }
}

// Handle sync to specific device
if(isset($_POST['sync_device'])) {
    $device_id = $_POST['device_id'];
    $result = sync_all_karyawan_to_device($koneksi, $device_id);
    $sync_results[$device_id] = $result;
    
    // Log the device sync
    log_sync_activity($koneksi, 'device_sync', $device_id, null, 
                     $result['status'] ?? 'error', $result['message'] ?? 'Unknown error');
}
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0">
                    <i class="fas fa-sync-alt mr-2"></i>Sinkronisasi ke Mesin Fingerprint
                </h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Sinkronkan data karyawan ke semua mesin absensi</p>
            </div>
            <div class="w-full md:w-auto mt-2 md:mt-0">
                <a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
            </div>
        </div>

        <div class="p-6">
            <?php if(!empty($sync_results)): ?>
            <div class="mb-8">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Hasil Sinkronisasi</h4>
                <div class="space-y-4">
                    <?php foreach($sync_results as $device_id => $result): 
                        $device = array_filter($devices, function($d) use ($device_id) {
                            return $d['id_mesin'] == $device_id;
                        });
                        $device = reset($device);
                    ?>
                    <div class="p-4 rounded-lg border <?php echo $result['status'] == 'success' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'; ?>">
                        <div class="flex items-center justify-between">
                            <div>
                                <h5 class="font-semibold <?php echo $result['status'] == 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                                    <i class="fas fa-<?php echo $result['status'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                                    <?php echo htmlspecialchars($device['nama_mesin'] ?? 'Unknown Device'); ?>
                                </h5>
                                <p class="text-sm <?php echo $result['status'] == 'success' ? 'text-green-600' : 'text-red-600'; ?> mt-1">
                                    <?php echo htmlspecialchars($result['message'] ?? 'No message'); ?>
                                </p>
                                <?php if(isset($result['total'])): ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    Total: <?php echo $result['total']; ?> karyawan | 
                                    Berhasil: <?php echo $result['synced'] ?? 0; ?> | 
                                    Gagal: <?php echo $result['failed'] ?? 0; ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $result['status'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $result['status'] == 'success' ? 'BERHASIL' : 'GAGAL'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sync All Devices -->
            <div class="mb-8 p-6 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl border border-indigo-100">
                <h4 class="text-lg font-semibold text-indigo-800 mb-3">Sinkronisasi Semua Mesin</h4>
                <p class="text-gray-600 mb-4">Sinkronkan semua data karyawan aktif ke semua mesin fingerprint yang terkoneksi.</p>
                
                <?php 
                $active_karyawan = $koneksi->query("SELECT COUNT(*) as total FROM ms_karyawan WHERE status_karyawan = 'Aktif'");
                $total_karyawan = $active_karyawan->fetch_assoc()['total'];
                ?>
                
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Total karyawan aktif: <span class="font-bold text-indigo-600"><?php echo $total_karyawan; ?></span></p>
                        <p class="text-sm text-gray-500">Total mesin terkoneksi: <span class="font-bold text-indigo-600"><?php echo count($devices); ?></span></p>
                    </div>
                    <form method="POST">
                        <button type="submit" name="sync_all" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                            <i class="fas fa-play-circle"></i>
                            Jalankan Sinkronisasi ke Semua Mesin
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sync Individual Devices -->
            <div class="mb-8">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Sinkronisasi per Mesin</h4>
                <p class="text-gray-600 mb-6">Pilih mesin tertentu untuk sinkronisasi atau untuk testing koneksi.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php foreach($devices as $device): 
                        $is_online = test_device_connection($device['ip_mesin'], $device['port']);
                        $sync_status = get_sync_status($koneksi, $device['id_mesin']);
                    ?>
                    <div class="p-5 rounded-xl border <?php echo $is_online ? 'border-green-200' : 'border-gray-200'; ?> bg-white shadow-sm">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h5 class="font-bold text-gray-800"><?php echo htmlspecialchars($device['nama_mesin']); ?></h5>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($device['ip_mesin']); ?>:<?php echo htmlspecialchars($device['port']); ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $is_online ? 'ONLINE' : 'OFFLINE'; ?>
                            </span>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="text-sm">
                                <span class="text-gray-500">Status Sync:</span>
                                <span class="font-semibold <?php echo $sync_status['synced'] ? 'text-green-600' : 'text-amber-600'; ?> ml-2">
                                    <?php echo $sync_status['synced'] ? 'SYNCED' : 'OUT OF SYNC'; ?>
                                </span>
                            </div>
                            
                            <div class="text-sm">
                                <span class="text-gray-500">Karyawan:</span>
                                <span class="font-semibold text-indigo-600 ml-2">
                                    Web: <?php echo $sync_status['web_total']; ?> | 
                                    Device: <?php echo $sync_status['device_total']; ?>
                                </span>
                            </div>
                            
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="device_id" value="<?php echo $device['id_mesin']; ?>">
                                <button type="submit" name="sync_device" 
                                        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors text-sm flex items-center justify-center gap-2 <?php echo !$is_online ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                        <?php echo !$is_online ? 'disabled' : ''; ?>>
                                    <i class="fas fa-sync-alt"></i>
                                    Sync ke Mesin Ini
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sync History -->
            <div class="mt-10">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Sinkronisasi</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Waktu</th>
                                <th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Tipe</th>
                                <th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Device</th>
                                <th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Karyawan</th>
                                <th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Pesan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $history = $koneksi->query("SELECT sl.*, m.nama_mesin, k.nama_karyawan 
                                                       FROM tb_sync_log sl
                                                       LEFT JOIN tb_mesin m ON sl.device_id = m.id_mesin
                                                       LEFT JOIN ms_karyawan k ON sl.karyawan_id = k.id_karyawan
                                                       ORDER BY sl.timestamp DESC LIMIT 20");
                            while($log = $history->fetch_assoc()):
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($log['timestamp'])); ?></td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        <?php echo htmlspecialchars($log['type']); ?>
                                    </span>
                                </td>
                                <td class="py-2 px-3 font-medium text-gray-700"><?php echo htmlspecialchars($log['nama_mesin'] ?? '-'); ?></td>
                                <td class="py-2 px-3 text-gray-600"><?php echo htmlspecialchars($log['nama_karyawan'] ?? '-'); ?></td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $log['status'] == 'success' ? 'bg-green-100 text-green-800' : ($log['status'] == 'error' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'); ?>">
                                        <?php echo strtoupper($log['status']); ?>
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-gray-500 text-xs"><?php echo htmlspecialchars($log['message']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .table-modern {
        border-collapse: separate;
        border-spacing: 0;
    }
    .table-modern th {
        background-color: #f8fafc;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }
    .table-modern td, .table-modern th {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-modern tbody tr:hover {
        background-color: #f7fafc;
    }
</style>