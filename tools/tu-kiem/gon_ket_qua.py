"""Gọn kết quả test thành bảng một dòng mỗi test, để công cụ tự kiểm G1 (tu_kiem_g1.py) đọc ở nhánh ci-results.

Log CI ở ci-results chỉ giữ 40 KB cuối, và tên test dài bị cắt theo bề rộng cửa sổ; bảng này giữ đủ mọi test, tên đầy đủ.

Mỗi dòng: trạng thái <TAB> bộ <TAB> cỡ màn hình <TAB> file <TAB> tên
  trạng thái  dat, hong, bo_qua
  bộ          pest, vitest, playwright
  cỡ màn hình tên dự án Playwright (may-tinh, dien-thoai); bộ khác ghi "-"
  file        đường dẫn tính từ gốc repo
  tên         các describe và tên test nối bằng " › " (Pest: tên test, bỏ chữ "it " ở đầu)

Dùng:
  python3 tools/tu-kiem/gon_ket_qua.py --junit ket-qua-tho/pest.xml --vitest ket-qua-tho/vitest.json \\
      --playwright ket-qua-tho/playwright.json > ket-qua/ket-qua.tsv
File không có (bước chưa chạy) thì bỏ qua; file hỏng thì báo ra stderr, mã thoát 1, các file khác vẫn ghi.
"""
import argparse
import json
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

DAT, HONG, BO_QUA = 'dat', 'hong', 'bo_qua'
NOI = ' › '


def _gon(chu):
    """Tên trên một dòng: bỏ tab, xuống dòng (TSV)."""
    return re.sub(r'\s+', ' ', str(chu)).strip()


def _tuong_doi(duong, goc):
    duong = str(duong or '')
    if goc and duong.startswith(goc):
        duong = duong[len(goc):].lstrip('/')
    return duong


def junit(p, goc=''):
    """JUnit XML của Pest (php artisan test --log-junit=...)."""
    ra = []
    for tc in ET.parse(p).getroot().iter('testcase'):
        ten = _gon(tc.get('name', ''))
        ten = ten[3:] if ten.startswith('it ') else ten
        if any(tc.find(k) is not None for k in ('failure', 'error')):
            tt = HONG
        elif tc.find('skipped') is not None:
            tt = BO_QUA
        else:
            tt = DAT
        # Pest ghi file dạng "tests/Feature/XTest.php::it tên test": chỉ giữ đường dẫn
        file = (tc.get('file') or '').split('::')[0]
        ra.append((tt, 'pest', '-', _tuong_doi(file, goc), ten))
    return ra


def vitest(p, goc=''):
    """JSON của Vitest (--reporter=json), dạng như Jest."""
    d = json.loads(Path(p).read_text(encoding='utf-8'))
    ra = []
    for f in d.get('testResults', []):
        for a in f.get('assertionResults', []):
            st = a.get('status')
            tt = DAT if st == 'passed' else (HONG if st == 'failed' else BO_QUA)
            ten = NOI.join(_gon(x) for x in [*a.get('ancestorTitles', []), a.get('title', '')] if x)
            ra.append((tt, 'vitest', '-', _tuong_doi(f.get('name', ''), goc), ten))
    return ra


def playwright(p, thu_muc='tests/e2e'):
    """JSON của Playwright (reporter json): mỗi test mỗi cỡ màn hình một dòng."""
    d = json.loads(Path(p).read_text(encoding='utf-8'))
    ra = []

    def di(suite, cha):
        tieu_de = suite.get('title', '')
        # Suite ngoài cùng là file: tên file không thuộc tên test
        la_file = suite.get('file') and tieu_de == suite.get('file')
        ten_cha = cha if (la_file or not tieu_de) else [*cha, tieu_de]
        for spec in suite.get('specs', []):
            for t in spec.get('tests', []):
                st = t.get('status')
                tt = DAT if st in ('expected', 'flaky') else (BO_QUA if st == 'skipped' else HONG)
                file = f"{thu_muc}/{spec.get('file', suite.get('file', ''))}"
                ra.append((tt, 'playwright', t.get('projectName') or '-', file, NOI.join(_gon(x) for x in [*ten_cha, spec.get('title', '')])))
        for con in suite.get('suites', []):
            di(con, ten_cha)

    for s in d.get('suites', []):
        di(s, [])
    return ra


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--junit', action='append', default=[], help='JUnit XML của Pest (lặp lại được)')
    ap.add_argument('--vitest', action='append', default=[])
    ap.add_argument('--playwright', action='append', default=[])
    ap.add_argument('--goc', default='', help='gốc repo trong đường dẫn file (bỏ đi cho gọn), mặc định thư mục hiện tại')
    a = ap.parse_args(argv)
    goc = a.goc or str(Path.cwd())
    hong = False
    dong = []
    for doc, ds in ((junit, a.junit), (vitest, a.vitest), (playwright, a.playwright)):
        for p in ds:
            if not Path(p).is_file():
                continue
            try:
                dong += doc(p, goc) if doc is not playwright else doc(p)
            except (ET.ParseError, json.JSONDecodeError, OSError, AttributeError, TypeError) as e:
                print(f'Không đọc được {p}: {e}', file=sys.stderr)
                hong = True
    for d in dong:
        print('\t'.join(d))
    return 1 if hong else 0


if __name__ == '__main__':
    sys.exit(main())
