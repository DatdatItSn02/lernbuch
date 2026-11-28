#!/usr/bin/env python3
"""Xem kết quả CI của một commit thật gọn (cho Claude: ít dòng, chỉ phần lỗi).

  python3 tools/ci/xem_ci.py                     kết quả php và web của HEAD
  python3 tools/ci/xem_ci.py --cho               chờ tới khi có kết quả của HEAD (mặc định tối đa 9 phút) rồi in
  python3 tools/ci/xem_ci.py --buoc php Larastan in log của một bước (tải log đầy đủ của job qua gh)

Nguồn: nhánh ci-results (latest-ci-php.txt, latest-ci-web.txt, ket-qua/<php|web>/ket-qua.tsv) do workflow ci ghi;
bước nào hỏng thì lấy thêm đúng phần log của bước đó (gh api). Không có gh thì dùng phần cuối log trong file tóm tắt.
Mã thoát: 0 mọi job đạt, 1 có job hỏng, 2 chưa có kết quả của commit này, 3 lỗi khi chạy (git, gh).
"""
from __future__ import annotations

import argparse
import re
import subprocess
import sys
import time

JOBS = ('php', 'web')
NHANH = 'ci-results'
MAU = re.compile(r'\x1b\[[0-9;]*m')
GIO_LOG = re.compile(r'^\d{4}-\d\d-\d\dT[\d:.]+Z ', re.M)
# Dòng mở đầu một test hỏng: ' FAILED  Tests\…' (Pest), '  1) [may-tinh] › …' (Playwright), ' FAIL  a/b.test.tsx > …' (Vitest)
DAU_HONG = re.compile(r'\s+FAILED\s+\S|\s+\d+\) \[|\s*FAIL\s+\S+\.(?:test|spec)\.[tj]sx?\b')


def bo_mau(chu: str) -> str:
    """Bỏ mã màu ANSI và giờ đầu dòng của log GitHub."""
    return GIO_LOG.sub('', MAU.sub('', chu))


def doc_dau(chu: str) -> dict[str, str] | None:
    """Hai dòng đầu của file tóm tắt: job, status, commit, ref, run. Sai dạng thì None."""
    dong = chu.splitlines()[:2]
    if len(dong) < 2:
        return None
    a = re.match(r'workflow: (\S+)\s+job: (\S+)\s+status: (\S+)', dong[0])
    b = re.match(r'commit: ([0-9a-f]{7,40})\s+ref: (\S+)\s+run: (\d+)', dong[1])
    if not a or not b:
        return None
    return {'job': a.group(2), 'status': a.group(3), 'commit': b.group(1), 'ref': b.group(2), 'run': b.group(3)}


def cac_buoc(chu: str) -> tuple[list[str], str | None]:
    """Tên các bước hỏng (dòng '✘ lỗi: …') và dòng 'KẾT QUẢ: a/b bước đạt' đầu tiên."""
    hong, ket_qua = [], None
    for d in bo_mau(chu).splitlines():
        if d.startswith('✘ lỗi: ') and d[7:] not in hong:
            hong.append(d[7:])
        elif ket_qua is None and d.startswith('KẾT QUẢ:'):
            ket_qua = d[9:].strip()
    return hong, ket_qua


def ten_ngan(buoc: str) -> str:
    """Phần đầu của tên bước, đủ để tìm lại: 'Pest (database …)' → 'Pest'."""
    return re.split(r'[:(]', buoc)[0].strip()


def khoi_hong(chu: str, toi_da: int = 3, dong: int = 14) -> list[str]:
    """Các khối báo test hỏng (Pest, Playwright, Vitest), mỗi khối `dong` dòng, nhiều nhất `toi_da` khối."""
    ds = bo_mau(chu).splitlines()
    ra: list[str] = []
    for i, d in enumerate(ds):
        if DAU_HONG.match(d):
            ra.append('\n'.join(x.rstrip()[:220] for x in ds[i:i + dong]))
            if len(ra) >= toi_da:
                break
    return ra


def hong_tsv(chu: str) -> list[tuple[str, str, str]]:
    """Dòng không đạt trong ket-qua.tsv: (công cụ, file, tên test). Dòng bỏ qua (bo_qua) không tính."""
    ra = []
    for d in chu.splitlines():
        o = d.split('\t')
        if len(o) >= 5 and o[0] == 'hong':
            ra.append((o[1], o[3], o[4]))
    return ra


def cat_buoc(log: str, ten: str, dong: int = 80) -> str | None:
    """Phần log của một bước (từ ##[group] tới ##[endgroup]): tên bắt đầu bằng `ten`, không có thì tên chứa `ten`."""
    ds = bo_mau(log).splitlines()
    nhom = [(i, d[9:]) for i, d in enumerate(ds) if d.startswith('##[group]')]
    vi_tri = next((i for i, t in nhom if t.lower().startswith(ten.lower())), None)
    if vi_tri is None:
        vi_tri = next((i for i, t in nhom if ten.lower() in t.lower()), None)
    if vi_tri is None:
        return None
    khoi = []
    for x in ds[vi_tri:]:
        khoi.append(x.rstrip()[:240])
        if x.startswith('##[endgroup]'):
            break
    if len(khoi) > dong:
        khoi = khoi[:dong] + [f'… (cắt, còn {len(khoi) - dong} dòng; thêm --dong N)']
    return '\n'.join(khoi)


def chi_tiet(khoi: str, dong: int = 30) -> str:
    """Phần đáng đọc trong log của một bước hỏng: các khối test hỏng nếu có, không thì `dong` dòng cuối (nơi công cụ in lỗi)."""
    k = khoi_hong(khoi)
    if k:
        return '\n---\n'.join(k)
    return '\n'.join(khoi.splitlines()[-dong:])


def tom_tat(job: str, tom: str | None, tsv: str | None, sha: str, log: str | None = None) -> tuple[str, int]:
    """Chữ in ra cho một job và mã: 0 đạt, 1 hỏng, 2 chưa có kết quả của commit này. `log`: log đầy đủ của job, nếu tải được."""
    dau = doc_dau(tom) if tom else None
    if dau is None:
        return f'{job}: chưa có file tóm tắt', 2
    if not sha.startswith(dau['commit']) and not dau['commit'].startswith(sha):
        return f"{job}: chưa có kết quả của {sha[:7]} (mới nhất: {dau['commit'][:7]} trên {dau['ref']})", 2
    hong, ket_qua = cac_buoc(tom or '')
    dong = [f"{job}: {dau['status']} (run {dau['run']}) {ket_qua or ''}".rstrip()]
    if dau['status'] == 'success' and not hong:
        return dong[0], 0
    test = hong_tsv(tsv or '')
    if test:
        dong.append(f'  test hỏng ({len(test)}):')
        dong += [f'    {c} {f} › {t[:150]}' for c, f, t in test[:12]]
        if len(test) > 12:
            dong.append(f'    … và {len(test) - 12} test nữa')
    for b in hong[:3]:
        dong.append(f'  ✘ {b}')
        khoi = cat_buoc(log, ten_ngan(b), 100000) if log else None
        if khoi is not None:
            dong += ['    ' + x for x in chi_tiet(khoi).splitlines()]
    if hong and log is None:
        for k in khoi_hong(tom or ''):
            dong += ['  ---'] + ['  ' + x for x in k.splitlines()]
        dong.append(f'  log một bước: python3 tools/ci/xem_ci.py --buoc {job} "{ten_ngan(hong[0])}"')
    if len(hong) > 3:
        dong += [f'  ✘ {b}' for b in hong[3:]]
    return '\n'.join(dong), 1


def chay(lenh: list[str]) -> str | None:
    """Chạy một lệnh, trả chữ in ra; lỗi thì None. Phần cuối log có thể bị cắt giữa một ký tự: byte hỏng thành dấu thay thế."""
    try:
        r = subprocess.run(lenh, capture_output=True, timeout=120)
    except (OSError, subprocess.SubprocessError):
        return None
    return r.stdout.decode('utf-8', 'replace') if r.returncode == 0 else None


def lay(duong: str) -> str | None:
    return chay(['git', 'show', f'origin/{NHANH}:{duong}'])


def tai_nhanh() -> bool:
    return chay(['git', 'fetch', '-q', 'origin', f'+refs/heads/{NHANH}:refs/remotes/origin/{NHANH}']) is not None


def kho() -> str | None:
    """owner/repo của origin."""
    url = chay(['git', 'remote', 'get-url', 'origin']) or ''
    m = re.search(r'github\.com[:/]+([^/]+/[^/\s]+?)(?:\.git)?\s*$', url)
    return m.group(1) if m else None


def log_job(job: str, run: str) -> str | None:
    """Log đầy đủ của một job trong một lần chạy (gh api); không tải được thì None."""
    ten_kho = kho()
    if ten_kho is None:
        return None
    ds = chay(['gh', 'api', f'repos/{ten_kho}/actions/runs/{run}/jobs', '-q', f'.jobs[] | select(.name|startswith("{job}")) | .id'])
    ma_job = (ds or '').split()
    return chay(['gh', 'api', f'repos/{ten_kho}/actions/jobs/{ma_job[0]}/logs']) if ma_job else None


def xem(sha: str) -> tuple[str, int]:
    ra, ma = [], 0
    for job in JOBS:
        tom, tsv = lay(f'latest-ci-{job}.txt'), lay(f'ket-qua/{job}/ket-qua.tsv')
        chu, m = tom_tat(job, tom, tsv, sha)
        if m == 1:
            dau = doc_dau(tom or '')
            chu, m = tom_tat(job, tom, tsv, sha, log_job(job, dau['run']) if dau else None)
        ra.append(chu)
        ma = max(ma, m)
    return '\n'.join(ra), ma


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description='Xem kết quả CI của một commit thật gọn.')
    p.add_argument('--commit', help='mã commit (mặc định HEAD)')
    p.add_argument('--cho', action='store_true', help='chờ tới khi có kết quả của commit này')
    p.add_argument('--toi-da', type=int, default=540, help='số giây chờ tối đa (mặc định 540)')
    p.add_argument('--buoc', nargs=2, metavar=('JOB', 'TEN'), help='in log của một bước, vd: --buoc php Larastan')
    p.add_argument('--dong', type=int, default=80, help='số dòng tối đa khi in một bước')
    a = p.parse_args(argv)

    sha = a.commit or (chay(['git', 'rev-parse', 'HEAD']) or '').strip()
    if not sha:
        print('Không đọc được mã commit (git rev-parse HEAD).')
        return 3
    if not tai_nhanh():
        print(f'Không tải được nhánh {NHANH}.')
        return 3

    if a.buoc:
        job, ten = a.buoc
        dau = doc_dau(lay(f'latest-ci-{job}.txt') or '')
        log = log_job(job, dau['run']) if dau else None
        if log is None:
            print(f'Không tải được log của job {job}.')
            return 3
        khoi = cat_buoc(log, ten, a.dong)
        print(khoi if khoi is not None else f'Không có bước nào tên chứa "{ten}" trong job {job}.')
        return 0 if khoi is not None else 3

    het = time.monotonic() + (a.toi_da if a.cho else 0)
    while True:
        chu, ma = xem(sha)
        if ma != 2 or time.monotonic() >= het:
            break
        time.sleep(30)
        if not tai_nhanh():
            print(f'Không tải được nhánh {NHANH}.')
            return 3
    print(chu)
    if ma == 2 and a.cho:
        print(f'Hết {a.toi_da} giây chờ. Chạy lại lệnh để chờ tiếp.')
    return ma


if __name__ == '__main__':
    sys.exit(main())
