# Kiểm thử nhap-du-lieu.ps1: tìm dữ liệu Lernbuch cũ (đường dẫn đưa vào, hoặc nguon-cu\ của dự án).
# Chạy: powershell -File test-nhap-du-lieu.ps1
$ErrorActionPreference = 'Stop'
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '_kiem.ps1')))
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\nhap-du-lieu.ps1')))

$tmp = Join-Path ([IO.Path]::GetTempPath()) ('lernbuch-nhap-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Force -Path (Join-Path $tmp 'nguon-cu') | Out-Null
try {
    Check 'chưa có dữ liệu thì báo rõ phải chép lernweb.zip vào đâu' ((Get-ErrorText { Find-LernbuchSource '' $tmp }) -match 'nguon-cu')
    Check 'đường dẫn đưa vào không có thì báo lỗi' ((Get-ErrorText { Find-LernbuchSource (Join-Path $tmp 'khong-co.zip') $tmp }) -match 'Không thấy')

    $folder = Join-Path $tmp 'nguon-cu\lernweb\content'
    New-Item -ItemType Directory -Force -Path $folder | Out-Null
    [IO.File]::WriteAllText((Join-Path $folder 'seed.db'), 'x')
    Check 'thư mục lernweb có content\seed.db thì dùng thư mục đó' ((Find-LernbuchSource '' $tmp) -eq (Join-Path $tmp 'nguon-cu\lernweb'))

    [IO.File]::WriteAllText((Join-Path $tmp 'nguon-cu\lernweb.zip'), 'x')
    Check 'có lernweb.zip thì ưu tiên file zip' ((Find-LernbuchSource '' $tmp) -eq (Join-Path $tmp 'nguon-cu\lernweb.zip'))

    $khac = Join-Path $tmp 'khac.db'
    [IO.File]::WriteAllText($khac, 'x')
    Check 'đường dẫn đưa vào thì dùng đúng đường dẫn đó' ((Find-LernbuchSource $khac $tmp) -eq $khac)
} finally {
    Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
}
Finish-Tests
