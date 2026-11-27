"""Test gon_ket_qua.py: đọc JUnit của Pest, JSON của Vitest và Playwright; file thiếu thì bỏ qua, file hỏng thì báo lỗi."""
import contextlib
import io
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import gon_ket_qua as g  # noqa: E402

JUNIT = """<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Tests\\Feature\\HocApiTest" file="/repo/tests/Feature/HocApiTest.php" tests="3">
    <testcase name="it mục 4: gửi một lô hai lần thì ghi một lần" file="/repo/tests/Feature/HocApiTest.php" line="10"/>
    <testcase name="chưa đăng nhập thì 401" file="/repo/tests/Feature/HocApiTest.php::chưa đăng nhập thì 401" line="20">
      <failure type="AssertionFailedError">Expected 401</failure>
    </testcase>
    <testcase name="it bỏ qua
      tạm" file="/repo/tests/Feature/HocApiTest.php"><skipped/></testcase>
  </testsuite>
</testsuites>
"""

VITEST = {
    'testResults': [
        {
            'name': '/repo/resources/js/app/lib/srs.test.ts',
            'assertionResults': [
                {'ancestorTitles': ['Lặp lại ngắt quãng', 'Quên'], 'title': 'mức 1 về 1', 'status': 'passed'},
                {'ancestorTitles': [], 'title': 'đổi giờ mùa hè', 'status': 'failed'},
                {'ancestorTitles': ['x'], 'title': 'chưa làm', 'status': 'todo'},
            ],
        }
    ]
}

PLAYWRIGHT = {
    'suites': [
        {
            'title': 'pwa.spec.ts',
            'file': 'pwa.spec.ts',
            'specs': [{'title': 'không có describe', 'file': 'pwa.spec.ts', 'tests': [{'projectName': 'may-tinh', 'status': 'expected'}]}],
            'suites': [
                {
                    'title': 'cài app và học khi mất mạng',
                    'file': 'pwa.spec.ts',
                    'specs': [
                        {
                            'title': 'đủ điều kiện cài',
                            'file': 'pwa.spec.ts',
                            'tests': [{'projectName': 'may-tinh', 'status': 'expected'}, {'projectName': 'dien-thoai', 'status': 'unexpected'}],
                        }
                    ],
                }
            ],
        }
    ]
}


class GonKetQua(unittest.TestCase):
    def setUp(self):
        self.dir = Path(tempfile.mkdtemp())

    def ghi(self, ten, noi_dung):
        p = self.dir / ten
        p.write_text(noi_dung if isinstance(noi_dung, str) else json.dumps(noi_dung, ensure_ascii=False), encoding='utf-8')
        return str(p)

    def chay(self, *args):
        out, err = io.StringIO(), io.StringIO()
        with contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
            code = g.main(list(args))
        return code, [d.split('\t') for d in out.getvalue().splitlines()], err.getvalue()

    def test_pest_junit_bo_chu_it_tuong_doi_file_va_trang_thai(self):
        code, dong, _ = self.chay('--junit', self.ghi('pest.xml', JUNIT), '--goc', '/repo')
        self.assertEqual(code, 0)
        self.assertEqual(dong, [
            ['dat', 'pest', '-', 'tests/Feature/HocApiTest.php', 'mục 4: gửi một lô hai lần thì ghi một lần'],
            ['hong', 'pest', '-', 'tests/Feature/HocApiTest.php', 'chưa đăng nhập thì 401'],
            ['bo_qua', 'pest', '-', 'tests/Feature/HocApiTest.php', 'bỏ qua tạm'],
        ])

    def test_vitest_noi_describe_va_ten(self):
        _, dong, _ = self.chay('--vitest', self.ghi('vitest.json', VITEST), '--goc', '/repo')
        self.assertEqual([d[0] for d in dong], ['dat', 'hong', 'bo_qua'])
        self.assertEqual(dong[0][3:], ['resources/js/app/lib/srs.test.ts', 'Lặp lại ngắt quãng › Quên › mức 1 về 1'])
        self.assertEqual(dong[1][4], 'đổi giờ mùa hè')

    def test_playwright_moi_co_man_hinh_mot_dong_ten_file_khong_vao_ten_test(self):
        _, dong, _ = self.chay('--playwright', self.ghi('playwright.json', PLAYWRIGHT))
        self.assertEqual(dong, [
            ['dat', 'playwright', 'may-tinh', 'tests/e2e/pwa.spec.ts', 'không có describe'],
            ['dat', 'playwright', 'may-tinh', 'tests/e2e/pwa.spec.ts', 'cài app và học khi mất mạng › đủ điều kiện cài'],
            ['hong', 'playwright', 'dien-thoai', 'tests/e2e/pwa.spec.ts', 'cài app và học khi mất mạng › đủ điều kiện cài'],
        ])

    def test_file_thieu_thi_bo_qua_file_hong_thi_bao_loi_ma_thoat_1_file_khac_van_ghi(self):
        code, dong, err = self.chay(
            '--junit', str(self.dir / 'khong-co.xml'),
            '--vitest', self.ghi('hong.json', '{"testResults": [ {'),
            '--playwright', self.ghi('playwright.json', PLAYWRIGHT),
        )
        self.assertEqual(code, 1)
        self.assertIn('Không đọc được', err)
        self.assertEqual(len(dong), 3)


if __name__ == '__main__':
    unittest.main()
