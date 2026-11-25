# Lernbuch - chạy toàn bộ kiểm thử, ghi kết quả vào setup\ket-qua-test.txt để Claude đọc.
#   Định dạng PHP (Pint), phân tích tĩnh (Larastan), test PHP (Pest, trên MySQL database lernbuch_test),
#   kiểm tra kiểu TypeScript, ESLint, test giao diện (Vitest), build.
# Các bước so khớp schema.sql bằng Python chỉ chạy trên CI (GitHub Actions).

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

. (Join-Path $PSScriptRoot 'lernbuch-chung.ps1')

$Log = Join-Path $PSScriptRoot 'ket-qua-test.txt'

function Get-TestSteps {
    $php = Get-Php
    $npm = Get-Npm
    return @(
        @{ Name = 'Định dạng PHP (Pint)';                Exe = $php; Args = @('vendor/bin/pint', '--test') },
        @{ Name = 'Phân tích tĩnh PHP (Larastan)';       Exe = $php; Args = @('vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G') },
        @{ Name = 'Test PHP (Pest, MySQL lernbuch_test)'; Exe = $php; Args = @('artisan', 'test') },
        @{ Name = 'Kiểu TypeScript (tsc)';               Exe = $npm; Args = @('run', 'typecheck') },
        @{ Name = 'ESLint';                              Exe = $npm; Args = @('run', 'lint') },
        @{ Name = 'Test giao diện (Vitest)';             Exe = $npm; Args = @('run', 'test') },
        @{ Name = 'Build giao diện (Vite)';              Exe = $npm; Args = @('run', 'build') }
    )
}

if ($MyInvocation.InvocationName -ne '.') {
    Start-Transcript -Path $Log -Force | Out-Null
    $lines = New-Object System.Collections.Generic.List[string]
    $passed = 0
    $exitCode = 0
    $steps = @()
    try {
        Use-LernbuchEnvironment
        $steps = Get-TestSteps
        foreach ($f in @('vendor\autoload.php', 'node_modules', '.env')) {
            if (-not (Test-Path -LiteralPath (Join-Path $Project $f))) { throw "Chưa có $f. Hãy chạy setup\cap-nhat.bat trước." }
        }
        Start-SharedMySql
        New-LernbuchDatabases
        Invoke-Checked (Get-Php) @('artisan', 'config:clear', '--no-ansi') 'xóa cache cấu hình'

        foreach ($step in $steps) {
            Write-Step $step.Name
            $watch = [Diagnostics.Stopwatch]::StartNew()
            $code = Invoke-Native $step.Exe $step.Args
            $watch.Stop()
            if ($code -eq 0) { $passed++ } else { $exitCode = 1 }
            $lines.Add((Format-StepResult $step.Name $code $watch.Elapsed.TotalSeconds))
        }
    } catch {
        $exitCode = 1
        Write-Host ''
        Write-Host "LỖI: $($_.Exception.Message)" -ForegroundColor Red
    } finally {
        Write-Step 'TỔNG KẾT'
        foreach ($l in $lines) {
            if ($l.StartsWith('✔')) { Write-Host $l -ForegroundColor Green } else { Write-Host $l -ForegroundColor Red }
        }
        Write-Host (Format-Summary $passed (@($steps).Count))
        if ($exitCode -eq 0) { Write-Host 'TẤT CẢ ĐỀU QUA' -ForegroundColor Green } else { Write-Host 'CÓ BƯỚC CHƯA QUA - báo Claude đọc setup\ket-qua-test.txt' -ForegroundColor Red }
        Stop-Transcript | Out-Null
    }
    exit $exitCode
}
