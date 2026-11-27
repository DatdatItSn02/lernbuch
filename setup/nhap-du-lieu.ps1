# Lernbuch - nhập nội dung Lernbuch cũ (thẻ, chủ đề, ngữ pháp, bài tập, động từ) vào database trên máy này.
# Bước 1 chạy thử: đọc, kiểm quy tắc dữ liệu, in báo cáo, chưa ghi gì. Bước 2 hỏi bạn rồi mới ghi (hoặc chạy với -Ghi).
# Nguồn mặc định: nguon-cu\lernweb.zip trong thư mục dự án, hoặc cạnh thư mục dự án (F:\CongViec\lernbuch\nguon-cu khi code ở
# F:\CongViec\lernbuch\app); muốn chỗ khác thì kéo file hoặc thư mục vào nhap-du-lieu.bat.
# Chỉ nhập nguồn có quyền dùng để bán. Kết quả ghi vào setup\ket-qua-nhap-du-lieu.txt.
param([string]$Nguon = '', [switch]$Ghi)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lernbuch-chung.ps1')

$Log = Join-Path $PSScriptRoot 'ket-qua-nhap-du-lieu.txt'

# Tìm dữ liệu Lernbuch cũ: đường dẫn bạn đưa; không có thì trong nguon-cu\ của dự án, rồi nguon-cu\ cạnh thư mục dự án;
# trong mỗi chỗ thử lernweb.zip, thư mục lernweb, rồi chính nguon-cu (có content\seed.db hoặc data\app.db).
function Find-LernbuchSource([string]$given, [string]$project) {
    if ($given -ne '') {
        if (Test-Path -LiteralPath $given) { return (Resolve-Path -LiteralPath $given).Path }
        throw "Không thấy $given."
    }
    $dirs = @((Join-Path $project 'nguon-cu'))
    $parent = Split-Path $project -Parent
    if ($parent) { $dirs += (Join-Path $parent 'nguon-cu') }
    foreach ($dir in $dirs) {
        foreach ($c in @('lernweb.zip', 'lernweb', '.')) {
            $p = Join-Path $dir $c
            if ((Test-Path -LiteralPath $p -PathType Leaf) -and $p.ToLower().EndsWith('.zip')) { return (Resolve-Path -LiteralPath $p).Path }
            if ((Test-Path -LiteralPath $p -PathType Container) -and ((Test-Path -LiteralPath (Join-Path $p 'content\seed.db')) -or (Test-Path -LiteralPath (Join-Path $p 'data\app.db')))) {
                return (Resolve-Path -LiteralPath $p).Path
            }
        }
    }
    throw 'Không thấy dữ liệu Lernbuch cũ. Chép lernweb.zip vào thư mục nguon-cu\ cạnh thư mục app\ (hoặc trong app\), hoặc kéo file vào nhap-du-lieu.bat.'
}

function Invoke-Import([string]$source, [bool]$write) {
    $argList = @('artisan', 'app:nhap-lernbuch', $source, '--no-ansi')
    if ($write) { $argList += '--ghi' }
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & (Get-Php) @argList 2>&1 | ForEach-Object { Write-Host "$_" }
        return $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $old
    }
}

if ($MyInvocation.InvocationName -ne '.') {
    Start-Transcript -Path $Log -Force | Out-Null
    $code = 1
    try {
        Use-LernbuchEnvironment
        foreach ($f in @('vendor\autoload.php', '.env')) {
            if (-not (Test-Path -LiteralPath (Join-Path $Project $f))) { throw "Chưa có $f. Hãy chạy setup\cap-nhat.bat trước." }
        }
        Start-SharedMySql
        $source = Find-LernbuchSource $Nguon $Project
        Push-Location -LiteralPath $Project
        try {
            Write-Step "Chạy thử: đọc $source, kiểm quy tắc dữ liệu (chưa ghi gì)"
            $code = Invoke-Import $source $false
            if ($code -eq 0) {
                $ok = [bool]$Ghi
                if (-not $ok -and -not $env:CI) {
                    try { [Console]::InputEncoding = New-Object System.Text.UTF8Encoding $false } catch { }
                    $answer = Read-Host 'Ghi vào database? Gõ c rồi Enter để ghi, phím khác để thôi'
                    $ok = ($answer -match '^\s*[cC]')
                }
                if ($ok) {
                    Write-Step 'Ghi vào database'
                    $code = Invoke-Import $source $true
                    if ($code -eq 0) { Write-Ok "Đã nhập. Mở web: setup\chay-web.bat rồi vào http://$($WebHost):$WebPort/app" }
                } else {
                    Write-Note 'Chưa ghi gì vào database.'
                }
            }
        } finally {
            Pop-Location
        }
    } catch {
        $code = 1
        Write-Host ''
        Write-Host "LỖI: $($_.Exception.Message)" -ForegroundColor Red
    } finally {
        Stop-Transcript | Out-Null
    }
    exit $code
}
