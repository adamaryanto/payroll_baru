@echo off
REM Auto Fingerprint Sweep - dijalankan via Windows Task Scheduler tiap N jam.
REM Deteksi otomatis XAMPP vs Laragon.
IF EXIST "C:\xampp\php\php.exe" (
  SET PHP=C:\xampp\php\php.exe
  SET APPDIR=C:\xampp\htdocs\absen
) ELSE (
  SET PHP=C:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe
  SET APPDIR=C:\laragon\www\payroll
)
"%PHP%" "%APPDIR%\auto_fingerprint_sweep.php" --master=auto >> "%APPDIR%\sweep.log" 2>&1
