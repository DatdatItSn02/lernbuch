"""Sinh icon app (public/icons) theo thế giới "Vở ô li và hộp thẻ": nền mực tím đậm, lề đỏ, chữ L trắng.

Chạy: python3 design/icons.py   (cần Pillow, fontTools, brotli; font lấy từ node_modules/@fontsource/be-vietnam-pro)
"""

import io
import sys
from pathlib import Path

from fontTools.ttLib import TTFont
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent.parent
FONT = ROOT / "node_modules/@fontsource/be-vietnam-pro/files/be-vietnam-pro-latin-800-normal.woff2"
BOX = "#2d2364"  # --color-box (sáng)
PEN = "#e0473f"  # bút đỏ, sáng hơn --color-pen một chút để đọc được trên nền đậm
WHITE = "#f6f4ff"


def font(px: int) -> ImageFont.FreeTypeFont:
    tt = TTFont(str(FONT))
    tt.flavor = None
    buf = io.BytesIO()
    tt.save(buf)
    buf.seek(0)
    return ImageFont.truetype(buf, px)


def icon(size: int, maskable: bool = False) -> Image.Image:
    # Vẽ ở 4 lần cỡ rồi thu nhỏ để nét mịn
    s = size * 4
    img = Image.new("RGB", (s, s), BOX) if maskable else Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if not maskable:
        d.rounded_rectangle([0, 0, s - 1, s - 1], radius=round(s * 0.16), fill=BOX)
    safe = 0.8 if maskable else 1.0  # icon maskable: nội dung nằm trong vùng an toàn 80%
    x_line = round(s * (0.5 - 0.27 * safe))
    w_line = max(2, round(s * 0.022))
    d.rectangle([x_line, 0, x_line + w_line, s], fill=PEN)
    f = font(round(s * 0.62 * safe))
    box = d.textbbox((0, 0), "L", font=f)
    w, h = box[2] - box[0], box[3] - box[1]
    d.text(((s - w) / 2 - box[0] + s * 0.04 * safe, (s - h) / 2 - box[1]), "L", font=f, fill=WHITE)
    return img.resize((size, size), Image.LANCZOS)


def main(out: Path = ROOT / "public/icons") -> None:
    out.mkdir(parents=True, exist_ok=True)
    icon(192).save(out / "icon-192.png")
    icon(512).save(out / "icon-512.png")
    icon(512, maskable=True).save(out / "icon-maskable-512.png")


if __name__ == "__main__":
    main(Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / "public/icons")
