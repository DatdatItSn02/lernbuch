#!/usr/bin/env bash
# Chạy trên alwaysdata: đổi current về bản trước. Không đụng database.
# Ưu tiên bản gần nhất đã qua kiểm /up (có file .da-kiem do workflow ghi); chưa có bản nào như vậy thì lấy bản ngay trước.
set -euo pipefail
APP="$HOME/lernbuch"
CUR=$(basename "$(readlink -f "$APP/current")")
TRUOC=$(ls -1 "$APP/releases" | sort | awk -v cur="$CUR" '$0 < cur')
PREV=""
for r in $(echo "$TRUOC" | sort -r); do
  if [ -f "$APP/releases/$r/.da-kiem" ]; then PREV="$r"; break; fi
done
[ -n "$PREV" ] || PREV=$(echo "$TRUOC" | tail -1)
if [ -z "$PREV" ]; then
  echo "Không có bản trước để quay lại (đang chạy $CUR)"; exit 1
fi
ln -sfn "$APP/releases/$PREV" "$APP/current.new" && mv -Tf "$APP/current.new" "$APP/current"
cd "$APP/current" && php artisan optimize -n && (php artisan queue:restart -n || true)
echo "Đã quay lại: $CUR -> $PREV"
