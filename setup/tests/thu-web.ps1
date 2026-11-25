# Thử web trên máy Windows như setup\chay-web.bat: chạy php artisan serve ở 127.0.0.1:8001 rồi mở vài trang.
# CI chạy file này sau setup\cap-nhat.bat. Ghi kết quả (UTF-8) vào -Log.
param([string]$Log = (Join-Path ([IO.Path]::GetTempPath()) 'lernbuch-thu-web.log'))

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\lernbuch-chung.ps1')))

if (-not [IO.Path]::IsPathRooted($Log)) { $Log = Join-Path (Get-Location).Path $Log }
$utf8 = New-Object System.Text.UTF8Encoding $false
New-Item -ItemType Directory -Force -Path (Split-Path $Log) | Out-Null
[IO.File]::WriteAllText($Log, '', $utf8)
function Out-Log([string]$line) { Write-Host $line; [IO.File]::AppendAllText($Log, $line + "`n", $utf8) }

$base = "http://$($WebHost):$WebPort"
$total = 0
$passed = 0
function Test-Page([string]$name, [string]$path, [int]$status, [string]$contains) {
    $script:total++
    $code = 0; $body = ''
    try {
        $r = Invoke-WebRequest -Uri "$base$path" -UseBasicParsing -TimeoutSec 60
        $code = [int]$r.StatusCode; $body = [string]$r.Content
    } catch {
        if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode } else { $body = $_.Exception.Message }
    }
    $ok = ($code -eq $status) -and (-not $contains -or $body.Contains($contains))
    if ($ok) { $script:passed++; Out-Log "✔ $name (GET $path → $code)" } else { Out-Log "✘ $name (GET $path → $code) $($body.Substring(0, [Math]::Min(300, $body.Length)))" }
    return $body
}

$server = $null
$exitCode = 1
try {
    Use-LernbuchEnvironment
    Start-SharedMySql
    if (Test-Port $WebPort) { throw "Cổng $WebPort đang bận." }
    $outFile = Join-Path ([IO.Path]::GetTempPath()) 'lernbuch-serve-out.txt'
    $errFile = Join-Path ([IO.Path]::GetTempPath()) 'lernbuch-serve-err.txt'
    $server = Start-Process -FilePath (Get-Php) -ArgumentList @('artisan', 'serve', "--host=$WebHost", "--port=$WebPort", '--no-ansi') `
        -WorkingDirectory $Project -PassThru -NoNewWindow -RedirectStandardOutput $outFile -RedirectStandardError $errFile
    $up = $false
    for ($i = 0; $i -lt 30; $i++) { Start-Sleep -Seconds 1; if (Test-Port $WebPort) { $up = $true; break } }
    if (-not $up) { throw "php artisan serve không mở cổng $WebPort." }
    Out-Log "Máy chủ chạy ở $base"

    Test-Page 'trang chào có chữ Việt' '/' 200 'Học tiếng Đức cho người Việt' | Out-Null
    Test-Page 'trang chào có chữ Đức' '/' 200 'Straße' | Out-Null
    $app = Test-Page 'khung app học' '/app' 200 'id="app"'
    Test-Page 'đường dẫn con của app học' '/app/hom-nay' 200 'id="app"' | Out-Null
    $admin = Test-Page 'trang đăng nhập quản trị' '/admin/login' 200 'Lernbuch'
    Test-Page 'kiểm tra sống /up' '/up' 200 '' | Out-Null
    Test-Page 'manifest PWA' '/manifest.webmanifest' 200 '"start_url"' | Out-Null
    Test-Page 'không lộ .env' '/.env' 404 '' | Out-Null

    $manifest = Get-Content -LiteralPath (Join-Path $Project 'public\build\manifest.json') -Raw | ConvertFrom-Json
    $css = $manifest.'resources/css/app.css'.file
    Test-Page 'CSS đã build' "/build/$css" 200 '' | Out-Null
    $m = [regex]::Match($admin, '(?:href|src)="(?:https?://[^/"]+)?(/(?:css|js)/filament/[^"]+)"')
    if ($m.Success) { Test-Page 'file CSS/JS của Filament' ($m.Groups[1].Value -replace '&amp;', '&') 200 '' | Out-Null }
    else { $script:total++; Out-Log '✘ trang đăng nhập quản trị không có link CSS/JS của Filament' }

    $exitCode = if ($passed -eq $total) { 0 } else { 1 }
} catch {
    Out-Log "✘ LỖI: $($_.Exception.Message)"
} finally {
    if ($server) { Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue }
    Get-NetTCPConnection -LocalPort $WebPort -State Listen -ErrorAction SilentlyContinue |
        ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
    Out-Log "KẾT QUẢ: $passed/$total trang đạt"
}
exit $exitCode
