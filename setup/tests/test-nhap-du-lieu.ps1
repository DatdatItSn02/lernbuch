# Kiểm thử nhap-du-lieu.ps1: tìm dữ liệu Lernbuch cũ (đường dẫn đưa vào, nguon-cu\ của dự án, hoặc nguon-cu\ cạnh dự án).
# Chạy: powershell -File test-nhap-du-lieu.ps1
$ErrorActionPreference = 'Stop'
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '_kiem.ps1')))
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\nhap-du-lieu.ps1')))

# Dựng như máy bạn: <gốc>\app là thư mục dự án (code), <gốc>\nguon-cu nằm cạnh
$tmp = Join-Path ([IO.Path]::GetTempPath()) ('lernbuch-nhap-' + [guid]::NewGuid().ToString('N'))
$app = Join-Path $tmp 'app'
New-Item -ItemType Directory -Force -Path (Join-Path $app 'nguon-cu'), (Join-Path $tmp 'nguon-cu') | Out-Null
try {
    Check 'chưa có dữ liệu thì báo rõ phải chép lernweb.zip vào đâu' ((Get-ErrorText { Find-LernbuchSource '' $app }) -match 'nguon-cu')
    Check 'đường dẫn đưa vào không có thì báo lỗi' ((Get-ErrorText { Find-LernbuchSource (Join-Path $tmp 'khong-co.zip') $app }) -match 'Không thấy')

    [IO.File]::WriteAllText((Join-Path $tmp 'nguon-cu\lernweb.zip'), 'x')
    Check 'lernweb.zip trong nguon-cu cạnh thư mục dự án (F:\CongViec\lernbuch\nguon-cu)' ((Find-LernbuchSource '' $app) -eq (Join-Path $tmp 'nguon-cu\lernweb.zip'))

    $folder = Join-Path $app 'nguon-cu\lernweb\content'
    New-Item -ItemType Directory -Force -Path $folder | Out-Null
    [IO.File]::WriteAllText((Join-Path $folder 'seed.db'), 'x')
    Check 'nguon-cu trong dự án được tìm trước; thư mục lernweb có content\seed.db thì dùng thư mục đó' ((Find-LernbuchSource '' $app) -eq (Join-Path $app 'nguon-cu\lernweb'))

    [IO.File]::WriteAllText((Join-Path $app 'nguon-cu\lernweb.zip'), 'x')
    Check 'có lernweb.zip thì ưu tiên file zip' ((Find-LernbuchSource '' $app) -eq (Join-Path $app 'nguon-cu\lernweb.zip'))

    $khac = Join-Path $tmp 'khac.db'
    [IO.File]::WriteAllText($khac, 'x')
    Check 'đường dẫn đưa vào thì dùng đúng đường dẫn đó' ((Find-LernbuchSource $khac $app) -eq $khac)
} finally {
    Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
}
Finish-Tests
