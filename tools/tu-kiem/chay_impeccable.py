"""Chạy bộ dò Impeccable trên DOM đã vẽ của mọi màn (mục 14 tự kiểm G1) ở cỡ máy tính và điện thoại, ghi tóm tắt JSON.

DOM do Playwright lưu khi commit có "[anh]" (tests/e2e/phien.ts), CI chép lên ci-results ket-qua/web/dom. Mỗi trang được
mở bằng trình duyệt thật (Impeccable quét URL file://), nên quy tắc cần trang đã vẽ (tương phản, chữ nhỏ...) đều chạy.
Thư mục DOM được chép vào ket-qua/dom của repo để các bỏ qua có lý do trong .impeccable/config.json (theo đường dẫn) áp dụng.

Dùng:
  python3 tools/tu-kiem/chay_impeccable.py --dom /tmp/ci-results/ket-qua/web/dom --impeccable <đường dẫn impeccable> \\
      --out docs/impeccable-g1.json
Mã thoát: 0 không còn phát hiện chính (chỉ có lưu ý), 1 lỗi khi chạy, 2 còn phát hiện chính.
"""
import argparse
import collections
import json
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

GOC = Path(__file__).resolve().parents[2]
KHUNG = {'may-tinh': '1280x800', 'dien-thoai': '390x844'}


def quet(impeccable, trang, khung, cwd):
    """Một lần quét mọi trang ở một cỡ màn hình: danh sách phát hiện. Lỗi khi chạy thì RuntimeError."""
    lenh = [impeccable, 'detect', '--json', '--viewport', khung, *[p.resolve().as_uri() for p in trang]]
    kq = subprocess.run(lenh, cwd=cwd, capture_output=True, text=True, encoding='utf-8')
    if kq.returncode not in (0, 2):
        raise RuntimeError(f'impeccable thoát mã {kq.returncode}: {kq.stderr.strip()[-500:]}')
    try:
        return json.loads(kq.stdout or '[]')
    except json.JSONDecodeError as e:
        raise RuntimeError(f'impeccable không trả JSON: {e}') from e


def tom_tat(phat_hien):
    """Phát hiện chính (lỗi) và lưu ý (advisory), đếm theo quy tắc, kèm từng dòng gọn."""
    chinh = [f for f in phat_hien if f.get('severity') != 'advisory']
    luu_y = [f for f in phat_hien if f.get('severity') == 'advisory']
    gon = lambda f: {'trang': Path(f.get('file', '')).name, 'quy_tac': f.get('antipattern'), 'muc': f.get('severity'),
                     'chi_tiet': f.get('snippet', ''), 'cho': f.get('selector', '')}
    return {
        'loi': len(chinh),
        'luu_y': len(luu_y),
        'theo_quy_tac': dict(collections.Counter(f.get('antipattern') for f in chinh)),
        'chi_tiet': [gon(f) for f in chinh + luu_y],
    }


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--dom', required=True, help='thư mục DOM (ci-results ket-qua/web/dom)')
    ap.add_argument('--impeccable', default='impeccable', help='lệnh impeccable')
    ap.add_argument('--goc', default=str(GOC), help='gốc repo (nơi có .impeccable/config.json)')
    ap.add_argument('--nguon', default='', help='ghi chú nguồn DOM (commit, run) vào kết quả')
    ap.add_argument('--out', help='ghi tóm tắt JSON ra file này')
    a = ap.parse_args(argv)

    dom, goc = Path(a.dom), Path(a.goc)
    if not dom.is_dir() or not any(dom.glob('*.html')):
        print(f'Không có trang DOM nào ở {dom}', file=sys.stderr)
        return 1
    dich = goc / 'ket-qua' / 'dom'
    shutil.rmtree(dich, ignore_errors=True)
    shutil.copytree(dom, dich)
    trang = sorted(dich.glob('*.html'))

    ket_qua = {}
    try:
        for ten, khung in KHUNG.items():
            ket_qua[ten] = {'khung': khung, **tom_tat(quet(a.impeccable, trang, khung, goc))}
    except (RuntimeError, OSError) as e:
        print(f'Lỗi khi chạy bộ dò: {e}', file=sys.stderr)
        return 1

    phien_ban = subprocess.run([a.impeccable, '--version'], capture_output=True, text=True).stdout.strip()
    tong = {
        'ten': 'Bộ dò Impeccable trên DOM đã vẽ của mọi màn (mục 14 tự kiểm G1)',
        'luc': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'impeccable': phien_ban,
        'nguon_dom': a.nguon or str(dom),
        'so_trang': len(trang),
        'bo_qua_co_ly_do': json.loads((goc / '.impeccable/config.json').read_text(encoding='utf-8')).get('detector', {}).get('ignoreValues', [])
        if (goc / '.impeccable/config.json').is_file() else [],
        'loi': sum(k['loi'] for k in ket_qua.values()),
        'luu_y': sum(k['luu_y'] for k in ket_qua.values()),
        'theo_co_man_hinh': ket_qua,
    }
    if a.out:
        Path(a.out).write_text(json.dumps(tong, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    for ten, k in ket_qua.items():
        print(f'{ten} ({k["khung"]}): {k["loi"]} lỗi, {k["luu_y"]} lưu ý' + (f' — {k["theo_quy_tac"]}' if k['theo_quy_tac'] else ''))
    print(f'{len(trang)} trang; tổng {tong["loi"]} lỗi, {tong["luu_y"]} lưu ý')
    return 2 if tong['loi'] else 0


if __name__ == '__main__':
    sys.exit(main())
