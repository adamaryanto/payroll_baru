@echo off
REM Auto Fingerprint Sweep - dijalankan via Windows Task Scheduler.
REM Portabel: jalanin sweep.php yang SEKAMPUNG dengan file .bat ini,
REM dengan PHP yang sesuai (XAMPP -> php XAMPP, selain itu -> php Laragon).
SET APPDIR=%~dp0
SET APPDIR=%APPDIR:~0,-1%
echo %APPDIR% | findstr /I "xampp" >nul
IF %ERRORLEVEL%==0 (
  SET PHP=C:\xampp\php\php.exe
) ELSE (
  SET PHP=C:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe
)
IF NOT EXIST "%PHP%" (
  echo [%DATE% %TIME%] PHP tidak ketemu: %PHP% >> "%APPDIR%\sweep.log" 2>&1
  exit /b 1
)
REM Script sudah nulis sweep.log sendiri; .bat cuma menangkap error fatal (stderr).
"%PHP%" "%APPDIR%\auto_fingerprint_sweep.php" --master=auto 2>> "%APPDIR%\sweep.log"
