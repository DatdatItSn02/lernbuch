@echo off
rem Tao tai khoan quan tri dang nhap /admin: hoi email, ho ten, mat khau.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tao-admin.ps1"
set "MA=%errorlevel%"
if not defined CI pause
exit /b %MA%
