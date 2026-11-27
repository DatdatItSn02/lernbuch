#!/usr/bin/env bash
# Ghi tóm tắt kết quả một job vào nhánh ci-results, để đọc được bằng git mà không cần API.
# Dùng: report.sh <tên-job> <trạng-thái> <file-log>
set -uo pipefail
name="$1"; status="$2"; log="$3"
dir=$(mktemp -d)
git -C "$dir" init -q
git -C "$dir" remote add origin "https://x-access-token:${GITHUB_TOKEN}@github.com/${GITHUB_REPOSITORY}.git"
if git -C "$dir" fetch -q --depth 1 origin ci-results 2>/dev/null; then
  git -C "$dir" checkout -q -b ci-results FETCH_HEAD
else
  git -C "$dir" checkout -q --orphan ci-results
fi
mkdir -p "$dir/runs"
out="$dir/runs/${GITHUB_RUN_NUMBER}-${GITHUB_WORKFLOW}-${name}.txt"
{
  echo "workflow: ${GITHUB_WORKFLOW}  job: ${name}  status: ${status}"
  echo "commit: ${GITHUB_SHA}  ref: ${GITHUB_REF_NAME}  run: ${GITHUB_RUN_ID}"
  echo
  grep -E '^(✔|✘|KẾT QUẢ)' "$log" 2>/dev/null || true
  echo
  echo "----- cuối log -----"
  tail -c 40000 "$log" 2>/dev/null || true
} > "$out"
cp "$out" "$dir/latest-${GITHUB_WORKFLOW}-${name}.txt"
# Ảnh kèm theo (nếu job có): REPORT_IMAGES là thư mục ảnh PNG, chép vào anh/ (ghi đè bản cũ)
if [ -n "${REPORT_IMAGES:-}" ] && compgen -G "${REPORT_IMAGES}/*.png" >/dev/null; then
  mkdir -p "$dir/anh"
  cp "${REPORT_IMAGES}"/*.png "$dir/anh/"
fi
# File kèm theo khác (nếu job có): cả thư mục REPORT_FILES chép vào thư mục REPORT_FILES_TO của nhánh (thay bản cũ)
if [ -n "${REPORT_FILES:-}" ] && [ -n "${REPORT_FILES_TO:-}" ] && [ -d "${REPORT_FILES}" ]; then
  rm -rf "${dir:?}/${REPORT_FILES_TO}" && mkdir -p "$dir/${REPORT_FILES_TO}"
  cp -r "${REPORT_FILES}"/. "$dir/${REPORT_FILES_TO}/"
fi
# Tên người ghi đặt vào cấu hình kho: lần thử lại dùng pull --rebase cũng cần (trước đây thiếu nên job đến sau mất kết quả)
git -C "$dir" config user.name lernbuch-bot
git -C "$dir" config user.email lernbuch-bot@users.noreply.github.com
git -C "$dir" add -A
git -C "$dir" commit -q -m "${GITHUB_WORKFLOW}/${name} ${status} #${GITHUB_RUN_NUMBER}"
for i in 1 2 3 4 5; do
  git -C "$dir" push -q origin HEAD:ci-results && exit 0
  sleep $((i * 2))
  git -C "$dir" pull -q --rebase origin ci-results
done
echo "::warning::không ghi được kết quả vào ci-results"
