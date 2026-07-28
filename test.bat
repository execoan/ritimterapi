@echo off
rem RitimTerapi duman testi — cift tikla calistir.
rem Gercek veriye DOKUNMAZ: kendi gecici deposuyla ayri bir sunucu acar.
chcp 65001 >nul
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
"%PHP%" "%~dp0test\smoke.php"
echo.
pause
