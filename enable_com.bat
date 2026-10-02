@echo off
title ZK Sync - Enable COM Extension
color 0A
echo.
echo  ==========================================
echo   ZK SYNC - AUTO SETUP COM EXTENSION
echo  ==========================================
echo.

:: Cari php.ini
set PHPINI=
if exist "C:\xampp\php\php.ini" set PHPINI=C:\xampp\php\php.ini
if exist "C:\php\php.ini" set PHPINI=C:\php\php.ini

if "%PHPINI%"=="" (
    echo  [ERROR] php.ini tidak ditemukan!
    echo  Pastikan XAMPP terinstall di C:\xampp
    echo.
    pause
    exit /b 1
)

echo  [1/4] php.ini ditemukan: %PHPINI%

:: Backup php.ini
copy "%PHPINI%" "%PHPINI%.bak" >nul 2>&1
echo  [2/4] Backup php.ini tersimpan (*.bak)

:: Cek apakah sudah aktif
findstr /i "^[^;]*extension=com_dotnet" "%PHPINI%" >nul 2>&1
if %errorlevel%==0 (
    echo  [3/4] COM extension SUDAH aktif - skip edit
    goto :restart
)

:: Edit php.ini: uncomment atau tambah extension=com_dotnet
echo  [3/4] Mengaktifkan COM extension...

:: Coba uncomment yang ada
powershell -Command "(Get-Content '%PHPINI%') -replace '^;\s*(extension\s*=\s*com_dotnet)', '$1' | Set-Content '%PHPINI%'" 2>nul

:: Cek lagi, kalau masih belum ada, tambah
findstr /i "extension=com_dotnet" "%PHPINI%" >nul 2>&1
if %errorlevel% neq 0 (
    echo. >> "%PHPINI%"
    echo ; Enable COM/DOTNET for ZK Attendance >> "%PHPINI%"
    echo extension=com_dotnet >> "%PHPINI%"
)

echo  [OK] COM extension diaktifkan

:restart
echo  [4/4] Restart Apache...
echo.

:: Stop Apache
net stop "Apache2.4" >nul 2>&1
if errorlevel 1 (
    net stop "Apache" >nul 2>&1
    taskkill /f /im httpd.exe >nul 2>&1
)

echo  Apache stopped. Tunggu 3 detik...
timeout /t 3 /nobreak >nul

:: Start Apache
net start "Apache2.4" >nul 2>&1
if errorlevel 1 (
    net start "Apache" >nul 2>&1
)

timeout /t 3 /nobreak >nul

:: Verifikasi
tasklist /fi "imagename eq httpd.exe" | find /i "httpd.exe" >nul
if %errorlevel%==0 (
    echo  ==========================================
    echo   [OK] SETUP SELESAI!
    echo  ==========================================
    echo.
    echo  COM extension sudah aktif.
    echo  Sekarang test tambah karyawan:
    echo  http://localhost/absen/page=karyawan^&aksi=tambah
    echo.
) else (
    echo  ==========================================
    echo   [WARNING] Apache belum jalan
    echo  ==========================================
    echo.
    echo  Buka XAMPP Control Panel ^> klik Start di Apache
    echo.
)

:: Cleanup: hapus diri sendiri setelah 5 detik
echo Script ini akan otomatis terhapus dalam 5 detik...
timeout /t 5 /nobreak >nul
del "%~f0" >nul 2>&1