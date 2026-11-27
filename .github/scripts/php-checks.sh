#!/usr/bin/env bash
# Kiểm tra phía PHP và database. Chạy hết các bước, cuối cùng báo bước lỗi.
# Cần: DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD (máy chủ MariaDB hoặc MySQL trống).
set -uo pipefail
export DB_USER="${DB_USERNAME}" DB_DATABASE="${DB_DATABASE:-lernbuch}"
SQL="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p${DB_PASSWORD} --default-character-set=utf8mb4"
fails=(); total=0
step() {
  local name="$1"; shift; total=$((total + 1))
  echo "::group::${name}"
  if "$@"; then echo "✔ ${name}"; else echo "✘ ${name}"; fails+=("${name}"); fi
  echo "::endgroup::"
}
count_tables() { $SQL -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}' AND table_name<>'migrations'"; }
seed_counts() {
  $SQL -N "${DB_DATABASE}" -e "SELECT CONCAT_WS(' ', (SELECT COUNT(*) FROM plans), (SELECT COUNT(*) FROM plan_entitlements),
    (SELECT COUNT(*) FROM ai_prompts), (SELECT COUNT(*) FROM settings), (SELECT COUNT(*) FROM roles),
    (SELECT COUNT(*) FROM role_has_permissions), (SELECT COUNT(*) FROM content_sources), (SELECT COUNT(*) FROM achievements))"
}
seed_twice() {
  php artisan db:seed --force -n && local a; a=$(seed_counts) &&
  php artisan db:seed --force -n && local b; b=$(seed_counts) &&
  echo "lần 1: $a | lần 2: $b" && [ "$a" = "$b" ] && [ "$(echo "$a" | cut -d' ' -f1-3)" = "5 40 10" ]
}
only_migrations_left() { [ "$(count_tables)" = "0" ]; }
build_ref() {
  $SQL -e "DROP DATABASE IF EXISTS lernbuch_ref; CREATE DATABASE lernbuch_ref CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" &&
  sed -e '/^CREATE DATABASE/d' -e '/^USE /d' database/sql/schema.sql | $SQL lernbuch_ref
}

$SQL -e "CREATE DATABASE IF NOT EXISTS ${DB_DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
         CREATE DATABASE IF NOT EXISTS lernbuch_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$SQL -N -e "SELECT CONCAT('Máy chủ database: ', VERSION())"
php -v | head -1
[ -f .env ] || cp .env.example .env
php artisan key:generate --force -n >/dev/null

step "Pint (định dạng code)" vendor/bin/pint --test -v
step "Larastan (phân tích tĩnh)" vendor/bin/phpstan analyse --no-progress --memory-limit=1G
step "Dựng database tham chiếu từ schema.sql" build_ref
step "Migrate lên" php artisan migrate --force -n
step "Khớp với schema.sql (0 khác biệt)" python3 tools/schema/compare_schema.py --ref lernbuch_ref --db "${DB_DATABASE}" --json storage/schema-diff.json
step "Quy ước LoWo (không khoá ngoại, thời gian số nguyên)" python3 tools/schema/check_conventions.py --migrations database/migrations --db "${DB_DATABASE}"
step "Migrate xuống hết" php artisan migrate:reset --force -n
step "Sau khi xuống chỉ còn bảng migrations" only_migrations_left
step "Migrate lên lại" php artisan migrate --force -n
step "Seeder chạy hai lần không nhân đôi" seed_twice
step "test_schema.py (cấu trúc và tình huống)" python3 tools/schema/test_schema.py
step "Test công cụ so khớp và quy ước" python3 -m unittest tools/schema/test_compare_schema.py tools/schema/test_check_conventions.py
step "Test trình kiểm file nhập" bash -c "cd import && python3 -m unittest test_validate_import.py"
step "Test công cụ audio (giọng được dùng để bán, tên file theo hash)" python3 -m unittest tools/audio/test_audio.py
step "Test công cụ tự kiểm G0, G1 (cả trường hợp lỗi)" python3 -m unittest tools/tu-kiem/test_tu_kiem.py tools/tu-kiem/test_lay_runs.py tools/tu-kiem/test_gon_ket_qua.py tools/tu-kiem/test_tu_kiem_g1.py
step "Test script deploy trên máy chủ (quay lại, giữ bản, migration hỏng)" python3 -m unittest deploy/test_remote.py
step "Test tạo .env cho alwaysdata (mật khẩu có ký tự đặc biệt)" python3 .github/scripts/test_env_production.py
mkdir -p ket-qua-tho ket-qua
step "Pest (database lernbuch_test)" env DB_DATABASE=lernbuch_test php artisan test --log-junit=ket-qua-tho/pest.xml
step "Hiệu năng với dữ liệu cỡ thật (tạo phiên p95 < 150 ms, API khác < 200 ms)" env DB_DATABASE=lernbuch_test php artisan test --group=hieu-nang --log-junit=ket-qua-tho/pest-hieu-nang.xml
# Bảng kết quả từng test (tên đầy đủ) cho công cụ tự kiểm G1, CI chép vào ci-results/ket-qua/php
python3 tools/tu-kiem/gon_ket_qua.py --junit ket-qua-tho/pest.xml --junit ket-qua-tho/pest-hieu-nang.xml > ket-qua/ket-qua.tsv || true

echo
echo "KẾT QUẢ: $((total - ${#fails[@]}))/${total} bước đạt"
for f in "${fails[@]}"; do echo "✘ lỗi: $f"; done
[ ${#fails[@]} -eq 0 ]
