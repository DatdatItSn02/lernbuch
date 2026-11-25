# Lernbuch - cài môi trường phát triển vào F:\dev, dùng chung với LoWo (giống setup\cai-dat của LoWo):
#   PHP 8.5 (NTS x64), Composer, Node.js 26 LTS, MySQL 9.7 LTS (cổng 3307), Git bản gọn nếu máy chưa có Git.
# Chạy song song với XAMPP: không sửa PATH hệ thống, không đụng C:\xampp, không dùng cổng 3306.
# Chạy lại nhiều lần được: phần nào đã cài (kể cả do LoWo cài) thì bỏ qua. Cuối cùng tạo database lernbuch, lernbuch_test.

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

. (Join-Path $PSScriptRoot 'lernbuch-chung.ps1')

$Dl          = "$Root\tai-ve"
$PhpDir      = "$Root\php85"
$ComposerDir = "$Root\composer"
$NodeDir     = "$Root\node26"
$MySqlDir    = "$Root\mysql97"
$MySqlData   = "$Root\mysql97-data"
$GitDir      = "$Root\git"
$CacheDir    = "$Root\cache"
$Log         = Join-Path $PSScriptRoot 'ket-qua-cai-dat.txt'

# Giữ đúng danh sách của LoWo để php.ini dùng chung không bị đổi qua đổi lại.
$PhpExtensions = @('curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'sqlite3', 'sodium', 'zip')
$PhpSettings = [ordered]@{
    'memory_limit'        = '512M'
    'upload_max_filesize' = '64M'
    'post_max_size'       = '64M'
    'date.timezone'       = 'Asia/Ho_Chi_Minh'
}

# ---------- Hàm thuần (có test riêng) ----------

function Set-PhpIniText([string]$text, [string[]]$extensions, [System.Collections.IDictionary]$settings) {
    # Bỏ dấu ; trước extension_dir = "ext" (bản Windows)
    $text = [regex]::Replace($text, '(?m)^;\s*extension_dir\s*=\s*"ext"(?=\r?$)', 'extension_dir = "ext"')
    foreach ($e in $extensions) {
        $esc = [regex]::Escape($e)
        if ([regex]::IsMatch($text, "(?m)^extension\s*=\s*$esc(?=\r?$)")) { continue }
        $commented = New-Object System.Text.RegularExpressions.Regex("(?m)^;\s*extension\s*=\s*$esc(?=\r?$)")
        if ($commented.IsMatch($text)) {
            $text = $commented.Replace($text, "extension=$e", 1)
        } else {
            $text = $text.TrimEnd() + "`r`nextension=$e`r`n"
        }
    }
    foreach ($key in $settings.Keys) {
        $val = [string]$settings[$key]
        $esc = [regex]::Escape($key)
        $active = New-Object System.Text.RegularExpressions.Regex("(?m)^\s*$esc\s*=[^\r\n]*")
        $commented = New-Object System.Text.RegularExpressions.Regex("(?m)^;\s*$esc\s*=[^\r\n]*")
        $line = "$key = $val"
        if ($active.IsMatch($text)) {
            $text = $active.Replace($text, { param($m) $line }.GetNewClosure(), 1)
        } elseif ($commented.IsMatch($text)) {
            $text = $commented.Replace($text, { param($m) $line }.GetNewClosure(), 1)
        } else {
            $text = $text.TrimEnd() + "`r`n$line`r`n"
        }
    }
    return $text
}

function Get-MySqlIniText([string]$baseDir, [string]$dataDir, [int]$port) {
    $b = $baseDir -replace '\\', '/'
    $d = $dataDir -replace '\\', '/'
    return @"
[mysqld]
basedir=$b
datadir=$d
port=$port
bind-address=127.0.0.1
character-set-server=utf8mb4

[client]
port=$port
host=127.0.0.1
default-character-set=utf8mb4
"@ -replace "`r?`n", "`r`n"
}

function Select-NodeRelease($index, [int]$major) {
    foreach ($r in $index) {
        if ($r.version -like "v$major.*" -and (@($r.files) -contains 'win-x64-zip')) { return $r }
    }
    return $null
}

function Get-ChecksumFromList([string]$text, [string]$fileName) {
    foreach ($line in ($text -split "`r?`n")) {
        $parts = $line.Trim() -split '\s+'
        if ($parts.Count -ge 2 -and $parts[-1] -eq $fileName) { return $parts[0].ToLower() }
    }
    return $null
}

function Test-VcRuntimeVersion([int]$major, [int]$minor) {
    return ($major -gt 14) -or ($major -eq 14 -and $minor -ge 30)
}

# Chọn bản MinGit 64-bit thường (không lấy busybox, arm64, 32-bit).
function Select-MinGitAsset($assets) {
    foreach ($a in $assets) {
        if ($a.name -match '^MinGit-[0-9.]+-64-bit\.zip$') { return $a }
    }
    return $null
}

# ---------- Hàm cần Windows / mạng ----------

function Get-Text([string]$url) {
    $r = Invoke-WebRequest -Uri $url -UseBasicParsing
    if ($r.Content -is [byte[]]) { return [Text.Encoding]::UTF8.GetString($r.Content) }
    return [string]$r.Content
}

function Get-File([string[]]$urls, [string]$dest) {
    if (Test-Path -LiteralPath $dest) { Write-Ok "Đã có $(Split-Path $dest -Leaf)"; return }
    $part = "$dest.part"
    foreach ($u in $urls) {
        try {
            Write-Note "Tải $u"
            Invoke-WebRequest -Uri $u -OutFile $part -UseBasicParsing
            Move-Item -LiteralPath $part -Destination $dest -Force
            $mb = [math]::Round((Get-Item -LiteralPath $dest).Length / 1MB, 1)
            Write-Ok "Đã tải $(Split-Path $dest -Leaf) ($mb MB)"
            return
        } catch {
            Write-Note "Không tải được: $($_.Exception.Message)"
            if (Test-Path -LiteralPath $part) { Remove-Item -LiteralPath $part -Force }
        }
    }
    throw "Không tải được $(Split-Path $dest -Leaf) từ địa chỉ nào."
}

function Assert-Sha256([string]$file, [string]$expected) {
    $actual = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLower()
    if ($actual -ne $expected.ToLower()) {
        Remove-Item -LiteralPath $file -Force
        throw "Mã kiểm tra của $(Split-Path $file -Leaf) không khớp, đã xóa file. Hãy chạy lại."
    }
    Write-Ok 'Mã kiểm tra SHA256 khớp'
}

function Install-Zip([string]$zip, [string]$target) {
    $tmp = Join-Path $Dl ('giai-nen-' + [IO.Path]::GetFileNameWithoutExtension($zip))
    if (Test-Path -LiteralPath $tmp) { Remove-Item -LiteralPath $tmp -Recurse -Force }
    New-Item -ItemType Directory -Force -Path $tmp | Out-Null
    Write-Note "Giải nén $(Split-Path $zip -Leaf)"
    $tar = Join-Path $env:SystemRoot 'System32\tar.exe'
    if (Test-Path -LiteralPath $tar) {
        & $tar -xf $zip -C $tmp
        if ($LASTEXITCODE -ne 0) { throw "Giải nén thất bại: $zip" }
    } else {
        Expand-Archive -LiteralPath $zip -DestinationPath $tmp -Force
    }
    $items = @(Get-ChildItem -LiteralPath $tmp -Force)
    if ($items.Count -eq 1 -and $items[0].PSIsContainer) { $src = $items[0].FullName } else { $src = $tmp }
    Move-Item -LiteralPath $src -Destination $target
    if (Test-Path -LiteralPath $tmp) { Remove-Item -LiteralPath $tmp -Recurse -Force }
    Write-Ok "Đã giải nén vào $target"
}

function Test-VcRedist {
    $dll = Join-Path $env:SystemRoot 'System32\vcruntime140.dll'
    if (-not (Test-Path -LiteralPath $dll)) { return $false }
    $v = (Get-Item -LiteralPath $dll).VersionInfo
    return Test-VcRuntimeVersion $v.FileMajorPart $v.FileMinorPart
}

function Get-OurMySqlProcess {
    $exe = Join-Path $MySqlDir 'bin\mysqld.exe'
    return @(Get-Process -Name mysqld -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $exe })
}

# ---------- Các bước cài ----------

function Install-VcRedist {
    Write-Step 'Thư viện Visual C++ 2015-2022 (PHP 8.5 và MySQL 9.7 cần)'
    if (Test-VcRedist) { Write-Ok 'Đã có bản phù hợp'; return }
    $exe = Join-Path $Dl 'vc_redist.x64.exe'
    Get-File @('https://aka.ms/vs/17/release/vc_redist.x64.exe') $exe
    Write-Note 'Windows sẽ hỏi quyền quản trị, hãy bấm Yes.'
    $p = Start-Process -FilePath $exe -ArgumentList '/install', '/passive', '/norestart' -Verb RunAs -Wait -PassThru
    if (@(0, 1638, 3010) -notcontains $p.ExitCode) { throw "Cài Visual C++ thất bại (mã $($p.ExitCode))." }
    if (-not (Test-VcRedist)) { throw 'Đã cài Visual C++ nhưng vẫn chưa thấy bản 14.30 trở lên. Hãy khởi động lại máy rồi chạy lại.' }
    Write-Ok 'Đã cài'
}

function Install-Php {
    Write-Step 'PHP 8.5'
    $php = Join-Path $PhpDir 'php.exe'
    if (-not (Test-Path -LiteralPath $php)) {
        $name = 'php-8.5-nts-Win32-vs17-x64-latest.zip'
        $zip = Join-Path $Dl $name
        Get-File @(
            "https://downloads.php.net/~windows/releases/latest/$name",
            "https://windows.php.net/downloads/releases/latest/$name"
        ) $zip
        Install-Zip $zip $PhpDir
    } else { Write-Ok "Đã có $php" }

    $ini = Join-Path $PhpDir 'php.ini'
    if (-not (Test-Path -LiteralPath $ini)) {
        Copy-Item -LiteralPath (Join-Path $PhpDir 'php.ini-development') -Destination $ini
    }
    $extDir = Join-Path $PhpDir 'ext'
    $exts = @($PhpExtensions | Where-Object { Test-Path -LiteralPath (Join-Path $extDir "php_$_.dll") })
    $text = [IO.File]::ReadAllText($ini)
    $new = Set-PhpIniText $text $exts $PhpSettings
    if ($new -ne $text) { [IO.File]::WriteAllText($ini, $new, (New-Object Text.UTF8Encoding($false))); Write-Ok 'Đã cấu hình php.ini' }

    $ver = & $php -r 'echo PHP_VERSION;'
    if ($LASTEXITCODE -ne 0 -or -not $ver) { throw 'php.exe không chạy được.' }
    $mods = (& $php -m) | ForEach-Object { $_.Trim().ToLower() }
    $missing = @($PhpExtensions | Where-Object { $mods -notcontains $_ })
    if ($missing.Count -gt 0) { throw "PHP thiếu extension: $($missing -join ', ')" }
    Write-Ok "PHP $ver, đủ extension"
}

function Install-Composer {
    Write-Step 'Composer'
    New-Item -ItemType Directory -Force -Path $ComposerDir | Out-Null
    $phar = Join-Path $ComposerDir 'composer.phar'
    if (-not (Test-Path -LiteralPath $phar)) {
        Get-File @('https://getcomposer.org/download/latest-stable/composer.phar') $phar
        $sum = Get-Text 'https://getcomposer.org/download/latest-stable/composer.phar.sha256sum'
        Assert-Sha256 $phar (($sum.Trim() -split '\s+')[0])
    } else { Write-Ok "Đã có $phar" }
    $bat = Join-Path $ComposerDir 'composer.bat'
    $batText = "@echo off`r`n`"%~dp0..\php85\php.exe`" `"%~dp0composer.phar`" %*`r`n"
    [IO.File]::WriteAllText($bat, $batText, (New-Object Text.ASCIIEncoding))
    $env:COMPOSER_HOME = Join-Path $ComposerDir 'home'
    $env:COMPOSER_CACHE_DIR = Join-Path $CacheDir 'composer'
    $ver = & (Join-Path $PhpDir 'php.exe') $phar --version --no-ansi
    if ($LASTEXITCODE -ne 0) { throw 'Composer không chạy được.' }
    Write-Ok $ver
}

function Install-Node {
    Write-Step 'Node.js 26 LTS'
    $node = Join-Path $NodeDir 'node.exe'
    if (-not (Test-Path -LiteralPath $node)) {
        $index = Invoke-RestMethod -Uri 'https://nodejs.org/dist/index.json' -UseBasicParsing
        $rel = Select-NodeRelease $index 26
        if (-not $rel) { throw 'Không tìm thấy bản Node.js 26 cho Windows x64.' }
        $v = $rel.version
        $name = "node-$v-win-x64.zip"
        $zip = Join-Path $Dl $name
        Get-File @("https://nodejs.org/dist/$v/$name") $zip
        $expected = Get-ChecksumFromList (Get-Text "https://nodejs.org/dist/$v/SHASUMS256.txt") $name
        if (-not $expected) { throw "Không thấy mã kiểm tra cho $name." }
        Assert-Sha256 $zip $expected
        Install-Zip $zip $NodeDir
    } else { Write-Ok "Đã có $node" }
    $ver = & $node -v
    Write-Ok "Node.js $ver"
}

function Find-MySqlZip {
    $local = Get-ChildItem -LiteralPath $Dl -Filter 'mysql-9.7.*-winx64.zip' -ErrorAction SilentlyContinue |
        Sort-Object { [version](($_.Name -replace '^mysql-', '') -replace '-winx64\.zip$', '') } -Descending |
        Select-Object -First 1
    if ($local) { return $local.FullName }
    foreach ($base in @('https://cdn.mysql.com/Downloads/MySQL-9.7', 'https://cdn.mysql.com/archives/mysql-9.7')) {
        for ($p = 15; $p -ge 0; $p--) {
            $name = "mysql-9.7.$p-winx64.zip"
            try {
                $r = Invoke-WebRequest -Uri "$base/$name" -Method Head -UseBasicParsing
                if ($r.StatusCode -eq 200) {
                    $zip = Join-Path $Dl $name
                    Get-File @("$base/$name") $zip
                    return $zip
                }
            } catch { }
        }
    }
    return $null
}

function Install-MySql {
    Write-Step "MySQL 9.7 LTS (cổng $Port, dùng chung với LoWo)"
    $mysqld = Join-Path $MySqlDir 'bin\mysqld.exe'
    $myIni = Join-Path $MySqlDir 'my.ini'
    if (-not (Test-Path -LiteralPath $mysqld)) {
        $zip = Find-MySqlZip
        if (-not $zip) {
            throw "Không tự tải được MySQL. Hãy mở https://dev.mysql.com/downloads/mysql/ , chọn bản 9.7 LTS, Windows (x86, 64-bit), ZIP Archive (không chọn bản Debug), lưu file mysql-9.7.x-winx64.zip vào $Dl rồi chạy lại."
        }
        Install-Zip $zip $MySqlDir
    } else { Write-Ok "Đã có $mysqld" }

    [IO.File]::WriteAllText($myIni, (Get-MySqlIniText $MySqlDir $MySqlData $Port), (New-Object Text.ASCIIEncoding))

    if (-not (Test-Path -LiteralPath $MySqlData)) {
        if (Test-Port $Port) { throw "Cổng $Port đang bị chương trình khác dùng. Hãy tắt chương trình đó rồi chạy lại." }
        Write-Note 'Khởi tạo dữ liệu MySQL (tài khoản root, chưa đặt mật khẩu, chỉ nghe trên máy này)'
        $errLog = Join-Path $Dl 'mysql-khoi-tao.log'
        $p = Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=`"$myIni`"", '--initialize-insecure', '--console' -Wait -PassThru -NoNewWindow -RedirectStandardError $errLog
        if ($p.ExitCode -ne 0) { throw "Khởi tạo MySQL thất bại, xem $errLog" }
        Write-Ok 'Đã khởi tạo'
    }

    if ((Test-Port $Port) -and (Get-OurMySqlProcess).Count -eq 0) {
        throw "Cổng $Port đang bị chương trình khác dùng."
    }
    Start-SharedMySql
    New-LernbuchDatabases
    $ver = & (Get-MySqlClient) --version
    Write-Ok $ver
}

function Install-Git {
    Write-Step 'Git'
    try { $found = Find-Git; Write-Ok "Đã có: $found"; return } catch { }
    $rel = Invoke-RestMethod -Uri 'https://api.github.com/repos/git-for-windows/git/releases/latest' -UseBasicParsing
    $asset = Select-MinGitAsset $rel.assets
    if (-not $asset) { throw 'Không thấy bản MinGit 64-bit trong bản phát hành mới nhất.' }
    $zip = Join-Path $Dl $asset.name
    Get-File @($asset.browser_download_url) $zip
    if ($asset.digest -and $asset.digest -like 'sha256:*') { Assert-Sha256 $zip $asset.digest.Substring(7) }
    New-Item -ItemType Directory -Force -Path $GitDir | Out-Null
    $tar = Join-Path $env:SystemRoot 'System32\tar.exe'
    & $tar -xf $zip -C $GitDir
    if ($LASTEXITCODE -ne 0) { throw 'Giải nén MinGit thất bại.' }
    Write-Ok "Đã cài Git ($($asset.name)) vào $GitDir"
}

# ---------- Chạy ----------

if ($MyInvocation.InvocationName -ne '.') {
    Start-Transcript -Path $Log -Force | Out-Null
    $code = 0
    try {
        $drive = Split-Path -Qualifier $Root
        if (-not (Test-Path -LiteralPath "$drive\")) { throw "Không thấy ổ $drive." }
        foreach ($d in @($Root, $Dl, $CacheDir)) { New-Item -ItemType Directory -Force -Path $d | Out-Null }
        Install-VcRedist
        Install-Php
        Install-Composer
        Install-Node
        Install-MySql
        Install-Git
        Write-Step 'XONG'
        Write-Note 'Tiếp theo: bấm đúp setup\cap-nhat.bat để lấy code, cài thư viện, tạo bảng và chạy test.'
        Write-Note 'XAMPP vẫn dùng PHP 7.4 và MariaDB cổng 3306 như cũ.'
    } catch {
        $code = 1
        Write-Host ''
        Write-Host "LỖI: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host $_.InvocationInfo.PositionMessage
    } finally {
        Stop-Transcript | Out-Null
    }
    exit $code
}
