# Lernbuch - tạo tài khoản quản trị đăng nhập /admin (nhân viên, is_staff = 1).
# Lệnh php artisan app:create-admin hỏi email, họ tên và mật khẩu (ít nhất 12 ký tự).

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lernbuch-chung.ps1')

$code = 1
try {
    Use-LernbuchEnvironment
    try { [Console]::InputEncoding = New-Object System.Text.UTF8Encoding $false } catch { }
    foreach ($f in @('vendor\autoload.php', '.env')) {
        if (-not (Test-Path -LiteralPath (Join-Path $Project $f))) { throw "Chưa có $f. Hãy chạy setup\cap-nhat.bat trước." }
    }
    Start-SharedMySql
    Write-Step 'Tạo tài khoản quản trị'
    Push-Location -LiteralPath $Project
    try {
        & (Get-Php) artisan app:create-admin
        $code = $LASTEXITCODE
    } finally {
        Pop-Location
    }
    if ($code -eq 0) { Write-Note "Đăng nhập ở http://$($WebHost):$WebPort/admin (mở web bằng setup\chay-web.bat)." }
} catch {
    Write-Host "LỖI: $($_.Exception.Message)" -ForegroundColor Red
}
exit $code
