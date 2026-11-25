# Kiểm thử các hàm thuần trong lernbuch-chung.ps1. Chạy: powershell -File test-chung.ps1
$ErrorActionPreference = 'Stop'
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '_kiem.ps1')))
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\lernbuch-chung.ps1')))

# --- Giá trị mặc định ---
Check 'thư mục dự án là thư mục cha của setup' (Test-Path -LiteralPath (Join-Path $Project 'artisan'))
Check 'web chạy ở 127.0.0.1:8001 (không trùng LoWo ở localhost:8000)' (($WebHost -eq '127.0.0.1') -and ($WebPort -eq 8001))
Check 'hai database lernbuch và lernbuch_test' ((@($Databases) -join ',') -eq 'lernbuch,lernbuch_test')
if (-not $env:LERNBUCH_DEV) { Check 'mặc định dùng F:\dev như LoWo' ($Root -eq 'F:\dev') }
if (-not $env:LERNBUCH_MYSQL_PORT) { Check 'mặc định MySQL cổng 3307 như LoWo' ($Port -eq 3307) }

# --- Mã Git của nội dung (so với git hash-object) ---
$latin1 = [Text.Encoding]::GetEncoding(28591)
Check 'mã Git của nội dung rỗng' ((Get-GitBlobHash (New-Object byte[] 0)) -eq 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391')
Check 'mã Git của "hello\n"' ((Get-GitBlobHash $latin1.GetBytes("hello`n")) -eq 'ce013625030ba8dba906f756967f9e9ca394464a')
Check 'mã Git của nội dung CRLF' ((Get-GitBlobHash $latin1.GetBytes("a`r`nb`r`n")) -eq 'c30dea8a3641ea99b125d04d599d843712292759')
Check 'mã Git của nội dung có BOM và chữ có dấu' ((Get-GitBlobHash ([byte[]](0xEF, 0xBB, 0xBF, 0x23, 0x20, 0x54, 0x69, 0xE1, 0xBA, 0xBF, 0x6E, 0x67, 0x0A))) -eq 'e4b7e5fa6f481361754e1cd861df0a83947ba6c0')

# --- So file trên máy với bản Git ---
$work = New-TestDir 'lernbuch-chung'
$f = Join-Path $work 'a.txt'
$lfBlob = '422c2b7ab3b3c668038da977e4e93a5fc623169c'   # "a\nb\n"
Write-TestFile $f "a`nb`n"
Check 'giống hệt bản Git' (Test-SameAsBlob $f $lfBlob)
Write-TestFile $f "a`nb`n" -Crlf
Check 'chỉ khác kiểu xuống dòng (CRLF) vẫn coi là giống' (Test-SameAsBlob $f $lfBlob)
Write-TestFile $f "a`r`nb`r`n"
Check 'bản Git lưu CRLF, file trên máy CRLF: giống' (Test-SameAsBlob $f 'c30dea8a3641ea99b125d04d599d843712292759')
Write-TestFile $f "a`nB`n"
Check 'khác nội dung: không giống' (-not (Test-SameAsBlob $f $lfBlob))
Check 'file không có nhưng bản Git có: không giống' (-not (Test-SameAsBlob (Join-Path $work 'khong-co.txt') $lfBlob))
Check 'cả hai đều không có: giống' (Test-SameAsBlob (Join-Path $work 'khong-co.txt') '')
Check 'file có nhưng bản Git không có: không giống' (-not (Test-SameAsBlob $f ''))
Remove-Item -LiteralPath $work -Recurse -Force

# --- Dòng kết quả ---
Check 'bước đạt' ((Format-StepResult 'Pint' 0 3.14159) -eq '✔ Pint (3.1 giây)')
Check 'bước lỗi có mã thoát' ((Format-StepResult 'Pest' 2 12) -eq '✘ Pest (mã 2, 12 giây)')
Check 'số giây dùng dấu chấm dù máy để định dạng Việt Nam' ((Format-StepResult 'x' 0 1.25).Contains('1.2') -or (Format-StepResult 'x' 0 1.25).Contains('1.3'))
Check 'dòng tổng kết' ((Format-Summary 6 7) -eq 'KẾT QUẢ: 6/7 bước đạt')

# --- SQL tạo database ---
$sql = Get-CreateDatabaseSql @('lernbuch', 'lernbuch_test')
Check 'tạo đủ hai database' (([regex]::Matches($sql, 'CREATE DATABASE IF NOT EXISTS')).Count -eq 2)
Check 'utf8mb4_unicode_ci giống CI và alwaysdata' (([regex]::Matches($sql, 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')).Count -eq 2)

# --- Môi trường ---
Check 'Test-Port trả false với cổng không ai dùng' (-not (Test-Port 1))
$git = $null; try { $git = Find-Git } catch { }
Check 'tìm thấy git' ($git -and (Test-Path -LiteralPath $git))
$err = Get-ErrorText { $Root = 'Z:\khong-co'; Use-LernbuchEnvironment }
Check 'thiếu PHP thì báo chạy cai-dat.bat' ($err -like '*cai-dat.bat*')

Finish-Tests
