"""Test design/icons.py: cỡ, nền trong suốt ở góc, icon maskable kín nền, lề đỏ, lỗi khi thiếu font."""

import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import icons  # noqa: E402


class IconsTest(unittest.TestCase):
    def test_sinh_du_ba_file_dung_co(self):
        with tempfile.TemporaryDirectory() as d:
            icons.main(Path(d))
            from PIL import Image

            sizes = {p.name: Image.open(p).size for p in Path(d).iterdir()}
        self.assertEqual(sizes, {"icon-192.png": (192, 192), "icon-512.png": (512, 512), "icon-maskable-512.png": (512, 512)})

    def test_icon_thuong_bo_goc_trong_suot_maskable_kin_nen(self):
        thuong = icons.icon(192).convert("RGBA")
        mask = icons.icon(512, maskable=True).convert("RGBA")
        self.assertEqual(thuong.getpixel((0, 0))[3], 0)
        self.assertEqual(mask.getpixel((0, 0)), (0x2D, 0x23, 0x64, 255))

    def test_co_le_do_va_chu_trang(self):
        img = icons.icon(512).convert("RGB")
        row = [img.getpixel((x, 40)) for x in range(512)]
        self.assertTrue(any(r > 180 and g < 110 and b < 110 for r, g, b in row), "không thấy lề đỏ")
        self.assertTrue(any(min(p) > 230 for p in img.getdata()), "không thấy chữ trắng")

    def test_thieu_file_font_thi_bao_loi(self):
        cu = icons.FONT
        icons.FONT = Path("/khong/co/font.woff2")
        try:
            with self.assertRaises(FileNotFoundError):
                icons.icon(64)
        finally:
            icons.FONT = cu


if __name__ == "__main__":
    unittest.main()
