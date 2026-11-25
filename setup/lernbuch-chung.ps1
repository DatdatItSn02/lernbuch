# Hàm dùng chung cho các script trong setup\. Nạp bằng: . (Join-Path $PSScriptRoot 'lernbuch-chung.ps1')
# Công cụ nằm ở F:\dev, dùng chung với LoWo: PHP 8.5, Composer, Node.js 26, MySQL 9.7 cổng 3307.
# Máy khác (ví dụ máy CI) đổi thư mục bằng biến môi trường LERNBUCH_DEV, cổng MySQL bằng LERNBUCH_MYSQL_PORT.

$Root      = if ($env:LERNBUCH_DEV) { $env:LERNBUCH_DEV } else { 'F:\dev' }
$Port      = if ($env:LERNBUCH_MYSQL_PORT) { [int]$env:LERNBUCH_MYSQL_PORT } else { 3307 }
$WebHost   = '127.0.0.1'   # không dùng localhost để cookie không lẫn với LoWo (localhost:8000)
$WebPort   = 8001
$Databases = @('lernbuch', 'lernbuch_test')
$Project   = Split-Path $PSScriptRoot -Parent

function Write-Step([string]$msg) { Write-Host ''; Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok([string]$msg)   { Write-Host "    OK: $msg" -ForegroundColor Green }
function Write-Note([string]$msg) { Write-Host "    $msg" }
function Write-Warn([string]$msg) { Write-Host "    CHÚ Ý: $msg" -ForegroundColor Yellow }

function Get-Php         { return "$Root\php85\php.exe" }
function Get-Composer    { return "$Root\composer\composer.bat" }
function Get-Npm         { return "$Root\node26\npm.cmd" }
function Get-MySqlClient { return "$Root\mysql97\bin\mysql.exe" }
function Get-MySqlAdmin  { return "$Root\mysql97\bin\mysqladmin.exe" }

# ---------- Chạy lệnh ngoài ----------

# Chạy lệnh, in toàn bộ đầu ra (cả lỗi) ra cửa sổ và file kết quả, trả về mã thoát.
# Windows PowerShell 5.1 coi dòng ghi ra stderr là lỗi nên phải tạm để 'Continue'.
function Invoke-Native([string]$exe, [string[]]$argList, [string]$workDir = $Project) {
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    Push-Location -LiteralPath $workDir
    try {
        & $exe @argList 2>&1 | ForEach-Object {
            if ($_ -is [System.Management.Automation.ErrorRecord]) { $_.Exception.Message } else { "$_" }
        } | Out-Host
        return $LASTEXITCODE
    } finally {
        Pop-Location
        $ErrorActionPreference = $old
    }
}

function Invoke-Checked([string]$exe, [string[]]$argList, [string]$what, [string]$workDir = $Project) {
    $code = Invoke-Native $exe $argList $workDir
    if ($code -ne 0) { throw "$what thất bại (mã $code)." }
}

# Chạy lệnh và lấy các dòng in ra (bỏ stderr). Trả về @{ Code; Lines }.
function Get-NativeOutput([string]$exe, [string[]]$argList, [string]$workDir = $Project) {
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    Push-Location -LiteralPath $workDir
    try {
        $out = & $exe @argList 2>$null
        $code = $LASTEXITCODE
        $lines = @($out | ForEach-Object { "$_" } | Where-Object { $_ -ne '' })
        return [pscustomobject]@{ Code = $code; Lines = $lines }
    } finally {
        Pop-Location
        $ErrorActionPreference = $old
    }
}

# ---------- Môi trường ----------

function Find-Git {
    $cmd = Get-Command git.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($cmd) { return $cmd.Source }
    $candidates = @(
        "$Root\git\cmd\git.exe",
        "$env:ProgramFiles\Git\cmd\git.exe",
        "${env:ProgramFiles(x86)}\Git\cmd\git.exe",
        "$env:LOCALAPPDATA\Programs\Git\cmd\git.exe"
    )
    foreach ($p in $candidates) { if ($p -and (Test-Path -LiteralPath $p)) { return $p } }
    $desktop = Get-ChildItem -Path "$env:LOCALAPPDATA\GitHubDesktop\app-*\resources\app\git\cmd\git.exe" -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1
    if ($desktop) { return $desktop.FullName }
    throw 'Không thấy Git. Hãy chạy setup\cai-dat.bat (tự cài Git bản gọn) hoặc cài Git for Windows.'
}

# PATH và thư mục cache chỉ cho cửa sổ hiện tại, giống setup\_env.bat; không sửa PATH hệ thống.
# Đọc đầu ra của git, php... theo UTF-8 để chữ tiếng Việt không bị lỗi.
function Use-LernbuchEnvironment {
    try { [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding $false } catch { }
    foreach ($f in @((Get-Php), (Get-Composer), (Get-Npm), (Get-MySqlClient))) {
        if (-not (Test-Path -LiteralPath $f)) { throw "Thiếu $f. Hãy chạy setup\cai-dat.bat trước." }
    }
    $parts = @("$Root\php85", "$Root\composer", "$Root\composer\home\vendor\bin", "$Root\node26", "$Root\mysql97\bin", "$Root\git\cmd")
    $env:PATH = (($parts + @($env:PATH)) -join ';')
    $env:COMPOSER_HOME = "$Root\composer\home"
    $env:COMPOSER_CACHE_DIR = "$Root\cache\composer"
    $env:npm_config_cache = "$Root\cache\npm"
}

function Test-Port([int]$p) {
    $c = New-Object Net.Sockets.TcpClient
    try { $c.Connect('127.0.0.1', $p); return $true } catch { return $false } finally { $c.Close() }
}

# ---------- MySQL dùng chung với LoWo ----------

function Start-SharedMySql {
    if (Test-Port $Port) { Write-Ok "MySQL đang chạy ở cổng $Port"; return }
    $mysqld = "$Root\mysql97\bin\mysqld.exe"
    if (-not (Test-Path -LiteralPath $mysqld)) { throw "Thiếu $mysqld. Hãy chạy setup\cai-dat.bat trước." }
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=`"$Root\mysql97\my.ini`"" -WindowStyle Hidden | Out-Null
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Seconds 1
        if (Test-Port $Port) { Write-Ok "Đã bật MySQL ở cổng $Port"; return }
    }
    throw "MySQL không bật được. Xem file .err trong $Root\mysql97-data"
}

function Stop-SharedMySql {
    if (-not (Test-Port $Port)) { Write-Ok "MySQL ở cổng $Port đang không chạy"; return }
    $code = Invoke-Native (Get-MySqlAdmin) @('-u', 'root', '-h', '127.0.0.1', '-P', "$Port", 'shutdown')
    if ($code -ne 0) { throw "Không tắt được MySQL (mã $code)." }
    for ($i = 0; $i -lt 30; $i++) {
        if (-not (Test-Port $Port)) { Write-Ok "Đã tắt MySQL ở cổng $Port"; return }
        Start-Sleep -Seconds 1
    }
    throw "MySQL vẫn chạy sau 30 giây."
}

function Get-CreateDatabaseSql([string[]]$names) {
    return (($names | ForEach-Object { "CREATE DATABASE IF NOT EXISTS $_ CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" }) -join ' ')
}

function New-LernbuchDatabases {
    Invoke-Checked (Get-MySqlClient) @('-u', 'root', '-h', '127.0.0.1', '-P', "$Port", '-e', (Get-CreateDatabaseSql $Databases)) 'tạo database'
    Write-Ok "Đã có database $($Databases -join ' và ')"
}

# ---------- Hàm thuần (có test riêng) ----------

# Mã Git (SHA-1) của một nội dung, giống `git hash-object`.
function Get-GitBlobHash([byte[]]$Bytes) {
    if ($null -eq $Bytes) { $Bytes = New-Object byte[] 0 }
    $header = [Text.Encoding]::ASCII.GetBytes("blob $($Bytes.Length)" + [char]0)
    $all = New-Object byte[] ($header.Length + $Bytes.Length)
    [Array]::Copy($header, 0, $all, 0, $header.Length)
    [Array]::Copy($Bytes, 0, $all, $header.Length, $Bytes.Length)
    $sha = [Security.Cryptography.SHA1]::Create()
    try { $hash = $sha.ComputeHash($all) } finally { $sha.Dispose() }
    return (($hash | ForEach-Object { $_.ToString('x2') }) -join '')
}

# File trên máy có đúng nội dung bản Git $BlobId không (coi CRLF và LF là như nhau).
function Test-SameAsBlob([string]$Path, [string]$BlobId) {
    $exists = Test-Path -LiteralPath $Path -PathType Leaf
    if (-not $BlobId) { return (-not $exists) }
    if (-not $exists) { return $false }
    $bytes = [IO.File]::ReadAllBytes($Path)
    if ((Get-GitBlobHash $bytes) -eq $BlobId) { return $true }
    $latin1 = [Text.Encoding]::GetEncoding(28591)
    $noCr = $latin1.GetBytes($latin1.GetString($bytes).Replace("`r", ''))
    return ((Get-GitBlobHash $noCr) -eq $BlobId)
}

# Dòng kết quả của một bước kiểm tra, cùng kiểu với CI.
function Format-StepResult([string]$name, [int]$code, [double]$seconds) {
    $sec = [math]::Round($seconds, 1).ToString([Globalization.CultureInfo]::InvariantCulture)
    if ($code -eq 0) { return "✔ $name ($sec giây)" }
    return "✘ $name (mã $code, $sec giây)"
}

function Format-Summary([int]$passed, [int]$total) {
    return "KẾT QUẢ: $passed/$total bước đạt"
}
