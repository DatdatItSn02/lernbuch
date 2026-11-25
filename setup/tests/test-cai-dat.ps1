# Kiểm thử các hàm của cai-dat.ps1 (không cần mạng). Chạy: powershell -File test-cai-dat.ps1
$ErrorActionPreference = 'Stop'
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '_kiem.ps1')))
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\cai-dat.ps1')))

# --- php.ini: đoạn trích đúng kiểu php.ini-development của bản Windows ---
$raw = @'
[PHP]
memory_limit = 128M
post_max_size = 8M
upload_max_filesize = 2M
; On windows:
;extension_dir = "ext"
; Directory in which the loadable extensions (modules) reside.
; extension_dir = "./"
;extension=bz2
;extension=curl
;extension=ffi
;extension=fileinfo
;extension=gd
;extension=gettext
;extension=intl
;extension=mbstring
;extension=openssl
;extension=pdo_mysql
;extension=pdo_sqlite
;extension=sodium
;extension=sqlite3
;extension=zip
[Date]
;date.timezone =
'@ -replace "`r?`n", "`r`n"
$exts = @($PhpExtensions) + @('khongcoextnay')
$out = Set-PhpIniText $raw $exts $PhpSettings
foreach ($e in $exts) {
    $n = ([regex]::Matches($out, "(?m)^extension=$([regex]::Escape($e))(?=\r?$)")).Count
    Check "extension=$e xuất hiện đúng 1 lần" ($n -eq 1)
}
Check 'extension_dir = "ext" được bật' ([regex]::IsMatch($out, '(?m)^extension_dir = "ext"\r?$'))
Check 'extension_dir mặc định "./" vẫn là comment' (-not [regex]::IsMatch($out, '(?m)^extension_dir = "\./"'))
foreach ($k in $PhpSettings.Keys) {
    $n = ([regex]::Matches($out, "(?m)^$([regex]::Escape($k)) = $([regex]::Escape($PhpSettings[$k]))\r?$")).Count
    Check "$k = $($PhpSettings[$k]) đúng 1 dòng" ($n -eq 1)
    $other = ([regex]::Matches($out, "(?m)^\s*$([regex]::Escape($k))\s*=")).Count
    Check "$k không còn dòng nào khác" ($other -eq 1)
}
Check 'mọi dòng kết thúc bằng CRLF' (-not [regex]::IsMatch($out, '(?<!\r)\n'))
Check 'không xóa dòng nào' ((($out -split "`r`n").Count) -ge (($raw -split "`r`n").Count))
Check 'chạy lần 2 không đổi gì' ((Set-PhpIniText $out $exts $PhpSettings) -eq $out)
Check 'extension khác (bz2) vẫn là comment' ([regex]::IsMatch($out, '(?m)^;extension=bz2\r?$'))
$empty = Set-PhpIniText '' @('curl') ([ordered]@{ 'memory_limit' = '512M' })
Check 'php.ini rỗng: thêm extension và cấu hình' ($empty -match 'extension=curl' -and $empty -match 'memory_limit = 512M')
$special = Set-PhpIniText "memory_limit = 128M`r`n" @() ([ordered]@{ 'memory_limit' = '$1 $0' })
Check 'giá trị có $1 không bị hiểu là nhóm regex' ($special.Trim() -eq 'memory_limit = $1 $0')
Check 'danh sách extension giống LoWo (php.ini dùng chung)' (($PhpExtensions -join ',') -eq 'curl,fileinfo,gd,intl,mbstring,openssl,pdo_mysql,pdo_sqlite,sqlite3,sodium,zip')

# --- my.ini ---
$my = Get-MySqlIniText 'F:\dev\mysql97' 'F:\dev\mysql97-data' 3307
Check 'my.ini dùng dấu / thay \' ($my -match 'basedir=F:/dev/mysql97\r\n' -and $my -match 'datadir=F:/dev/mysql97-data\r\n')
Check 'my.ini cổng 3307 cho cả server và client' (([regex]::Matches($my, 'port=3307')).Count -eq 2)
Check 'my.ini chỉ nghe 127.0.0.1' ($my -match 'bind-address=127\.0\.0\.1')
Check 'my.ini toàn ASCII' (-not ($my.ToCharArray() | Where-Object { [int]$_ -gt 127 }))
Check 'my.ini dùng CRLF' (-not [regex]::IsMatch($my, '(?<!\r)\n'))
$lowoIni = "[mysqld]`r`nbasedir=F:/dev/mysql97`r`ndatadir=F:/dev/mysql97-data`r`nport=3307`r`nbind-address=127.0.0.1`r`ncharacter-set-server=utf8mb4`r`n`r`n[client]`r`nport=3307`r`nhost=127.0.0.1`r`ndefault-character-set=utf8mb4"
Check 'my.ini giống hệt bản LoWo ghi (MySQL dùng chung)' ($my -eq $lowoIni)

# --- Node.js ---
$idx = @(
    [pscustomobject]@{ version = 'v27.1.0'; files = @('win-x64-zip') },
    [pscustomobject]@{ version = 'v26.9.0'; files = @('linux-x64') },
    [pscustomobject]@{ version = 'v26.8.2'; files = @('win-x64-zip', 'win-x64-msi') },
    [pscustomobject]@{ version = 'v26.8.1'; files = @('win-x64-zip') },
    [pscustomobject]@{ version = 'v2.6.0'; files = @('win-x64-zip') }
)
Check 'chọn Node 26 mới nhất có bản zip Windows' ((Select-NodeRelease $idx 26).version -eq 'v26.8.2')
Check 'không có bản phù hợp thì trả null' ($null -eq (Select-NodeRelease $idx 30))
Check 'v2.6.0 không bị nhầm là v26' ($null -eq (Select-NodeRelease @($idx[4]) 26))
$json = '[{"version":"v26.8.2","files":["win-x64-zip"]},{"version":"v24.21.0","files":["win-x64-zip"]}]' | ConvertFrom-Json
Check 'đọc được mảng từ ConvertFrom-Json' ((Select-NodeRelease $json 26).version -eq 'v26.8.2')

# --- Mã kiểm tra, Visual C++, MinGit ---
$sums = "aaa111  node-v26.8.2-win-x64.zip`nBBB222  node-v26.8.2-win-x64.zip.extra`nccc333  node-v26.8.2-win-x86.zip"
Check 'lấy đúng mã kiểm tra theo tên file' ((Get-ChecksumFromList $sums 'node-v26.8.2-win-x64.zip') -eq 'aaa111')
Check 'không có tên file thì trả null' ($null -eq (Get-ChecksumFromList $sums 'khong-co.zip'))
Check 'chuỗi rỗng không lỗi' ($null -eq (Get-ChecksumFromList '' 'x.zip'))
Check 'VC 14.44 hợp lệ' (Test-VcRuntimeVersion 14 44)
Check 'VC 14.30 hợp lệ' (Test-VcRuntimeVersion 14 30)
Check 'VC 14.29 không hợp lệ' (-not (Test-VcRuntimeVersion 14 29))
Check 'VC 15.0 hợp lệ' (Test-VcRuntimeVersion 15 0)
$assets = @(
    [pscustomobject]@{ name = 'MinGit-2.52.0-busybox-64-bit.zip' },
    [pscustomobject]@{ name = 'MinGit-2.52.0-arm64.zip' },
    [pscustomobject]@{ name = 'MinGit-2.52.0-32-bit.zip' },
    [pscustomobject]@{ name = 'MinGit-2.52.0-64-bit.zip' }
)
Check 'chọn MinGit 64-bit thường' ((Select-MinGitAsset $assets).name -eq 'MinGit-2.52.0-64-bit.zip')
Check 'không có MinGit 64-bit thì trả null' ($null -eq (Select-MinGitAsset @($assets[0], $assets[1])))

# --- Giải nén (đường Expand-Archive, giả lập không có tar.exe) ---
$work = New-TestDir 'lernbuch-cai-dat'
$Dl = Join-Path $work 'tai-ve'; New-Item -ItemType Directory -Path $Dl | Out-Null
$srcA = Join-Path $work 'srcA\node-v26.8.2-win-x64'; New-Item -ItemType Directory -Force -Path $srcA | Out-Null
Set-Content -LiteralPath (Join-Path $srcA 'node.exe') -Value 'x'
Compress-Archive -Path (Join-Path $work 'srcA\*') -DestinationPath (Join-Path $Dl 'a.zip')
$srcB = Join-Path $work 'srcB'; New-Item -ItemType Directory -Force -Path (Join-Path $srcB 'ext') | Out-Null
Set-Content -LiteralPath (Join-Path $srcB 'php.exe') -Value 'x'
Set-Content -LiteralPath (Join-Path $srcB 'ext\php_curl.dll') -Value 'x'
Compress-Archive -Path (Join-Path $srcB '*') -DestinationPath (Join-Path $Dl 'b.zip')
$realSystemRoot = $env:SystemRoot
$env:SystemRoot = Join-Path $work 'khong-co-windows'
try {
    Install-Zip (Join-Path $Dl 'a.zip') (Join-Path $work 'node26') 6>$null | Out-Null
    Install-Zip (Join-Path $Dl 'b.zip') (Join-Path $work 'php85') 6>$null | Out-Null
} finally {
    $env:SystemRoot = $realSystemRoot
}
Check 'zip có thư mục gốc: file nằm ngay trong thư mục đích' (Test-Path (Join-Path $work 'node26\node.exe'))
Check 'zip không có thư mục gốc: php.exe và ext\ đúng chỗ' ((Test-Path (Join-Path $work 'php85\php.exe')) -and (Test-Path (Join-Path $work 'php85\ext\php_curl.dll')))
Check 'thư mục tạm đã được dọn' (@(Get-ChildItem $Dl -Directory).Count -eq 0)
$threw = $false
try { Get-File @('http://127.0.0.1:1/khong-co.zip') (Join-Path $Dl 'x.zip') 6>$null } catch { $threw = $_.Exception.Message -like '*Không tải được x.zip*' }
Check 'tải thất bại: báo lỗi rõ ràng' $threw
Check 'tải thất bại: không để lại file .part' (-not (Test-Path (Join-Path $Dl 'x.zip.part')))
$f = Join-Path $Dl 'c.bin'; Set-Content -LiteralPath $f -Value 'abc'
$threw = $false
try { Assert-Sha256 $f ('0' * 64) 6>$null } catch { $threw = $true }
Check 'sai SHA256: báo lỗi và xóa file' ($threw -and -not (Test-Path $f))
Set-Content -LiteralPath $f -Value 'abc' -NoNewline
$h = (Get-FileHash $f -Algorithm SHA256).Hash
$ok = $true; try { Assert-Sha256 $f $h.ToUpper() 6>$null } catch { $ok = $false }
Check 'đúng SHA256 (khác hoa thường): qua' $ok
Remove-Item -LiteralPath $work -Recurse -Force

Finish-Tests
