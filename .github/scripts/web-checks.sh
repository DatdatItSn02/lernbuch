#!/usr/bin/env bash
# Kiểm tra phía giao diện. Chạy hết các bước, cuối cùng báo bước lỗi.
set -uo pipefail
fails=(); total=0
step() {
  local name="$1"; shift; total=$((total + 1))
  echo "::group::${name}"
  if "$@"; then echo "✔ ${name}"; else echo "✘ ${name}"; fails+=("${name}"); fi
  echo "::endgroup::"
}
step "Kiểm kiểu TypeScript" npm run --silent typecheck
step "ESLint" npm run --silent lint
step "Vitest" npm run --silent test
step "Build Vite" npm run --silent build
echo
echo "KẾT QUẢ: $((total - ${#fails[@]}))/${total} bước đạt"
for f in "${fails[@]}"; do echo "✘ lỗi: $f"; done
[ ${#fails[@]} -eq 0 ]
