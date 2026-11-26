@echo off
rem Nhap noi dung Lernbuch cu vao database: chay thu (bao cao, chua ghi), hoi roi moi ghi.
rem Mac dinh doc nguon-cu\lernweb.zip; keo file hoac thu muc khac vao file nay de doc cho khac.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0nhap-du-lieu.ps1" -Nguon "%~1"
set "MA=%errorlevel%"
echo.
echo Ket qua: setup\ket-qua-nhap-du-lieu.txt
if not defined CI pause
exit /b %MA%
