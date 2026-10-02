<?php
// Get all devices
$devices = [];
$result = $koneksi->query("SELECT * FROM tb_mesin ORDER BY no_mesin");
while($row = $result->fetch_assoc()) {
    $devices[] = $row;
}
for($i = count($devices); $i < 4; $i++) {
    $devices[] = ['id_mesin'=>'','no_mesin'=>$i+1,'nama_mesin'=>'Mesin '.($i+1),'ip_mesin'=>'','comm_key'=>'0','port'=>'4370','protocol'=>'soap'];
}
$devices = array_values($devices);
include_once __DIR__ . '/../../device_sync_helper.php';
?>
<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0">
                    <i class="fas fa-microchip mr-2"></i>Setting Konfigurasi Mesin Absensi
                </h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Kelola dan konfigurasi perangkat fingerprint X606S</p>
            </div>
            <div class="w-full md:w-auto mt-2 md:mt-0">
                <a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
            </div>
        </div>

        <div class="p-6">
            <!-- Smart Notification -->
            <?php if(isset($_GET['notif'])):
                $notif_class = $_GET['notif'] === 'update' ? 'green' : 'amber';
                $notif_title = $_GET['notif'] === 'update' ? 'Berhasil' : 'Update Tersimpan';
                $notif_msg = $_GET['notif'] === 'update' ? 'Konfigurasi mesin berhasil diperbarui!' : 'Data Anda telah diperbarui. Pastikan untuk melakukan sinkronisasi ke mesin-mesin fingerprint.';
            ?>
            <div class="mb-6 p-4 rounded-lg border <?php echo $_GET['notif'] === 'update' ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'; ?>">
                <h4 class="text-lg font-semibold <?php echo $_GET['notif'] === 'update' ? 'text-green-800' : 'text-amber-800'; ?> mb-2">
                    <i class="fas fa-<?php echo $_GET['notif'] === 'update' ? 'check-circle' : 'info-circle'; ?> mr-2"></i><?php echo $notif_title; ?>
                </h4>
                <p class="text-sm <?php echo $_GET['notif'] === 'update' ? 'text-green-600' : 'text-amber-600'; ?>"><?php echo $notif_msg; ?></p>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="flex border-b mb-6">
                <?php foreach($devices as $index=>$device):
                    $is_online = !empty($device['ip_mesin']) ? test_device_connection($device['ip_mesin'],$device['port']) : false;
                ?>
                <button type="button" onclick="showDeviceTab(<?php echo $index+1; ?>)" id="tab-<?php echo $index+1; ?>" class="device-tab px-4 py-2 text-sm font-semibold border-b-2 <?php echo $index===0?'border-indigo-600 text-indigo-600':'border-transparent text-gray-500'; ?>">
                    <?php echo htmlspecialchars($device['nama_mesin']); ?>
                    <span class="ml-1.5 inline-block w-2 h-2 rounded-full <?php echo $is_online?'bg-green-500':'bg-red-500'; ?>"></span>
                </button>
                <?php endforeach; ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php foreach($devices as $index=>$device):
                    $is_online = !empty($device['ip_mesin']) ? test_device_connection($device['ip_mesin'],$device['port']) : false;
                ?>
                <div id="device-<?php echo $index+1; ?>" class="device-content" style="<?php echo $index===0?'':'display:none;'; ?>">
                    <!-- Device Panel -->
                    <div class="mb-6 p-6 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl border border-indigo-100">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-5">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-600 text-white font-bold text-sm shadow-sm">
                                    <?php echo $index+1; ?>
                                </span>
                                <div>
                                    <h5 class="text-base font-bold text-gray-800"><?php echo htmlspecialchars($device['nama_mesin']); ?></h5>
                                    <p class="text-xs text-gray-500">Device <?php echo $index+1; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $is_online?'bg-green-100 text-green-800':'bg-red-100 text-red-800'; ?>">
                                    <span class="inline-block w-1.5 h-1.5 rounded-full mr-1 <?php echo $is_online?'bg-green-500':'bg-red-500'; ?>"></span>
                                    <?php echo $is_online?'TERKONEKSI':'OFFLINE'; ?>
                                </span>
                                <span class="px-3 py-1 text-xs font-medium rounded-full bg-white text-gray-600 border border-gray-200">
                                    <i class="fas fa-network-wired mr-1"></i><?php echo htmlspecialchars($device['ip_mesin'] ?: '-'); ?>:<?php echo htmlspecialchars($device['port'] ?: '4370'); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Info box -->
                        <div class="mb-5 p-4 bg-white rounded-lg border border-indigo-100 flex gap-3">
                            <i class="fas fa-info-circle text-indigo-500 mt-0.5"></i>
                            <div class="text-sm text-gray-600 leading-relaxed">
                                <strong class="text-indigo-800">Catatan:</strong> Pastikan mesin terkoneksi dengan jaringan LAN yang sama sebelum menyimpan konfigurasi. X606S memakai SOAP; X902 memakai biner ZK-UDP pada port <code class="bg-indigo-50 px-1.5 py-0.5 rounded text-indigo-700 text-xs"><?php echo htmlspecialchars($device['port'] ?: '4370'); ?></code>.
                            </div>
                        </div>

                        <!-- Form grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">No Mesin <span class="text-red-500">*</span></label>
                                <input type="text" name="tnomesin[]" value="<?php echo htmlspecialchars($device['no_mesin']); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Mesin <span class="text-red-500">*</span></label>
                                <input type="text" name="tnamamesin[]" value="<?php echo htmlspecialchars($device['nama_mesin']); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">IP Mesin</label>
                                <input type="text" name="tipmesin[]" value="<?php echo htmlspecialchars($device['ip_mesin']); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm" placeholder="10.10.10.x">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Comm Key</label>
                                <input type="text" name="tcommkey[]" value="<?php echo htmlspecialchars($device['comm_key']); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Port</label>
                                <input type="text" name="tport[]" value="<?php echo htmlspecialchars($device['port']); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Protokol</label>
                                <select name="tprotocol[]" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                                    <option value="soap" <?= (($device['protocol'] ?? 'soap')==='soap')?'selected':'' ?>>SOAP (X606S)</option>
                                    <option value="zkudp" <?= (($device['protocol'] ?? '')==='zkudp')?'selected':'' ?>>Biner ZK-UDP (X902)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Footer -->
                <div class="flex flex-col md:flex-row justify-between items-center pt-5 border-t border-gray-100 gap-3">
                    <div class="text-sm text-gray-500">
                        <span class="text-red-500 font-semibold">*</span> Semua field wajib diisi dengan benar
                    </div>
                    <button type="submit" name="simpan" value="Simpan" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-8 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                        <i class="fas fa-save"></i> Simpan Semua Konfigurasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if(isset($_POST['simpan'])) {
    $existing_devices = [];
    $result = $koneksi->query("SELECT id_mesin, no_mesin FROM tb_mesin ORDER BY no_mesin");
    while($row = $result->fetch_assoc()) $existing_devices[] = $row;
    $device_count = isset($_POST['tnomesin']) ? count($_POST['tnomesin']) : 0;
    $success = true;
    for($i=0;$i<$device_count;$i++){
        $no_mesin = $_POST['tnomesin'][$i] ?? ($i+1);
        $nama_mesin = $_POST['tnamamesin'][$i] ?? "Mesin ".($i+1);
        $ip_mesin = $_POST['tipmesin'][$i] ?? "";
        $comm_key = $_POST['tcommkey'][$i] ?? "0";
        $port = $_POST['tport'][$i] ?? "4370";
        $protocol = ($_POST['tprotocol'][$i] ?? "soap") === "zkudp" ? "zkudp" : "soap";
        $no_mesin_esc = mysqli_real_escape_string($koneksi,$no_mesin);
        $nama_mesin_esc = mysqli_real_escape_string($koneksi,$nama_mesin);
        $ip_mesin_esc = mysqli_real_escape_string($koneksi,$ip_mesin);
        $comm_key_esc = mysqli_real_escape_string($koneksi,$comm_key);
        $port_esc = mysqli_real_escape_string($koneksi,$port);
        $protocol_esc = mysqli_real_escape_string($koneksi,$protocol);
        $id_mesin = $existing_devices[$i]['id_mesin'] ?? null;
        if($id_mesin){
            $id_mesin_esc = mysqli_real_escape_string($koneksi,$id_mesin);
            $sql = $koneksi->query("UPDATE tb_mesin SET no_mesin='$no_mesin_esc',nama_mesin='$nama_mesin_esc',ip_mesin='$ip_mesin_esc',comm_key='$comm_key_esc',port='$port_esc',protocol='$protocol_esc' WHERE id_mesin='$id_mesin_esc'");
            if($sql && $koneksi->query("SHOW COLUMNS FROM tb_mesin LIKE 'protocol'")->num_rows==0){
                $koneksi->query("ALTER TABLE tb_mesin ADD COLUMN protocol VARCHAR(10) NOT NULL DEFAULT 'soap'");
                $sql = $koneksi->query("UPDATE tb_mesin SET protocol='$protocol_esc' WHERE id_mesin='$id_mesin_esc'");
            }
        }else{
            $has_protocol = $koneksi->query("SHOW COLUMNS FROM tb_mesin LIKE 'protocol'")->num_rows>0;
            $sql = $has_protocol
                ? $koneksi->query("INSERT INTO tb_mesin (no_mesin,nama_mesin,ip_mesin,comm_key,port,protocol) VALUES ('$no_mesin_esc','$nama_mesin_esc','$ip_mesin_esc','$comm_key_esc','$port_esc','$protocol_esc')")
                : $koneksi->query("INSERT INTO tb_mesin (no_mesin,nama_mesin,ip_mesin,comm_key,port) VALUES ('$no_mesin_esc','$nama_mesin_esc','$ip_mesin_esc','$comm_key_esc','$port_esc')");
            if($sql && !$has_protocol){
                $koneksi->query("ALTER TABLE tb_mesin ADD COLUMN protocol VARCHAR(10) NOT NULL DEFAULT 'soap'");
                $koneksi->query("UPDATE tb_mesin SET protocol='$protocol_esc' WHERE no_mesin='$no_mesin_esc' AND nama_mesin='$nama_mesin_esc'");
            }
        }
        if(!$sql) $success=false;
    }
    if($success){
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>Swal.fire({icon:'success',title:'Berhasil',text:'Konfigurasi semua mesin berhasil disimpan!',confirmButtonColor:'#2563eb'}).then(()=>{window.location.href='?page=mesin&notif=update';});</script>";
    }else{
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>Swal.fire({icon:'error',title:'Gagal',text:'Terjadi kesalahan saat menyimpan.'});</script>";
    }
}
?>
<script>
function showDeviceTab(n){
    for(let i=1;i<=4;i++){
        const c=document.getElementById('device-'+i);
        const t=document.getElementById('tab-'+i);
        if(c) c.style.display=(i===n?'block':'none');
        if(t){
            if(i===n){
                t.className='device-tab px-4 py-2 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600';
            }else{
                t.className='device-tab px-4 py-2 text-sm font-semibold border-b-2 border-transparent text-gray-500';
            }
        }
    }
}
document.addEventListener('DOMContentLoaded',()=>showDeviceTab(1));
</script>