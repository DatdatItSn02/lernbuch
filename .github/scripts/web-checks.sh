#!/usr/bin/env bash
# Kiểm tra phía giao diện. Chạy hết các bước, cuối cùng báo bước lỗi.
# Cần: vendor (composer install, theme Filament và php artisan serve dùng), node_modules, Chromium của Playwright,
# database trống (DB_* trong môi trường) cho phần đăng nhập.
set -uo pipefail
fails=(); total=0
step() {
  local name="$1"; shift; total=$((total + 1))
  echo "::group::${name}"
  if "$@"; then echo "✔ ${name}"; else echo "✘ ${name}"; fails+=("${name}"); fi
  echo "::endgroup::"
}
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --no-interaction --no-ansi >/dev/null

step "Kiểm kiểu TypeScript" npm run --silent typecheck
step "ESLint" npm run --silent lint
mkdir -p ket-qua-tho ket-qua
step "Vitest" npm run --silent test -- --reporter=default --reporter=json --outputFile.json=ket-qua-tho/vitest.json
step "Build Vite (app học, trang chào, theme quản trị)" npm run --silent build
step "Tương phản màu WCAG AA (palette_check.py trên tokens.css)" python3 design/palette_check.py
step "Test palette_check (cả trường hợp lỗi)" python3 -m unittest design/test_palette_check.py
step "Test sinh icon app (cả trường hợp lỗi)" bash -c "python3 -m pip install -q --user pillow fonttools brotli 2>/dev/null || python3 -m pip install -q --user --break-system-packages pillow fonttools brotli; python3 -m unittest design/test_icons.py"
step "Database cho test trình duyệt (migrate, dữ liệu khởi tạo, tài khoản người học thử, nội dung mẫu)" bash -c "php artisan migrate --force -n --no-ansi && php artisan db:seed --force -n --no-ansi && php artisan db:seed --class=NguoiHocThuSeeder --force -n --no-ansi && php artisan db:seed --class=NoiDungThuSeeder --force -n --no-ansi"
step "Playwright: font, bố cục, đăng nhập, app học (bàn phím, axe WCAG 2.2 AA, sáng/tối) trên máy tính, điện thoại" \
  env PLAYWRIGHT_JSON_OUTPUT_FILE=ket-qua-tho/playwright.json PLAYWRIGHT_JSON_OUTPUT_NAME=ket-qua-tho/playwright.json npx playwright test --reporter=list,json
# Bảng kết quả từng test (tên đầy đủ) cho công cụ tự kiểm G1, CI chép vào ci-results/ket-qua/web
python3 tools/tu-kiem/gon_ket_qua.py --vitest ket-qua-tho/vitest.json --playwright ket-qua-tho/playwright.json > ket-qua/ket-qua.tsv || true
echo
echo "KẾT QUẢ: $((total - ${#fails[@]}))/${total} bước đạt"
for f in "${fails[@]}"; do echo "✘ lỗi: $f"; done
[ ${#fails[@]} -eq 0 ]
