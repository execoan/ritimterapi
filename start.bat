@echo off
rem RitimTerapi'yi baslatir: cift tikla, tarayici kendiliginden acilir.
rem Telefon/tablet ayni Wi-Fi'dan asagida yazan adresle baglanir.
cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

echo.
echo  ================================================
echo   RitimTerapi baslatiliyor...
echo.
echo   Bu bilgisayar :  http://localhost:8590
echo   Telefon/tablet (ayni Wi-Fi, calisani deneyin):
for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*'} | ForEach-Object {$_.IPAddress}"') do echo     http://%%i:8590
echo.
echo   Kapatmak icin bu pencereyi kapatin.
echo   (Ilk calistirmada Windows guvenlik duvari sorarsa
echo    "Ozel aglar" icin izin verin.)
echo  ================================================
echo.
start "" /b cmd /c "timeout /t 2 >nul & start http://localhost:8590"
"%PHP%" -S 0.0.0.0:8590 router.php
