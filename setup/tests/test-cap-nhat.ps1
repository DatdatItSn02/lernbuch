# Kiểm thử cap-nhat.ps1: sửa .env và luồng lấy code bằng Git (dùng git thật trong thư mục tạm).
# Chạy: powershell -File test-cap-nhat.ps1
$ErrorActionPreference = 'Stop'
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '_kiem.ps1')))
. ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\cap-nhat.ps1')))

# --- Sửa .env ---
$sample = "APP_NAME=Lernbuch`r`nAPP_ENV=local`r`nAPP_KEY=`r`nAPP_URL=http://localhost:8000`r`nDB_CONNECTION=mariadb`r`n# DB_HOST=127.0.0.1`r`nDB_PORT=3306`r`nDB_USERNAME=lernbuch`r`nDB_PASSWORD=bi-mat`r`n"
$out = Set-EnvText $sample $EnvValues
$lines = $out -split "`n"
Check 'giữ nguyên khóa không liên quan' (($lines -contains 'APP_NAME=Lernbuch') -and ($lines -contains 'APP_ENV=local'))
Check 'web ở 127.0.0.1:8001' ($lines -contains 'APP_URL=http://127.0.0.1:8001')
Check 'DB_CONNECTION đổi sang mysql' ($lines -contains 'DB_CONNECTION=mysql')
Check 'dòng bị comment được bật lại' (($lines -contains 'DB_HOST=127.0.0.1') -and -not ($out -match '# DB_HOST'))
Check "DB_PORT=$Port (MySQL dùng chung với LoWo)" ($lines -contains "DB_PORT=$Port")
Check 'tài khoản root, mật khẩu trống' (($lines -contains 'DB_USERNAME=root') -and ($lines -contains 'DB_PASSWORD='))
Check 'khóa chưa có thì thêm vào' ($lines -contains 'DB_DATABASE=lernbuch')
Check 'không còn ký tự CR' (-not $out.Contains("`r"))
Check 'mỗi khóa chỉ một dòng' ((@($lines | Where-Object { $_ -like 'DB_PORT=*' })).Count -eq 1)
Check 'chạy lần hai không đổi gì' ((Set-EnvText $out $EnvValues) -eq $out)
Check '.env rỗng vẫn tạo đủ khóa' ((@((Set-EnvText '' $EnvValues) -split "`n" | Where-Object { $_ -ne '' })).Count -eq $EnvValues.Count)
Check '.env.example trong code đã đúng sẵn cho máy này' ((Set-EnvText ([IO.File]::ReadAllText((Join-Path $Project '.env.example'))) $EnvValues) -eq ([IO.File]::ReadAllText((Join-Path $Project '.env.example')) -replace "`r", ''))

# --- APP_KEY ---
$key = New-AppKey
Check 'khóa mới đúng dạng base64 32 byte' (($key -match '^base64:[A-Za-z0-9+/]{43}=$') -and ([Convert]::FromBase64String($key.Substring(7)).Length -eq 32))
Check 'hai lần tạo ra hai khóa khác nhau' ($key -ne (New-AppKey))
Check 'APP_KEY trống thì đặt khóa mới' ((Set-AppKeyText $out $key) -split "`n" -contains "APP_KEY=$key")
$withKey = Set-EnvText $out ([ordered]@{ 'APP_KEY' = 'base64:dang-dung' })
Check 'APP_KEY đã có thì giữ nguyên' ((Set-AppKeyText $withKey $key) -eq $withKey)
Check 'chưa có dòng APP_KEY thì thêm vào' ((Set-AppKeyText "APP_NAME=x`n" $key) -split "`n" -contains "APP_KEY=$key")

# --- Luồng lấy code bằng Git ---
$work = New-TestDir 'lernbuch-git'
$dev = Join-Path $work 'dev'          # nơi Claude commit rồi đẩy lên
$github = Join-Path $work 'github.git' # đóng vai GitHub (không đặt tên $remote: trùng biến $Remote của cap-nhat.ps1)
$app = Join-Path $work 'app'          # máy người dùng
New-Item -ItemType Directory -Force -Path $dev | Out-Null
Invoke-TestGit $dev @('init', '-q', '-b', 'main') | Out-Null
Invoke-TestGit $dev @('config', 'user.name', 'test') | Out-Null
Invoke-TestGit $dev @('config', 'user.email', 'test@local') | Out-Null
Invoke-TestGit $dev @('config', 'core.autocrlf', 'false') | Out-Null
Write-TestFile "$dev\.gitattributes" "* text=auto eol=lf`n"
Write-TestFile "$dev\.gitignore" "/vendor`n.env`n"
Write-TestFile "$dev\README.md" "ban 1`n"
Write-TestFile "$dev\composer.json" "{}`n"
Write-TestFile "$dev\cu.txt" "se bi xoa`n"
function Publish([string]$msg) {
    Invoke-TestGit $dev @('add', '-A') | Out-Null
    Invoke-TestGit $dev @('commit', '-q', '-m', $msg) | Out-Null
    Invoke-TestGit $dev @('push', '-q', 'origin', 'main') | Out-Null
    return (Invoke-TestGit $dev @('rev-parse', 'HEAD'))[0].Trim()
}
Invoke-TestGit $dev @('add', '-A') | Out-Null
Invoke-TestGit $dev @('commit', '-q', '-m', 'ban 1') | Out-Null
Invoke-TestGit $work @('clone', '-q', '--bare', $dev, $github) | Out-Null
Invoke-TestGit $dev @('remote', 'add', 'origin', $github) | Out-Null
Invoke-TestGit $work @('clone', '-q', $github, $app) | Out-Null
function Get-Head { return (Invoke-TestGit $app @('rev-parse', 'HEAD'))[0].Trim() }
function Test-Clean { return ((Invoke-TestGit $app @('status', '--porcelain')).Count -eq 0) }

$Project = $app
Write-TestFile "$app\vendor\abc\x.php" "giu nguyen`n"
Write-TestFile "$app\.env" "APP_KEY=bi-mat`n"

$h1 = Get-Head
Check 'đã là bản mới nhất: không lỗi' ((Get-ErrorText { Update-Code }) -eq '')
Check 'đã là bản mới nhất: không đổi gì' ((Get-Head) -eq $h1)

# Bản mới thêm script setup\ (CRLF), sửa .gitattributes, sửa README, xóa cu.txt.
# Claude gửi trước hai script vào máy (chưa có trong Git của máy, xuống dòng CRLF như Git sẽ ghi).
Write-TestFile "$dev\.gitattributes" "* text=auto eol=lf`n*.bat text eol=crlf`n*.ps1 text eol=crlf`n"
$batText = "@echo off`nrem thu`ngoto nhan`n:nhan`necho xong`n"
$psText = "# Tiếng Việt có dấu`nWrite-Host 'xin chào'`n"
Write-TestFile "$dev\setup\chay.bat" $batText
Write-TestFile "$dev\setup\chay.ps1" $psText -Bom
Write-TestFile "$dev\README.md" "ban 2`n"
Remove-Item -LiteralPath "$dev\cu.txt"
$h2 = Publish 'ban 2'
Write-TestFile "$app\setup\chay.bat" $batText -Crlf
Write-TestFile "$app\setup\chay.ps1" $psText -Bom -Crlf

$err = Get-ErrorText { Update-Code }
Check 'file gửi trước giống bản mới: cập nhật được' ($err -eq '')
if ($err) { Write-Host $err }
Check 'lên đúng bản mới nhất' ((Get-Head) -eq $h2)
Check 'file đã xóa trong bản mới bị xóa' (-not (Test-Path -LiteralPath "$app\cu.txt"))
Check 'README là bản mới' ([IO.File]::ReadAllText("$app\README.md") -eq "ban 2`n")
$bat = [IO.File]::ReadAllText("$app\setup\chay.bat")
Check '.bat ghi bằng CRLF, không còn LF lẻ' ($bat.Contains("`r`n") -and -not [regex]::IsMatch($bat, '(?<!\r)\n'))
$psBytes = [IO.File]::ReadAllBytes("$app\setup\chay.ps1")
Check '.ps1 giữ BOM UTF-8 và dùng CRLF' (($psBytes[0] -eq 0xEF) -and ($psBytes[1] -eq 0xBB) -and ($psBytes[2] -eq 0xBF) -and ([Text.Encoding]::UTF8.GetString($psBytes).Contains("`r`n")))
Check 'vendor và .env không bị đụng tới' (([IO.File]::ReadAllText("$app\vendor\abc\x.php") -eq "giu nguyen`n") -and ([IO.File]::ReadAllText("$app\.env") -eq "APP_KEY=bi-mat`n"))
Check 'không còn thay đổi nào chưa ghi' (Test-Clean)
Check 'chạy lại ngay: không lỗi, không đổi' (((Get-ErrorText { Update-Code }) -eq '') -and ((Get-Head) -eq $h2))

# Bản mới sửa chính script; Claude gửi trước file đó vào máy (file đã có trong Git của máy).
$psText3 = "# Tiếng Việt có dấu`nWrite-Host 'ban 3'`n"
Write-TestFile "$dev\setup\chay.ps1" $psText3 -Bom
$h3 = Publish 'ban 3'
Write-TestFile "$app\setup\chay.ps1" $psText3 -Bom -Crlf
Check 'file có trong Git, gửi trước giống bản mới: cập nhật được' (((Get-ErrorText { Update-Code }) -eq '') -and ((Get-Head) -eq $h3))
Check 'nội dung là bản mới, không còn thay đổi chưa ghi' ([Text.Encoding]::UTF8.GetString([IO.File]::ReadAllBytes("$app\setup\chay.ps1")).Contains('ban 3') -and (Test-Clean))

# Người dùng tự sửa README, bản mới cũng sửa README: dừng, giữ phần tự sửa.
Write-TestFile "$dev\README.md" "ban 4`n"
$h4 = Publish 'ban 4'
Write-TestFile "$app\README.md" "tu sua tren may`n"
$err = Get-ErrorText { Update-Code }
Check 'file tự sửa khác bản mới: dừng và nêu tên file' ($err -like '*README.md*')
Check 'file tự sửa: giữ nguyên phần đã sửa, chưa cập nhật' (([IO.File]::ReadAllText("$app\README.md") -eq "tu sua tren may`n") -and ((Get-Head) -eq $h3))
Invoke-TestGit $app @('checkout', '--', 'README.md') | Out-Null

# Tự sửa một file mà bản mới không đụng tới: cũng dừng (máy phải giống GitHub).
Write-TestFile "$app\composer.json" "{ }`n"
Check 'file tự sửa không có trong lần cập nhật: dừng và nêu tên file' ((Get-ErrorText { Update-Code }) -like '*composer.json*')
Invoke-TestGit $app @('checkout', '--', 'composer.json') | Out-Null
Check 'bỏ phần tự sửa thì cập nhật được' (((Get-ErrorText { Update-Code }) -eq '') -and ((Get-Head) -eq $h4))

# Bản mới thêm file mà máy đã có một file khác nội dung cùng tên (chưa có trong Git).
Write-TestFile "$dev\docs\moi.md" "noi dung moi`n"
$h5 = Publish 'ban 5'
Write-TestFile "$app\docs\moi.md" "tu viet`n"
$err = Get-ErrorText { Update-Code }
Check 'file lạ trùng tên file mới: dừng và nêu tên file' ($err -like '*docs/moi.md*')
Check 'file lạ: giữ nguyên nội dung' ([IO.File]::ReadAllText("$app\docs\moi.md") -eq "tu viet`n")
Remove-Item -LiteralPath "$app\docs\moi.md"
Check 'bỏ file lạ thì cập nhật được' (((Get-ErrorText { Update-Code }) -eq '') -and ((Get-Head) -eq $h5))

# Đang ở nhánh khác main.
Invoke-TestGit $app @('checkout', '-q', '-b', 'thu') | Out-Null
Check 'không ở nhánh main: dừng và nêu tên nhánh' ((Get-ErrorText { Update-Code }) -like '*nhánh thu*')
Invoke-TestGit $app @('checkout', '-q', 'main') | Out-Null

# Máy có commit riêng chưa có trên GitHub.
Invoke-TestGit $app @('config', 'user.name', 'may') | Out-Null
Invoke-TestGit $app @('config', 'user.email', 'may@local') | Out-Null
Write-TestFile "$app\rieng.txt" "rieng`n"
Invoke-TestGit $app @('add', 'rieng.txt') | Out-Null
Invoke-TestGit $app @('commit', '-q', '-m', 'rieng') | Out-Null
Check 'máy có commit riêng: dừng' ((Get-ErrorText { Update-Code }) -like '*commit riêng*')
Invoke-TestGit $app @('reset', '-q', '--hard', $h5) | Out-Null

# Không lấy được code (mất mạng, sai địa chỉ).
Invoke-TestGit $app @('remote', 'set-url', 'origin', (Join-Path $work 'khong-co.git')) | Out-Null
Check 'không lấy được code: báo lỗi git fetch' ((Get-ErrorText { Update-Code }) -like '*git fetch*')
Invoke-TestGit $app @('remote', 'set-url', 'origin', $github) | Out-Null
Check 'trả lại địa chỉ đúng: cập nhật được' (((Get-ErrorText { Update-Code }) -eq '') -and ((Get-Head) -eq $h5))

# Thư mục chưa phải kho Git.
$Project = Join-Path $work 'khong-git'
New-Item -ItemType Directory -Force -Path $Project | Out-Null
Check 'chưa phải kho Git: báo cách lấy code' ((Get-ErrorText { Update-Code }) -like '*git clone*')

Set-Location ([IO.Path]::GetTempPath())
Remove-Item -LiteralPath $work -Recurse -Force -ErrorAction SilentlyContinue
Finish-Tests
