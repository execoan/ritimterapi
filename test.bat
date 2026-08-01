@echo off
rem RitimTerapi duman testi — cift tikla calistir.
rem Gercek veriye DOKUNMAZ: kendi gecici deposuyla ayri bir sunucu acar.
chcp 65001 >nul
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
node "%~dp0test\zamanlama.test.js"
if errorlevel 1 goto :son
node "%~dp0test\ritim-ogrenme.test.js"
if errorlevel 1 goto :son
node "%~dp0test\metronom-cekirdegi.test.js"
if errorlevel 1 goto :son
node "%~dp0test\metronom-setlist.test.js"
if errorlevel 1 goto :son
node "%~dp0test\motor-koordinasyon.test.js"
if errorlevel 1 goto :son
node "%~dp0test\grup-atolyesi.test.js"
if errorlevel 1 goto :son
node "%~dp0test\poliritim.test.js"
if errorlevel 1 goto :son
node "%~dp0test\tini.test.js"
if errorlevel 1 goto :son
echo.
"%PHP%" "%~dp0test\statik.php"
if errorlevel 1 goto :son
"%PHP%" "%~dp0test\migration-v13.php"
if errorlevel 1 goto :son
"%PHP%" "%~dp0test\olcum.test.php"
if errorlevel 1 goto :son
"%PHP%" "%~dp0test\kilitli-dil.test.php"
if errorlevel 1 goto :son
"%PHP%" "%~dp0test\smoke.php"
:son
echo.
pause
