# Kiểm các file trong setup\: cú pháp PowerShell 5.1, BOM, xuống dòng CRLF, .bat chỉ có chữ không dấu,
# .bat không dừng chờ bấm phím khi chạy trên CI. Chạy: powershell -File test-file.ps1
$ErrorActionPreference = 'Stop'
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '_kiem.ps1')))

$setup = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$ps1 = @(Get-ChildItem -LiteralPath $setup -Filter '*.ps1' -Recurse)
$bat = @(Get-ChildItem -LiteralPath $setup -Filter '*.bat' -Recurse)
Check 'có đủ script' (($ps1.Count -ge 10) -and ($bat.Count -ge 10))

foreach ($f in $ps1) {
    $name = $f.FullName.Substring($setup.Length + 1)
    $tokens = $null; $errors = $null
    [System.Management.Automation.Language.Parser]::ParseFile($f.FullName, [ref]$tokens, [ref]$errors) | Out-Null
    Check "$name : đúng cú pháp PowerShell" ($errors.Count -eq 0)
    foreach ($e in $errors) { Write-Host "      dòng $($e.Extent.StartLineNumber): $($e.Message)" }
    $b = [IO.File]::ReadAllBytes($f.FullName)
    Check "$name : có BOM UTF-8 (Windows PowerShell đọc đúng chữ có dấu)" (($b.Length -ge 3) -and ($b[0] -eq 0xEF) -and ($b[1] -eq 0xBB) -and ($b[2] -eq 0xBF))
    $text = [Text.Encoding]::GetEncoding(28591).GetString($b)
    Check "$name : xuống dòng CRLF" (-not [regex]::IsMatch($text, '(?<!\r)\n'))
}

foreach ($f in $bat) {
    $name = $f.FullName.Substring($setup.Length + 1)
    $b = [IO.File]::ReadAllBytes($f.FullName)
    Check "$name : chỉ có ký tự ASCII" (-not ($b | Where-Object { $_ -gt 127 }))
    $text = [Text.Encoding]::ASCII.GetString($b)
    Check "$name : xuống dòng CRLF (cmd.exe cần)" (-not [regex]::IsMatch($text, '(?<!\r)\n'))
    $lines = $text -split "`r`n"
    $barePause = @($lines | Where-Object { $_ -match '^\s*pause\b' })
    if ($f.Name -ne 'moi-truong.bat') {
        Check "$name : chỉ dừng chờ phím khi không chạy trên CI" ($barePause.Count -eq 0)
    }
}

$cap = [IO.File]::ReadAllText((Join-Path $setup 'cap-nhat.bat'))
Check 'cap-nhat.bat: cả khối lệnh trong ngoặc, có exit /b bên trong' ($cap -match '(?s)\r\n\(\r\n.*powershell.*exit /b\r\n\)')
Check 'cap-nhat.bat: chuyển tham số cho cap-nhat.ps1' ($cap -match 'cap-nhat\.ps1" %\*')

Finish-Tests
