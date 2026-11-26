#!/usr/bin/env bash
# Chạy trên alwaysdata. Dùng: remote-deploy.sh <tên-bản>
# Cần sẵn: ~/lernbuch/incoming/<tên-bản>.tgz và ~/lernbuch/incoming/<tên-bản>.env (từ workflow deploy).
# Migration chạy trên bản mới trước khi đổi current: lỗi thì dừng, bản đang chạy giữ nguyên.
set -euo pipefail
NAME="$1"
APP="$HOME/lernbuch"
REL="$APP/releases/$NAME"
IN="$APP/incoming"

mkdir -p "$APP/releases" "$APP/shared/storage/app/public" "$APP/shared/storage/logs" \
         "$APP/shared/storage/framework/cache" "$APP/shared/storage/framework/sessions" "$APP/shared/storage/framework/views"

php -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || {
  echo "Cần PHP 8.4 cho tài khoản alwaysdata (đang dùng $(php -r 'echo PHP_VERSION;'))"; exit 1; }

# Lỗi ở bất kỳ bước nào trước khi đổi current thì xoá bản dở dang, để lần quay lại sau không chọn nhầm nó
trap 'echo "✘ Deploy dừng, giữ nguyên bản đang chạy: $(basename "$(readlink -f "$APP/current")")"; rm -rf "$REL"' ERR

echo "== Giải nén $NAME"
rm -rf "$REL" && mkdir -p "$REL"
tar xzf "$IN/$NAME.tgz" -C "$REL"
rm -rf "$REL/storage" && ln -s "$APP/shared/storage" "$REL/storage"

echo "== Cập nhật .env (giữ APP_KEY cũ)"
KEY=""
[ -f "$APP/shared/.env" ] && KEY=$(grep -E '^APP_KEY=.+' "$APP/shared/.env" | head -1 | cut -d= -f2- || true)
cp "$IN/$NAME.env" "$APP/shared/.env.new" && chmod 600 "$APP/shared/.env.new"
if [ -n "$KEY" ]; then
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" "$APP/shared/.env.new"
fi
mv -f "$APP/shared/.env.new" "$APP/shared/.env"
ln -sfn "$APP/shared/.env" "$REL/.env"
cd "$REL"
if [ -z "$KEY" ]; then php artisan key:generate --force -n; fi

echo "== Migration"
php artisan migrate --force -n

echo "== Cache cấu hình, route, view"
php artisan optimize -n

echo "== Đổi current sang $NAME"
ln -sfn "$REL" "$APP/current.new" && mv -Tf "$APP/current.new" "$APP/current"
trap - ERR
php artisan queue:restart -n || true

echo "== Giữ 3 bản gần nhất (và luôn giữ bản tốt gần nhất để còn chỗ quay lại)"
TOT=$(ls -1 "$APP/releases" | sort -r | while read -r r; do [ -f "$APP/releases/$r/.da-kiem" ] && { echo "$r"; break; }; done || true)
ls -1 "$APP/releases" | sort | head -n -3 | while read -r r; do
  [ "$r" = "$TOT" ] || rm -rf "${APP:?}/releases/$r"
done
rm -f "$IN/$NAME.tgz" "$IN/$NAME.env"

# Gói Free tính chung 1 GB cho file, mail và database: ghi cả ba để theo dõi (tools/tu-kiem đọc các dòng này)
echo "== Dung lượng (gói Free: 1 GB tính chung file, mail và database)"
echo "Thư mục app: $(du -sh "$APP" 2>/dev/null | cut -f1)"
echo "Cả thư mục nhà: $(du -sh "$HOME" 2>/dev/null | cut -f1)"
php artisan app:dung-luong -n 2>/dev/null || true
echo "Xong: $NAME"
