#!/usr/bin/env bash
# Chạy trên alwaysdata khi địa chỉ web trả lỗi: xem bản đang chạy có đủ file không và tự gọi /up
# ngay trong PHP (không qua web server). App trả 200 mà địa chỉ web trả 404 nghĩa là site chưa trỏ vào app.
set -uo pipefail
APP="$HOME/lernbuch"
echo "current -> $(readlink -f "$APP/current" 2>/dev/null || echo 'chưa có')"
for f in public/index.php public/.htaccess .env vendor/autoload.php; do
  if [ -e "$APP/current/$f" ]; then echo "có $f"; else echo "THIẾU $f"; fi
done
cd "$APP/current" 2>/dev/null || exit 0
CODE=$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
echo $kernel->handle(Illuminate\Http\Request::create("/up", "GET"))->getStatusCode();
' 2>"$APP/check.err")
echo "app tự trả /up: ${CODE:-lỗi}"
if [ -s "$APP/check.err" ]; then head -5 "$APP/check.err"; fi
rm -f "$APP/check.err"
