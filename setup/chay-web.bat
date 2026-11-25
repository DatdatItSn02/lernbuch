@echo off
rem Mo Lernbuch tren trinh duyet: bat MySQL, chay may chu PHP o http://127.0.0.1:8001
rem Dung 127.0.0.1 thay cho localhost de cookie khong lan voi LoWo dang chay o localhost:8000.
rem Giao dien dung ban da build san - setup\cap-nhat.bat da build. Dong cua so nay de tat may chu.
call "%~dp0_env.bat"
if not exist "%~dp0..\vendor\autoload.php" goto chua-cap-nhat
if not exist "%~dp0..\public\build\manifest.json" goto chua-cap-nhat
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0mysql.ps1" bat
if errorlevel 1 goto het
cd /d "%~dp0.."
title Lernbuch - may chu dang chay o %LERNBUCH_WEB% - dong cua so nay de tat
start "" /b powershell -NoProfile -Command "Start-Sleep -Seconds 2; Start-Process '%LERNBUCH_WEB%'"
php artisan serve --host=127.0.0.1 --port=8001
goto het
:chua-cap-nhat
echo Chua cai thu vien hoac chua build giao dien. Hay chay setup\cap-nhat.bat truoc.
:het
if not defined CI pause
