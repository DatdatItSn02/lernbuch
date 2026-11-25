"""Test cho palette_check.py: đọc đúng tokens.css, bắt được cặp màu thiếu tương phản. Chạy: python -m unittest design/test_palette_check.py"""
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import palette_check  # noqa: E402

TOKENS = (HERE / "tokens.css").read_text(encoding="utf-8")


class PaletteCheckTest(unittest.TestCase):
    def test_doc_du_mau_sang_toi_va_mua(self):
        light, dark, seasons = palette_check.parse_tokens(TOKENS)
        self.assertEqual(len(light), 28)
        self.assertEqual(set(light), set(dark), "bản tối phải ghi đè đủ mọi màu của bản sáng")
        self.assertEqual(sorted(seasons), ["autumn", "spring", "summer", "winter"])
        self.assertEqual(light["paper"], (0.975, 0.012, 85.0))
        self.assertEqual(dark["paper"], (0.19, 0.012, 70.0))

    def test_bang_mau_hien_tai_dat_AA(self):
        report = palette_check.check(TOKENS)
        self.assertEqual(report["failures"], [])
        self.assertEqual(report["light"]["hex"]["leaf"], "#21763c")
        self.assertEqual(len(report["light"]["pairs"]), len(palette_check.PAIRS))

    def test_co_y_lam_nhat_chu_phu_thi_bao_loi_dung_cap(self):
        bad = TOKENS.replace("--color-ink-2: oklch(47% 0.025 60);", "--color-ink-2: oklch(75% 0.025 60);", 1)
        self.assertNotEqual(bad, TOKENS)
        failures = palette_check.check(bad)["failures"]
        self.assertTrue(failures)
        self.assertTrue(all(f.startswith("light: ink-2 trên") for f in failures), failures)

    def test_thieu_mau_thi_bao_loi(self):
        bad = TOKENS.replace("--color-focus: oklch(52% 0.15 255);", "", 1)
        self.assertIn("light: thiếu màu focus trong tokens.css", palette_check.check(bad)["failures"])

    def test_mau_ngoai_gamut_thi_bao_loi(self):
        bad = TOKENS.replace("--color-leaf: oklch(50% 0.12 150);", "--color-leaf: oklch(50% 0.35 150);", 1)
        self.assertTrue(any("ngoài gamut" in f for f in palette_check.check(bad)["failures"]))

    def test_chay_dong_lenh_ghi_json_va_ma_thoat(self):
        with tempfile.TemporaryDirectory() as d:
            out = Path(d) / "kq.json"
            ok = subprocess.run([sys.executable, str(HERE / "palette_check.py"), "--out", str(out)], capture_output=True, text=True)
            self.assertEqual(ok.returncode, 0, ok.stdout + ok.stderr)
            self.assertEqual(json.loads(out.read_text(encoding="utf-8"))["failures"], [])
            bad_tokens = Path(d) / "tokens.css"
            bad_tokens.write_text(TOKENS.replace("--color-ink-2: oklch(47% 0.025 60);", "--color-ink-2: oklch(75% 0.025 60);", 1), encoding="utf-8")
            fail = subprocess.run([sys.executable, str(HERE / "palette_check.py"), "--tokens", str(bad_tokens), "--out", str(out)], capture_output=True, text=True)
            self.assertEqual(fail.returncode, 1)
            self.assertIn("ink-2", fail.stdout)


if __name__ == "__main__":
    unittest.main()
