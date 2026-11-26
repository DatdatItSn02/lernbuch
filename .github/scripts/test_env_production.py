"""Test env-production.py: điền đúng mật khẩu, ký tự đặc biệt không làm hỏng .env.

Chạy: python3 -m unittest .github/scripts/test_env_production.py
"""
import importlib.util
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('env_production', HERE / 'env-production.py')
env_production = importlib.util.module_from_spec(spec)
spec.loader.exec_module(env_production)

MAU = 'DB_PASSWORD=__DB_PASSWORD__\nMAIL_PASSWORD=__MAIL_PASSWORD__\n'


class EnvProductionTest(unittest.TestCase):
    def test_dien_ca_hai_mat_khau(self):
        out = env_production.render(MAU, {'DB_PASSWORD': 'abc123', 'MAIL_PASSWORD': 'thu#Mat khau$1'})
        self.assertEqual(out, "DB_PASSWORD=abc123\nMAIL_PASSWORD='thu#Mat khau$1'\n")

    def test_mat_khau_co_nhay_don_thi_dung_nhay_kep_va_thoat_ky_tu(self):
        out = env_production.render(MAU, {'DB_PASSWORD': 'x', 'MAIL_PASSWORD': 'it\'s "a" $b\\c'})
        self.assertIn('MAIL_PASSWORD="it\'s \\"a\\" \\$b\\\\c"', out)

    def test_thieu_bi_mat_thi_de_trong_khong_de_lai_cho_giu_cho(self):
        out = env_production.render(MAU, {})
        self.assertNotIn('__', out)
        self.assertIn('MAIL_PASSWORD=\n', out)

    def test_mau_that_co_du_cho_dien(self):
        mau = (HERE.parents[1] / '.env.production.example').read_text(encoding='utf-8')
        self.assertIn('MAIL_PASSWORD=__MAIL_PASSWORD__', mau)
        self.assertIn('DB_PASSWORD=__DB_PASSWORD__', mau)
        out = env_production.render(mau, {'DB_PASSWORD': 'd', 'MAIL_PASSWORD': 'm'})
        self.assertNotIn('__DB_PASSWORD__', out)
        self.assertNotIn('__MAIL_PASSWORD__', out)


if __name__ == '__main__':
    unittest.main()
