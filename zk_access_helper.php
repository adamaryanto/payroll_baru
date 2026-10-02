<?php
// Helper untuk sync ke DB Access ZK (att2000.mdb)
// Biar di ZK Attendance Management kiri langsung ada Nama tanpa Download

function _zk_find_mdb(){
    $candidates = [
        "C:\\Program Files (x86)\\att2000.mdb",
        "C:\\Program Files\\att2000.mdb",
        "C:\\Att2000.mdb",
        "C:\\ZKAccess\\att2000.mdb",
        "C:\\Program Files\\ZKTeco\\att2000.mdb",
        "C:\\Program Files (x86)\\ZKTeco\\att2000.mdb",
    ];
    foreach($candidates as $p){
        if(@file_exists($p)) return $p;
    }
    return null;
}

function _zk_get_mdb_conn($mdbPath){
    $conn = new COM("ADODB.Connection");
    $conn->CommandTimeout = 5;
    try{
        $conn->Open("Provider=Microsoft.Jet.OLEDB.4.0;Data Source={$mdbPath};");
    } catch(Exception $e){
        $conn->Open("Provider=Microsoft.ACE.OLEDB.12.0;Data Source={$mdbPath};");
    }
    return $conn;
}

function _zk_detect_cols($conn){
    $foundCol = 'USERID';
    $nameCol = 'Name';
    try{
        $cat = new COM("ADOX.Catalog");
        $cat->ActiveConnection = $conn;
        $cols = [];
        foreach($cat->Tables("USERINFO")->Columns as $c){
            $cols[strtoupper($c->Name)] = 1;
        }
        if(isset($cols['BADGENUMBER'])) $foundCol = 'Badgenumber';
        elseif(isset($cols['USERID'])) $foundCol = 'USERID';
        if(isset($cols['USERNAME'])) $nameCol = 'USERNAME';
        elseif(isset($cols['NAME'])) $nameCol = 'Name';
    } catch(Exception $e){
        try{ $conn->Execute("SELECT Badgenumber FROM USERINFO WHERE 1=0"); $foundCol='Badgenumber'; } catch(Exception $ex){}
        try{ $conn->Execute("SELECT USERNAME FROM USERINFO WHERE 1=0"); $nameCol='USERNAME'; } catch(Exception $ex){}
    }
    return ['foundCol'=>$foundCol, 'nameCol'=>$nameCol];
}

function zk_update_user($pin, $nama){
    if(PHP_OS_FAMILY !== 'Windows') return ['success'=>false,'msg'=>'Not Windows, COM unavailable'];
    if(!class_exists('COM')) return ['success'=>false,'msg'=>'COM extension disabled'];
    $mdbPath = _zk_find_mdb();
    if(!$mdbPath) return ['success'=>false,'msg'=>'att2000.mdb not found'];
    try{
        $conn = _zk_get_mdb_conn($mdbPath);
        $cols = _zk_detect_cols($conn);
        $foundCol = $cols['foundCol'];
        $nameCol = $cols['nameCol'];
        $pinEsc = str_replace("'","''",$pin);
        $namaEsc = str_replace("'","''",$nama);
        $rs = null;
        try{ $rs = $conn->Execute("SELECT * FROM USERINFO WHERE {$foundCol}='{$pinEsc}'"); } catch(Exception $e){}
        if($rs && !$rs->EOF){
            $conn->Execute("UPDATE USERINFO SET {$nameCol}='{$namaEsc}' WHERE {$foundCol}='{$pinEsc}'");
        } else {
            try{ $conn->Execute("INSERT INTO USERINFO ({$foundCol}, {$nameCol}) VALUES ('{$pinEsc}', '{$namaEsc}')"); }
            catch(Exception $e){
                try{ $conn->Execute("INSERT INTO USERINFO (USERID, Name) VALUES ('{$pinEsc}', '{$namaEsc}')"); }
                catch(Exception $ex){ throw $e; }
            }
        }
        $conn->Close();
        return ['success'=>true];
    } catch(Exception $e){
        return ['success'=>false,'msg'=>$e->getMessage()];
    } catch(Throwable $e){
        return ['success'=>false,'msg'=>$e->getMessage()];
    }
}

function zk_delete_user($pin){
    if(PHP_OS_FAMILY !== 'Windows') return ['success'=>false];
    if(!class_exists('COM')) return ['success'=>false];
    $mdbPath = _zk_find_mdb();
    if(!$mdbPath) return ['success'=>false];
    try{
        $conn = _zk_get_mdb_conn($mdbPath);
        $cols = _zk_detect_cols($conn);
        $foundCol = $cols['foundCol'];
        $pinEsc = str_replace("'","''",$pin);
        $conn->Execute("DELETE FROM USERINFO WHERE {$foundCol}='{$pinEsc}'");
        $conn->Close();
        return ['success'=>true];
    } catch(Exception $e){
        return ['success'=>false,'msg'=>$e->getMessage()];
    }
}

function zk_queue_update($pin, $nama){
    global $koneksi;
    if(!isset($koneksi) || !$koneksi) return ['success'=>false,'msg'=>'No DB'];
    $koneksi->query("CREATE TABLE IF NOT EXISTS zk_sync_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pin VARCHAR(50) NOT NULL,
        nama VARCHAR(255) NOT NULL,
        action VARCHAR(20) NOT NULL DEFAULT 'upsert',
        processed TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        processed_at DATETIME DEFAULT NULL
    )");
    $pinEsc = $koneksi->real_escape_string($pin);
    $namaEsc = $koneksi->real_escape_string($nama);
    $koneksi->query("INSERT INTO zk_sync_queue (pin, nama, action, created_at) VALUES ('$pinEsc', '$namaEsc', 'upsert', NOW())");
    return ['success'=>true];
}

function zk_queue_delete($pin){
    global $koneksi;
    if(!isset($koneksi) || !$koneksi) return ['success'=>false];
    $pinEsc = $koneksi->real_escape_string($pin);
    $koneksi->query("INSERT INTO zk_sync_queue (pin, nama, action, created_at) VALUES ('$pinEsc', '', 'delete', NOW())");
    return ['success'=>true];
}
?>