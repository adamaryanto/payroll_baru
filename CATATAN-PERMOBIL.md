# Catatan: Hitungan Per Mobil (Boneless)

Ada **2 rumus berbeda** untuk biaya per mobil. Jangan disamakan tanpa keputusan.

## 1. Aplikasi — pakai tarif MANUAL (master)
- Lokasi: `page/boneless/tambah.php` & `ubah.php` → fungsi `calculate()` (JS), box **Grand Total Akhir**
- Rumus: `grand = boneless_net + (jumlah_mobil × tarif_master)`
- `tarif_master` = `tb_biayamobil.biaya_mobil`, diubah di sidebar **MASTER → Mobil** (`?page=boneless&aksi=master`, file `page/boneless/m_boneless.php`). Saat ini: **Rp 1.100.000**
- `jumlah_mobil` = input manual per tanggal (`tb_boneless.jumlah_mobil`, kolom "Jumlah Mobil (Potong)")
- Box **Total Boneless Murni** = `plus − minus` saja (tanpa mobil) → ini yang sama dengan TOTAL file (J310)

## 2. File Excel contoh — pakai RUMUS dari data (tanpa tarif manual)
- Contoh: `REALISASI ABSENSI 08 OKTOBER 2026.xlsx`, sheet REALISASI baris 341 (PERENCANAAN baris 313, sama polanya)
- Rumus: `per_mobil = (BIAYA_PABRIK + BONLESS) / POTONG`
- `BIAYA_PABRIK` = total upah dibayar (kolom O, hasil `SUM`)
- `BONLESS` = net boneless (yang plus ditambah, PENGURANGAN dikurang)
- `POTONG` = jumlah mobil, **diketik manual** di file (13)
- Tarif master Rp 1,1 jt **tidak dipakai sama sekali** di file

## Kalau suatu saat mau diubah/disamakan, pilih salah satu:
- **A. Aplikasi ikut file**: ganti `calculate()` agar Grand Total = `(total_upah + boneless_net) / jumlah_mobil`. Butuh total upah tersedia di form boneless (saat ini tidak ada).
- **B. File ikut aplikasi**: tambah tarif master ke file, Grand Total file = `boneless_net + (mobil × tarif)`.
