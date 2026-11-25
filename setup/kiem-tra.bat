@echo off
rem Ghi phien ban va trang thai moi truong ra setup\ket-qua-kiem-tra.txt de Claude doc. Khong ghi noi dung .env.
call "%~dp0_env.bat"
set "OUT=%~dp0ket-qua-kiem-tra.txt"
> "%OUT%" echo === Kiem tra moi truong Lernbuch %date% %time%
>> "%OUT%" echo --- PHP
php -v >> "%OUT%" 2>&1
php -m >> "%OUT%" 2>&1
php -r "echo 'php.ini: ', php_ini_loaded_file(), PHP_EOL;" >> "%OUT%" 2>&1
>> "%OUT%" echo --- Composer
call composer --version --no-ansi >> "%OUT%" 2>&1
>> "%OUT%" echo --- Node.js va npm
node -v >> "%OUT%" 2>&1
call npm -v >> "%OUT%" 2>&1
>> "%OUT%" echo --- MySQL cong %LERNBUCH_MYSQL_PORT%
mysql --version >> "%OUT%" 2>&1
mysql -u root -h 127.0.0.1 -P %LERNBUCH_MYSQL_PORT% -e "SELECT VERSION(); SHOW DATABASES;" >> "%OUT%" 2>&1
>> "%OUT%" echo --- Git
git --version >> "%OUT%" 2>&1
git -C "%~dp0.." log -1 --format="%%h %%ci %%s" >> "%OUT%" 2>&1
git -C "%~dp0.." status --short >> "%OUT%" 2>&1
>> "%OUT%" echo --- Lernbuch
pushd "%~dp0.."
php artisan --version --no-ansi >> "%OUT%" 2>&1
if exist .env (>> "%OUT%" echo .env: co) else (>> "%OUT%" echo .env: chua co)
if exist vendor\autoload.php (>> "%OUT%" echo vendor: co) else (>> "%OUT%" echo vendor: chua co)
if exist node_modules (>> "%OUT%" echo node_modules: co) else (>> "%OUT%" echo node_modules: chua co)
if exist public\build\manifest.json (>> "%OUT%" echo giao dien da build: co) else (>> "%OUT%" echo giao dien da build: chua)
popd
>> "%OUT%" echo --- XAMPP, chi de doi chieu
if exist C:\xampp\php\php.exe (C:\xampp\php\php.exe -v >> "%OUT%" 2>&1) else (>> "%OUT%" echo Khong thay C:\xampp\php\php.exe)
>> "%OUT%" echo === Het
type "%OUT%"
echo.
echo Da ghi vao %OUT%
if not defined CI pause
exit /b 0
