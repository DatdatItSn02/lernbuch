@echo off
rem Dung chung cho cac file .bat khac: dat PATH chi cho cua so hien tai, khong sua PATH he thong.
rem Cong cu nam o F:\dev, dung chung voi LoWo: PHP 8.5, Composer, Node.js 26, MySQL 9.7 cong 3307.
if not defined LERNBUCH_DEV set "LERNBUCH_DEV=F:\dev"
if not defined LERNBUCH_MYSQL_PORT set "LERNBUCH_MYSQL_PORT=3307"
set "LERNBUCH_WEB=http://127.0.0.1:8001"
set "PATH=%LERNBUCH_DEV%\php85;%LERNBUCH_DEV%\composer;%LERNBUCH_DEV%\composer\home\vendor\bin;%LERNBUCH_DEV%\node26;%LERNBUCH_DEV%\mysql97\bin;%LERNBUCH_DEV%\git\cmd;%PATH%"
set "COMPOSER_HOME=%LERNBUCH_DEV%\composer\home"
set "COMPOSER_CACHE_DIR=%LERNBUCH_DEV%\cache\composer"
set "npm_config_cache=%LERNBUCH_DEV%\cache\npm"
