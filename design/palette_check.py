"""Kiểm bảng màu trong design/tokens.css: đổi OKLCH sang sRGB, kiểm gamut, tính tương phản WCAG 2.

Đọc thẳng tokens.css (khối @theme cho bản sáng, [data-theme="dark"] cho bản tối, [data-season=…] cho màu mùa),
nên bảng màu và phép kiểm không lệch nhau. Ghi kết quả ra design/palette-check.json; có cặp không đạt thì thoát mã 1.

Chạy: python design/palette_check.py [--tokens đường/dẫn/tokens.css] [--out đường/dẫn/kết-quả.json]
"""
import argparse
import json
import math
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent

# (chữ hoặc hình, nền, mức tối thiểu): 4.5 cho chữ thường, 3.0 cho chữ lớn, viền, biểu tượng, vòng focus
PAIRS = [
    ("ink", "paper", 4.5), ("ink", "sheet", 4.5), ("ink-2", "sheet", 4.5), ("ink-2", "sunken", 4.5),
    ("ink-2", "paper", 4.5), ("ink-3", "sheet", 3.0),
    ("on-leaf", "leaf", 4.5), ("on-leaf", "leaf-strong", 4.5), ("leaf", "sheet", 4.5), ("ink", "leaf-tint", 4.5),
    ("danger", "sheet", 4.5), ("ink", "danger-tint", 4.5), ("warning-ink", "sheet", 4.5), ("info", "sheet", 4.5),
    ("focus", "sheet", 3.0), ("focus", "paper", 3.0), ("line-strong", "sheet", 3.0),
    ("der", "sheet", 4.5), ("die", "sheet", 4.5), ("das", "sheet", 4.5), ("plural", "sheet", 4.5),
    ("level-5", "sheet", 3.0), ("level-4", "sheet", 3.0),
]

OKLCH = re.compile(r"--color-([\w-]+)\s*:\s*oklch\(\s*([\d.]+)%\s+([\d.]+)\s+([\d.]+)\s*\)")
SEASON = re.compile(r'\[data-season="(\w+)"\]\s*\{\s*--season\s*:\s*oklch\(\s*([\d.]+)%\s+([\d.]+)\s+([\d.]+)\s*\)')


def block(css: str, header: str) -> str:
    """Nội dung khối CSS bắt đầu bằng `header {` (không có khối lồng)."""
    start = css.find(header)
    if start < 0:
        raise ValueError(f"không thấy khối {header}")
    open_at = css.index("{", start)
    return css[open_at + 1: css.index("}", open_at)]


def parse_tokens(css: str):
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    def colors(text):
        return {m[0]: (float(m[1]) / 100, float(m[2]), float(m[3])) for m in OKLCH.findall(text)}
    light = colors(block(css, "@theme"))
    dark = {**light, **colors(block(css, '[data-theme="dark"]'))}
    seasons = {m[0]: (float(m[1]) / 100, float(m[2]), float(m[3])) for m in SEASON.findall(css)}
    return light, dark, seasons


def oklch_to_linear_srgb(L, C, h):
    a, b = C * math.cos(math.radians(h)), C * math.sin(math.radians(h))
    l_ = L + 0.3963377774 * a + 0.2158037573 * b
    m_ = L - 0.1055613458 * a - 0.0638541728 * b
    s_ = L - 0.0894841775 * a - 1.2914855480 * b
    l, m, s = l_ ** 3, m_ ** 3, s_ ** 3
    return (4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
            -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
            -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s)


def to_hex(lin):
    def enc(x):
        x = min(1.0, max(0.0, x))
        return 12.92 * x if x <= 0.0031308 else 1.055 * x ** (1 / 2.4) - 0.055
    return "#" + "".join(f"{round(enc(x) * 255):02x}" for x in lin)


def lum(lin):
    r, g, b = (min(1.0, max(0.0, x)) for x in lin)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def contrast(a, b):
    la, lb = sorted((lum(a), lum(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)


def check(css: str):
    light, dark, seasons = parse_tokens(css)
    report, bad = {}, []
    for theme, pal in (("light", {**light, **seasons}), ("dark", dark)):
        lin = {k: oklch_to_linear_srgb(*v) for k, v in pal.items()}
        out_gamut = sorted(k for k, v in lin.items() if any(x < -0.001 or x > 1.001 for x in v))
        pairs = []
        for fg, bg, need in PAIRS:
            if fg not in lin or bg not in lin:
                bad.append(f"{theme}: thiếu màu {fg if fg not in lin else bg} trong tokens.css")
                continue
            r = contrast(lin[fg], lin[bg])
            pairs.append({"fg": fg, "bg": bg, "ratio": round(r, 2), "need": need, "ok": r >= need})
            if r < need:
                bad.append(f"{theme}: {fg} trên {bg} = {r:.2f} < {need}")
        if out_gamut:
            bad.append(f"{theme}: ngoài gamut sRGB {out_gamut}")
        report[theme] = {
            "hex": {k: to_hex(v) for k, v in lin.items()},
            "oklch": {k: f"oklch({v[0] * 100:g}% {v[1]:g} {v[2]:g})" for k, v in pal.items()},
            "out_of_gamut": out_gamut,
            "pairs": pairs,
        }
    report["failures"] = bad
    return report


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument("--tokens", default=str(HERE / "tokens.css"))
    ap.add_argument("--out", default=str(HERE / "palette-check.json"))
    args = ap.parse_args(argv)
    report = check(Path(args.tokens).read_text(encoding="utf-8"))
    Path(args.out).write_text(json.dumps(report, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    total = sum(len(report[t]["pairs"]) for t in ("light", "dark"))
    passed = sum(p["ok"] for t in ("light", "dark") for p in report[t]["pairs"])
    print(f"Tương phản: {passed}/{total} cặp đạt WCAG 2 AA (sáng và tối)")
    for f in report["failures"]:
        print("  ✘", f)
    return 1 if report["failures"] else 0


if __name__ == "__main__":
    sys.exit(main())
