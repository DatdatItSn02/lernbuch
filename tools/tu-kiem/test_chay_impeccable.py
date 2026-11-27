"""Test chay_impeccable.py bằng một impeccable giả: quét đủ hai cỡ màn hình trên bản chép DOM trong repo, đếm lỗi và lưu ý
riêng; bộ dò hỏng hay không có DOM thì báo lỗi, mã thoát 1.

Chạy: python3 -m unittest tools/tu-kiem/test_chay_impeccable.py
"""
import contextlib
import io
import json
import os
import stat
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import chay_impeccable as ci  # noqa: E402

GIA = r'''#!/usr/bin/env python3
import json, os, sys
if sys.argv[1:] == ['--version']:
    print('4.10.0'); sys.exit(0)
khung = sys.argv[sys.argv.index('--viewport') + 1]
urls = [a for a in sys.argv if a.startswith('file://')]
with open(os.environ['GIA_GHI'], 'a', encoding='utf-8') as f:
    f.write(json.dumps({'cwd': os.getcwd(), 'khung': khung, 'urls': urls}) + '\n')
che_do = os.environ.get('GIA_CHE_DO', 'sach')
if che_do == 'hong':
    print('trình duyệt không mở được', file=sys.stderr); sys.exit(1)
if che_do == 'sach':
    print('[]'); sys.exit(0)
ra = [{'antipattern': 'low-contrast', 'severity': 'warning', 'file': urls[0], 'snippet': f'3.2:1 ở {khung}', 'selector': 'input'},
      {'antipattern': 'low-contrast', 'severity': 'advisory', 'file': urls[-1], 'snippet': '4.2:1', 'selector': 'span'}]
if khung == '390x844':
    ra.append({'antipattern': 'tiny-text', 'severity': 'warning', 'file': urls[0], 'snippet': '9px', 'selector': 'small'})
print(json.dumps(ra)); sys.exit(2)
'''


class ChayImpeccable(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp())
        self.dom = self.tmp / 'ci' / 'dom'
        self.dom.mkdir(parents=True)
        for ten in ('app-hom-nay-light.html', 'quan-tri-the-dark.html'):
            (self.dom / ten).write_text('<!DOCTYPE html><html><body><h1>x</h1></body></html>', encoding='utf-8')
        (self.dom / 'css-abc.css').write_text('body{}', encoding='utf-8')
        self.goc = self.tmp / 'repo'
        (self.goc / '.impeccable').mkdir(parents=True)
        (self.goc / '.impeccable/config.json').write_text(json.dumps({'detector': {'ignoreValues': [
            {'rule': 'nested-cards', 'value': '*', 'files': ['ket-qua/dom/quan-tri-*.html'], 'reason': 'Filament'}]}}), encoding='utf-8')
        self.gia = self.tmp / 'impeccable'
        self.gia.write_text(GIA, encoding='utf-8')
        self.gia.chmod(self.gia.stat().st_mode | stat.S_IEXEC)
        self.ghi = self.tmp / 'goi.jsonl'
        os.environ['GIA_GHI'] = str(self.ghi)
        self.addCleanup(os.environ.pop, 'GIA_CHE_DO', None)

    def chay(self, che_do='sach', dom=None):
        os.environ['GIA_CHE_DO'] = che_do
        out = self.tmp / 'kq.json'
        err = io.StringIO()
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(err):
            code = ci.main(['--dom', str(dom or self.dom), '--impeccable', str(self.gia), '--goc', str(self.goc),
                            '--nguon', 'ci-results run 1', '--out', str(out)])
        return code, (json.loads(out.read_text(encoding='utf-8')) if out.exists() else None), err.getvalue()

    def goi(self):
        return [json.loads(d) for d in self.ghi.read_text(encoding='utf-8').splitlines()]

    def test_sach_thi_ma_thoat_0_va_quet_ban_chep_trong_repo_o_hai_co_man_hinh(self):
        code, kq, _ = self.chay()
        self.assertEqual(code, 0)
        self.assertEqual((kq['loi'], kq['luu_y'], kq['so_trang'], kq['impeccable']), (0, 0, 2, '4.10.0'))
        self.assertEqual(kq['nguon_dom'], 'ci-results run 1')
        self.assertEqual(kq['bo_qua_co_ly_do'][0]['reason'], 'Filament')
        goi = self.goi()
        self.assertEqual([g['khung'] for g in goi], ['1280x800', '390x844'])
        for g in goi:
            # Chạy ở gốc repo, quét bản chép trong ket-qua/dom (bỏ qua theo đường dẫn mới áp dụng); CSS được chép kèm
            self.assertEqual(Path(g['cwd']).resolve(), self.goc.resolve())
            self.assertEqual(len(g['urls']), 2)
            self.assertTrue(all('/ket-qua/dom/' in u for u in g['urls']))
        self.assertTrue((self.goc / 'ket-qua/dom/css-abc.css').is_file())

    def test_con_phat_hien_thi_ma_thoat_2_dem_loi_rieng_luu_y(self):
        code, kq, _ = self.chay('loi')
        self.assertEqual(code, 2)
        self.assertEqual((kq['loi'], kq['luu_y']), (3, 2))
        mt, dt = kq['theo_co_man_hinh']['may-tinh'], kq['theo_co_man_hinh']['dien-thoai']
        self.assertEqual((mt['loi'], mt['theo_quy_tac']), (1, {'low-contrast': 1}))
        self.assertEqual((dt['loi'], dt['theo_quy_tac']), (2, {'low-contrast': 1, 'tiny-text': 1}))
        self.assertEqual(mt['chi_tiet'][0], {'trang': 'app-hom-nay-light.html', 'quy_tac': 'low-contrast', 'muc': 'warning',
                                             'chi_tiet': '3.2:1 ở 1280x800', 'cho': 'input'})
        self.assertEqual(mt['chi_tiet'][1]['muc'], 'advisory')

    def test_bo_do_hong_thi_ma_thoat_1_khong_ghi_ket_qua(self):
        code, kq, err = self.chay('hong')
        self.assertEqual(code, 1)
        self.assertIsNone(kq)
        self.assertIn('trình duyệt không mở được', err)

    def test_khong_co_trang_dom_thi_ma_thoat_1(self):
        trong = self.tmp / 'trong'
        trong.mkdir()
        code, kq, err = self.chay(dom=trong)
        self.assertEqual(code, 1)
        self.assertIsNone(kq)
        self.assertIn('Không có trang DOM', err)

    def test_chay_lai_thay_ban_chep_cu(self):
        (self.goc / 'ket-qua/dom').mkdir(parents=True)
        (self.goc / 'ket-qua/dom/trang-cu.html').write_text('<html></html>', encoding='utf-8')
        self.chay()
        self.assertFalse((self.goc / 'ket-qua/dom/trang-cu.html').exists())
        self.assertEqual(len(self.goi()[0]['urls']), 2)


if __name__ == '__main__':
    unittest.main()
