#!/usr/bin/env bash
# Chạy trên alwaysdata: đổi current về bản ngay trước bản đang chạy. Không đụng database.
set -euo pipefail
APP="$HOME/lernbuch"
CUR=$(basename "$(readlink -f "$APP/current")")
PREV=$(ls -1 "$APP/releases" | sort | grep -B1 -x "$CUR" | head -1)
if [ -z "$PREV" ] || [ "$PREV" = "$CUR" ]; then
  echo "Không có bản trước để quay lại (đang chạy $CUR)"; exit 1
fi
ln -sfn "$APP/releases/$PREV" "$APP/current.new" && mv -Tf "$APP/current.new" "$APP/current"
cd "$APP/current" && php artisan optimize -n && (php artisan queue:restart -n || true)
echo "Đã quay lại: $CUR -> $PREV"
