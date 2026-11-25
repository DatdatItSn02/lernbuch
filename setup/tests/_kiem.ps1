# Công cụ kiểm thử dùng chung cho setup\tests\test-*.ps1 (không cần Pester).
try { [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding $false } catch { }

$script:pass = 0
$script:fail = 0
$script:lastError = ''

function Check([string]$name, [bool]$cond) {
    if ($cond) {
        $script:pass++; Write-Host "PASS  $name"
    } else {
        $script:fail++; Write-Host "FAIL  $name" -ForegroundColor Red
        if ($script:lastError) { Write-Host "      lỗi gần nhất: $script:lastError" }
    }
    $script:lastError = ''
}

# Chạy khối lệnh, trả về nội dung lỗi nếu có (chuỗi rỗng nếu không lỗi).
function Get-ErrorText([scriptblock]$block) {
    try { & $block 6>$null | Out-Null; $script:lastError = '(không có lỗi)'; return '' }
    catch { $script:lastError = $_.Exception.Message; return $_.Exception.Message }
}

function Finish-Tests {
    Write-Host ''
    Write-Host "Kết quả: $script:pass qua, $script:fail lỗi"
    if ($script:fail -gt 0) { exit 1 }
    exit 0
}

# git trong thư mục $dir; lỗi thì dừng test và in đầu ra của git.
function Invoke-TestGit([string]$dir, [string[]]$a) {
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $out = @(& git -C $dir @a 2>&1 | ForEach-Object { "$_" })
        $code = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $old
    }
    if ($code -ne 0) { throw "git $($a -join ' ') lỗi (mã $code): $($out -join ' | ')" }
    return ,$out
}

# Ghi file với nội dung chính xác từng byte: UTF-8, có/không BOM, xuống dòng LF hoặc CRLF.
function Write-TestFile([string]$path, [string]$text, [switch]$Bom, [switch]$Crlf) {
    $t = $text -replace "`r`n", "`n"
    if ($Crlf) { $t = $t -replace "`n", "`r`n" }
    [byte[]]$bytes = (New-Object System.Text.UTF8Encoding $false).GetBytes($t)
    if ($Bom) { [byte[]]$bytes = @([byte]0xEF, [byte]0xBB, [byte]0xBF) + $bytes }
    $dir = Split-Path $path
    if ($dir) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    [IO.File]::WriteAllBytes($path, $bytes)
}

function New-TestDir([string]$prefix) {
    $d = Join-Path ([IO.Path]::GetTempPath()) ($prefix + '-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
    New-Item -ItemType Directory -Force -Path $d | Out-Null
    return $d
}
