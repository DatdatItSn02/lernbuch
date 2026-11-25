# Lernbuch - nhận code mới rồi chuẩn bị để chạy, giống setup\cap-nhat của LoWo:
#   1. Lấy code mới nhất của nhánh main trên GitHub (đã qua CI) bằng Git.
#   2. Sửa .env cho đúng máy này (MySQL cổng 3307 dùng chung với LoWo, web ở 127.0.0.1:8001).
#   3. composer install, npm ci, tạo database, migrate, seed, build giao diện.
#   4. Chạy toàn bộ test (setup\chay-test.ps1).
# Kết quả ghi vào setup\ket-qua-cap-nhat.txt và setup\ket-qua-test.txt.
#   -SkipCode : không lấy code (máy CI đã checkout đúng bản cần thử)
#   -SkipTests: không chạy test sau khi cập nhật

param([switch]$SkipCode, [switch]$SkipTests)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

. (Join-Path $PSScriptRoot 'lernbuch-chung.ps1')

$Log      = Join-Path $PSScriptRoot 'ket-qua-cap-nhat.txt'
$Remote   = 'origin'
$Branch   = 'main'
$Incoming = "refs/remotes/$Remote/$Branch"

# Giá trị .env cần cho máy này; chỉ ghi đè những khóa này, khóa khác giữ nguyên.
$EnvValues = [ordered]@{
    'APP_URL'       = "http://$($WebHost):$WebPort"
    'DB_CONNECTION' = 'mysql'
    'DB_HOST'       = '127.0.0.1'
    'DB_PORT'       = "$Port"
    'DB_DATABASE'   = 'lernbuch'
    'DB_USERNAME'   = 'root'
    'DB_PASSWORD'   = ''
}

# ---------- Hàm thuần (có test riêng) ----------

function Set-EnvText([string]$text, [System.Collections.IDictionary]$values) {
    $lines = New-Object System.Collections.Generic.List[string]
    if ($text -ne '') {
        foreach ($l in ($text -split "`r?`n")) { $lines.Add($l) }
        if ($lines.Count -gt 0 -and $lines[$lines.Count - 1] -eq '') { $lines.RemoveAt($lines.Count - 1) }
    }
    foreach ($key in $values.Keys) {
        $line = "$key=$($values[$key])"
        $found = $false
        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match ('^\s*#?\s*' + [regex]::Escape($key) + '\s*=')) {
                if (-not $found) { $lines[$i] = $line; $found = $true }
            }
        }
        if (-not $found) { $lines.Add($line) }
    }
    return (($lines -join "`n") + "`n")
}

# APP_KEY chỉ đặt khi đang trống hoặc chưa có, không đổi khóa đang dùng (đổi khóa làm mất phiên đăng nhập và dữ liệu đã mã hoá).
function Set-AppKeyText([string]$text, [string]$key) {
    if ([regex]::IsMatch($text, '(?m)^[ \t]*APP_KEY[ \t]*=[ \t]*\S')) { return $text }
    return (Set-EnvText $text ([ordered]@{ 'APP_KEY' = $key }))
}

function New-AppKey {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return 'base64:' + [Convert]::ToBase64String($bytes)
}

# ---------- Lấy code ----------

function Get-GitLines([string]$git, [string[]]$argList) {
    $r = Get-NativeOutput $git (@('-c', 'core.quotepath=false') + $argList) $Project
    if ($r.Code -ne 0) { throw "git $($argList -join ' ') thất bại (mã $($r.Code))." }
    return ,$r.Lines
}

function Get-IncomingBlob([string]$git, [string]$file) {
    $r = Get-NativeOutput $git @('rev-parse', '--verify', '--quiet', "$($Incoming):$file") $Project
    if ($r.Code -ne 0 -or $r.Lines.Count -eq 0) { return '' }
    return $r.Lines[0].Trim()
}

# File trên máy khác với lần cập nhật trước:
# - giống hệt bản trong code mới (Claude gửi trước file vào máy, thường là chính các script này)
#   thì bỏ bản trên máy để Git ghi bản mới;
# - khác thì dừng, để không mất phần đã sửa trên máy.
function Resolve-LocalChanges([string]$git) {
    $blocked = New-Object System.Collections.Generic.List[string]

    foreach ($file in (Get-GitLines $git @('diff', '--name-only', 'HEAD'))) {
        if (Test-SameAsBlob (Join-Path $Project $file) (Get-IncomingBlob $git $file)) {
            Write-Note "Đã có sẵn bản mới của $file"
            Invoke-Checked $git @('checkout', '--quiet', 'HEAD', '--', $file) "trả $file về bản cũ"
        } else {
            $blocked.Add($file)
        }
    }

    foreach ($file in (Get-GitLines $git @('diff', '--name-only', '--diff-filter=A', 'HEAD', $Incoming))) {
        $full = Join-Path $Project $file
        if (-not (Test-Path -LiteralPath $full)) { continue }
        if (Test-SameAsBlob $full (Get-IncomingBlob $git $file)) {
            Write-Note "Đã có sẵn bản mới của $file"
            Remove-Item -LiteralPath $full -Force
        } else {
            $blocked.Add($file)
        }
    }

    if ($blocked.Count -gt 0) {
        throw "Có file trên máy khác với code trên GitHub:`n$($blocked -join "`n")`nChưa cập nhật gì. Hãy báo Claude trước để không mất phần đã sửa."
    }
}

# Git có thể ghi .bat/.ps1 theo kiểu xuống dòng cũ nếu .gitattributes đổi cùng lần cập nhật; cmd.exe cần CRLF.
function Repair-LineEndings([string]$git) {
    $latin1 = [Text.Encoding]::GetEncoding(28591)
    foreach ($file in (Get-GitLines $git @('ls-files', '--', '*.bat', '*.cmd', '*.ps1'))) {
        $full = Join-Path $Project $file
        if (-not (Test-Path -LiteralPath $full)) { continue }
        $text = $latin1.GetString([IO.File]::ReadAllBytes($full))
        if ([regex]::IsMatch($text, '(?<!\r)\n')) {
            Remove-Item -LiteralPath $full -Force
            Invoke-Checked $git @('checkout', '--quiet', '--', $file) "ghi lại $file"
        }
    }
}

function Update-Code {
    Write-Step "Lấy code mới từ GitHub (nhánh $Branch)"
    if (-not (Test-Path -LiteralPath (Join-Path $Project '.git'))) {
        throw "$Project chưa phải kho Git. Hãy lấy code bằng: git clone https://github.com/DatdatItSn02/lernbuch.git `"$Project`""
    }
    $git = Find-Git
    $current = Get-NativeOutput $git @('symbolic-ref', '--quiet', '--short', 'HEAD') $Project
    $name = if ($current.Lines.Count -gt 0) { $current.Lines[0].Trim() } else { '(không ở nhánh nào)' }
    if ($name -ne $Branch) { throw "Code đang ở nhánh $name, cần nhánh $Branch. Hãy báo Claude." }

    Invoke-Checked $git @('fetch', '--quiet', $Remote, "+refs/heads/$($Branch):$Incoming") 'git fetch (lấy code từ GitHub)'

    $before = (Get-GitLines $git @('rev-parse', 'HEAD'))[0].Trim()
    $after = (Get-GitLines $git @('rev-parse', $Incoming))[0].Trim()
    if ($before -eq $after) {
        Resolve-LocalChanges $git
        Write-Ok "Code đã là bản mới nhất ($($after.Substring(0, 7)))"
        return
    }
    $isAncestor = Invoke-Native $git @('merge-base', '--is-ancestor', 'HEAD', $Incoming)
    if ($isAncestor -ne 0) {
        throw "Máy có commit riêng chưa có trên GitHub nên không cập nhật thẳng được. Hãy báo Claude."
    }

    Resolve-LocalChanges $git
    Invoke-Checked $git @('merge', '--ff-only', '--quiet', $Incoming) 'git merge'
    Repair-LineEndings $git
    Write-Ok "Đã cập nhật lên $($after.Substring(0, 7)):"
    foreach ($l in (Get-GitLines $git @('log', '--oneline', '--no-decorate', "$before..$after"))) { Write-Note "  $l" }
}

# ---------- Các bước còn lại ----------

function Update-EnvFile {
    Write-Step 'Cấu hình .env'
    $envFile = Join-Path $Project '.env'
    if (-not (Test-Path -LiteralPath $envFile)) {
        Copy-Item -LiteralPath (Join-Path $Project '.env.example') -Destination $envFile
        Write-Note 'Đã tạo .env từ .env.example'
    }
    $text = [IO.File]::ReadAllText($envFile)
    $new = Set-AppKeyText (Set-EnvText $text $EnvValues) (New-AppKey)
    if ($new -ne $text) {
        [IO.File]::WriteAllText($envFile, $new, (New-Object Text.UTF8Encoding($false)))
        Write-Ok "Đã cập nhật .env (MySQL cổng $Port, web $($EnvValues['APP_URL']))"
    } else {
        Write-Ok '.env đã đúng'
    }
}

function Install-Dependencies {
    Write-Step 'Thư viện PHP (composer install)'
    Invoke-Checked (Get-Composer) @('install', '--no-interaction', '--no-ansi', '--no-progress') 'composer install'

    # npm ci cài đúng theo package-lock.json và không sửa file này; chỉ chạy khi file đổi hoặc chưa có node_modules.
    Write-Step 'Thư viện giao diện (npm ci)'
    $lock = Join-Path $Project 'package-lock.json'
    $stamp = Join-Path $PSScriptRoot 'tmp\npm-lock.sha256'
    $hash = (Get-FileHash -LiteralPath $lock -Algorithm SHA256).Hash
    $old = if (Test-Path -LiteralPath $stamp) { (Get-Content -LiteralPath $stamp -Raw).Trim() } else { '' }
    if ($hash -eq $old -and (Test-Path -LiteralPath (Join-Path $Project 'node_modules'))) {
        Write-Ok 'package-lock.json không đổi, bỏ qua'
    } else {
        Invoke-Checked (Get-Npm) @('ci', '--no-audit', '--no-fund') 'npm ci'
        New-Item -ItemType Directory -Force -Path (Split-Path $stamp) | Out-Null
        Set-Content -LiteralPath $stamp -Value $hash
    }
}

function Update-Database {
    Write-Step 'Database'
    Start-SharedMySql
    New-LernbuchDatabases
    $php = Get-Php
    Invoke-Checked $php @('artisan', 'config:clear', '--no-ansi') 'xóa cache cấu hình'
    Invoke-Checked $php @('artisan', 'migrate', '--force', '--no-interaction', '--no-ansi') 'migrate'
    # seed.sql chạy lại không nhân đôi, không ghi đè dữ liệu đã sửa ở trang quản trị.
    Invoke-Checked $php @('artisan', 'db:seed', '--force', '--no-interaction', '--no-ansi') 'nạp dữ liệu khởi tạo (seed)'
    if (-not (Test-Path -LiteralPath (Join-Path $Project 'public\storage'))) {
        Invoke-Checked $php @('artisan', 'storage:link', '--no-ansi') 'tạo liên kết public\storage'
    }
    Invoke-Checked $php @('artisan', 'optimize:clear', '--no-ansi') 'xóa cache'
}

function Build-Frontend {
    Write-Step 'Build giao diện (npm run build)'
    Invoke-Checked (Get-Npm) @('run', 'build') 'npm run build'
}

# Chưa có tài khoản quản trị thì nhắc chạy setup\tao-admin.bat.
function Show-AdminHint {
    $sql = 'SELECT COUNT(*) FROM lernbuch.users WHERE is_staff = 1 AND deleted_at IS NULL'
    $r = Get-NativeOutput (Get-MySqlClient) @('-u', 'root', '-h', '127.0.0.1', '-P', "$Port", '-N', '-B', '-e', $sql)
    if ($r.Code -eq 0 -and $r.Lines.Count -gt 0 -and $r.Lines[0].Trim() -eq '0') {
        Write-Host ''
        Write-Host 'Chưa có tài khoản quản trị. Chạy setup\tao-admin.bat để tạo tài khoản đăng nhập /admin.' -ForegroundColor Yellow
    }
}

# ---------- Chạy ----------

if ($MyInvocation.InvocationName -ne '.') {
    Start-Transcript -Path $Log -Force | Out-Null
    $exitCode = 0
    try {
        Use-LernbuchEnvironment
        if ($SkipCode) { Write-Step 'Bỏ qua bước lấy code (-SkipCode)' } else { Update-Code }
        Update-EnvFile
        Install-Dependencies
        Update-Database
        Build-Frontend
        Write-Step 'CẬP NHẬT XONG'
        Write-Note "Mở web: bấm đúp setup\chay-web.bat rồi vào http://$($WebHost):$WebPort"
        Show-AdminHint
    } catch {
        $exitCode = 1
        Write-Host ''
        Write-Host "LỖI: $($_.Exception.Message)" -ForegroundColor Red
    } finally {
        Stop-Transcript | Out-Null
    }

    if ($exitCode -eq 0 -and -not $SkipTests) {
        & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'chay-test.ps1')
        $exitCode = $LASTEXITCODE
    }
    exit $exitCode
}
