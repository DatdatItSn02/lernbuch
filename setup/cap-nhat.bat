@echo off
rem Lay code moi tu GitHub, cai thu vien, cap nhat database, build roi chay toan bo test.
rem Ca khoi lenh nam trong ngoac: cmd doc het truoc khi chay, nen file nay duoc thay ban moi giua chung van an toan.
(
  cd /d "%~dp0"
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0cap-nhat.ps1" %*
  echo.
  echo Ket qua: setup\ket-qua-cap-nhat.txt va setup\ket-qua-test.txt
  if not defined CI pause
  exit /b
)
