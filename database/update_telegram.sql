-- =========================================================
-- Fitur Telegram Bot (Setting, Log, Anti-dobel laporan)
-- Tidak ada kolom baru di tabel lama. Aman dijalankan berulang.
-- Bisa juga dibuat otomatis oleh telegram_helper.php saat pertama dipakai.
-- =========================================================

CREATE TABLE IF NOT EXISTS tb_telegram (
    id TINYINT NOT NULL DEFAULT 1,
    bot_token VARCHAR(255) NOT NULL DEFAULT '',
    owner_chat_id VARCHAR(64) NOT NULL DEFAULT '',
    group_chat_id VARCHAR(64) NOT NULL DEFAULT '',
    jam_laporan TIME NOT NULL DEFAULT '08:00:00',
    enabled TINYINT NOT NULL DEFAULT 0,
    base_url VARCHAR(255) NOT NULL DEFAULT 'http://localhost/payroll',
    rencana_offset TINYINT NOT NULL DEFAULT 0,
    realisasi_offset TINYINT NOT NULL DEFAULT -1,
    kirim_rencana TINYINT NOT NULL DEFAULT 1,
    kirim_realisasi TINYINT NOT NULL DEFAULT 1,
    secret VARCHAR(64) NOT NULL DEFAULT '',
    update_id BIGINT NOT NULL DEFAULT 0,
    last_run DATETIME DEFAULT NULL,
    last_sent DATE DEFAULT NULL,
    tgl_updt DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tb_telegram_sent (
    tgl DATE NOT NULL,
    jenis VARCHAR(20) NOT NULL,
    sent_at DATETIME NOT NULL,
    PRIMARY KEY (tgl, jenis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tb_telegram_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tgl DATETIME NOT NULL,
    aksi VARCHAR(40) NOT NULL,
    id_ref INT NOT NULL DEFAULT 0,
    oleh VARCHAR(120) NOT NULL DEFAULT '',
    ket TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Baris konfigurasi tunggal (secret diisi otomatis oleh helper bila kosong)
INSERT INTO tb_telegram (id, secret, tgl_updt)
SELECT 1, REPLACE(UUID(), '-', ''), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tb_telegram WHERE id = 1);
