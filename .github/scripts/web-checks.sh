#!/usr/bin/env bash
# Kiểm tra phía giao diện. Chạy hết các bước, cuối cùng báo bước lỗi.
# Cần: vendor (composer install, theme Filament và php artisan serve dùng), node_modules, Chromium của Playwright.
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
step "Vitest" npm run --silent test
step "Build Vite (app học, trang chào, theme quản trị)" npm run --silent build
step "Tương phản màu WCAG AA (palette_check.py trên tokens.css)" python3 design/palette_check.py
step "Test palette_check (cả trường hợp lỗi)" python3 -m unittest design/test_palette_check.py
step "Playwright: font và bố cục trên máy tính, điện thoại" npx playwright test
echo
echo "KẾT QUẢ: $((total - ${#fails[@]}))/${total} bước đạt"
for f in "${fails[@]}"; do echo "✘ lỗi: $f"; done
[ ${#fails[@]} -eq 0 ]
