@echo off
rem Mo cua so lenh dung PHP 8.5, Composer, Node.js 26 cho Lernbuch, dang dung o thu muc code.
call "%~dp0_env.bat"
if not exist "%LERNBUCH_DEV%\php85\php.exe" goto chua-cai
cd /d "%~dp0.."
title Lernbuch - PHP 8.5, Node 26, MySQL cong %LERNBUCH_MYSQL_PORT%
echo Cua so nay dung PHP 8.5 cho Lernbuch. Cac cua so khac van dung PHP 7.4 cua XAMPP.
php -r "echo 'PHP ', PHP_VERSION, PHP_EOL;"
node -v
cmd /k
exit /b 0
:chua-cai
echo Chua cai moi truong. Hay chay setup\cai-dat.bat truoc.
pause
exit /b 1
