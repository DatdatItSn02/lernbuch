@echo off
rem Tat MySQL dung chung. LoWo cung dang dung MySQL nay.
call "%~dp0_env.bat"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0mysql.ps1" tat
set "MA=%errorlevel%"
if not defined CI ping -n 4 127.0.0.1 >nul
exit /b %MA%
