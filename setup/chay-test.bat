@echo off
rem Chay toan bo kiem thu cua Lernbuch, ket qua ghi vao setup\ket-qua-test.txt
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0chay-test.ps1"
set "MA=%errorlevel%"
echo.
echo Ket qua: %~dp0ket-qua-test.txt
if not defined CI pause
exit /b %MA%
