# Lernbuch - bật hoặc tắt MySQL 9.7 dùng chung với LoWo (F:\dev\mysql97, cổng 3307).
# Dùng: mysql.ps1 bat | tat   (setup\mysql-bat.bat, setup\mysql-tat.bat gọi file này)
param([Parameter(Mandatory = $true)][ValidateSet('bat', 'tat')][string]$Viec)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lernbuch-chung.ps1')

$code = 0
try {
    try { [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding $false } catch { }
    if ($Viec -eq 'bat') {
        Start-SharedMySql
    } else {
        Stop-SharedMySql
        Write-Note 'LoWo cũng dùng MySQL này; bật lại bằng setup\mysql-bat.bat.'
    }
} catch {
    $code = 1
    Write-Host "LỖI: $($_.Exception.Message)" -ForegroundColor Red
}
exit $code
