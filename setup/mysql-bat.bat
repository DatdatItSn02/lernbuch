@echo off
rem Bat MySQL 9.7 dung chung voi LoWo - F:\dev\mysql97, cong 3307 - neu dang tat.
call "%~dp0_env.bat"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0mysql.ps1" bat
set "MA=%errorlevel%"
if not defined CI ping -n 4 127.0.0.1 >nul
exit /b %MA%
