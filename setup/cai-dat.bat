@echo off
rem Cai (neu chua co) PHP 8.5, Composer, Node.js 26, MySQL 9.7 vao F:\dev - dung chung voi LoWo, khong anh huong XAMPP.
rem Cuoi cung tao database lernbuch va lernbuch_test.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0cai-dat.ps1"
set "MA=%errorlevel%"
echo.
echo Ket qua da ghi vao: %~dp0ket-qua-cai-dat.txt
if not defined CI pause
exit /b %MA%
