# Chạy mọi file test-*.ps1 trong thư mục này, mỗi file một tiến trình Windows PowerShell riêng.
# Ghi đầu ra và tổng kết (UTF-8) vào -Log để CI gửi lên nhánh ci-results.
param([string]$Log = (Join-Path ([IO.Path]::GetTempPath()) 'lernbuch-setup-tests.log'))

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding $false } catch { }
if (-not [IO.Path]::IsPathRooted($Log)) { $Log = Join-Path (Get-Location).Path $Log }
$utf8 = New-Object System.Text.UTF8Encoding $false
New-Item -ItemType Directory -Force -Path (Split-Path $Log) | Out-Null
[IO.File]::WriteAllText($Log, '', $utf8)
function Out-Log([string]$line) { Write-Host $line; [IO.File]::AppendAllText($Log, $line + "`n", $utf8) }

$tests = @(Get-ChildItem -LiteralPath $PSScriptRoot -Filter 'test-*.ps1' | Sort-Object Name)
$results = New-Object System.Collections.Generic.List[string]
$passed = 0
foreach ($t in $tests) {
    Out-Log "===== $($t.Name)"
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & powershell -NoProfile -ExecutionPolicy Bypass -File $t.FullName 2>&1 | ForEach-Object { Out-Log "$_" }
        $code = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $old
    }
    if ($code -eq 0) { $passed++; $results.Add("✔ $($t.Name)") } else { $results.Add("✘ $($t.Name) (mã $code)") }
}
Out-Log ''
foreach ($r in $results) { Out-Log $r }
Out-Log "KẾT QUẢ: $passed/$($tests.Count) file test đạt"
if ($passed -eq $tests.Count -and $tests.Count -gt 0) { exit 0 }
exit 1
