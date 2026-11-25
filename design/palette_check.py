"""Kiểm tra bảng màu OKLCH: đổi sang sRGB, kiểm gamut, tính độ tương phản WCAG."""
import json
import math
import sys

LIGHT = {
    "paper": (0.975, 0.012, 85), "sheet": (0.995, 0.005, 85), "sunken": (0.950, 0.016, 85),
    "line": (0.860, 0.020, 80), "line-strong": (0.640, 0.025, 75),
    "ink": (0.270, 0.025, 60), "ink-2": (0.470, 0.025, 60), "ink-3": (0.560, 0.022, 60),
    "leaf": (0.500, 0.120, 150), "leaf-strong": (0.440, 0.115, 150), "leaf-tint": (0.940, 0.040, 150),
    "on-leaf": (0.995, 0.005, 150),
    "focus": (0.520, 0.150, 255),
    "danger": (0.520, 0.170, 28), "danger-tint": (0.950, 0.024, 28),
    "warning": (0.780, 0.140, 80), "warning-ink": (0.450, 0.090, 70),
    "info": (0.520, 0.110, 245),
    "der": (0.500, 0.130, 255), "die": (0.520, 0.160, 15), "das": (0.500, 0.092, 170), "plural": (0.520, 0.110, 60),
    "lv0": (0.900, 0.010, 80), "lv1": (0.860, 0.060, 115), "lv2": (0.780, 0.090, 130),
    "lv3": (0.680, 0.110, 140), "lv4": (0.570, 0.120, 148), "lv5": (0.460, 0.110, 152),
    "spring": (0.820, 0.080, 350), "summer": (0.860, 0.130, 95), "autumn": (0.700, 0.140, 55), "winter": (0.840, 0.050, 235),
}
DARK = {
    "paper": (0.190, 0.012, 70), "sheet": (0.230, 0.014, 70), "sunken": (0.170, 0.012, 70),
    "line": (0.340, 0.015, 70), "line-strong": (0.520, 0.020, 70),
    "ink": (0.930, 0.012, 85), "ink-2": (0.760, 0.015, 85), "ink-3": (0.660, 0.015, 85),
    "leaf": (0.740, 0.130, 150), "leaf-strong": (0.800, 0.120, 150), "leaf-tint": (0.300, 0.050, 150),
    "on-leaf": (0.200, 0.030, 150),
    "focus": (0.740, 0.120, 255),
    "danger": (0.720, 0.150, 28), "danger-tint": (0.300, 0.060, 28),
    "warning": (0.800, 0.130, 80), "warning-ink": (0.850, 0.110, 85),
    "info": (0.740, 0.100, 245),
    "der": (0.760, 0.110, 255), "die": (0.760, 0.120, 15), "das": (0.770, 0.100, 170), "plural": (0.800, 0.100, 70),
    "lv0": (0.340, 0.010, 80), "lv1": (0.420, 0.050, 115), "lv2": (0.500, 0.080, 130),
    "lv3": (0.590, 0.100, 140), "lv4": (0.680, 0.115, 148), "lv5": (0.780, 0.120, 152),
}
# (chữ hoặc hình, nền, mức tối thiểu)
PAIRS = [
    ("ink", "paper", 4.5), ("ink", "sheet", 4.5), ("ink-2", "sheet", 4.5), ("ink-2", "sunken", 4.5),
    ("ink-3", "sheet", 3.0),
    ("on-leaf", "leaf", 4.5), ("on-leaf", "leaf-strong", 4.5), ("leaf", "sheet", 4.5), ("ink", "leaf-tint", 4.5),
    ("danger", "sheet", 4.5), ("ink", "danger-tint", 4.5), ("warning-ink", "sheet", 4.5), ("info", "sheet", 4.5),
    ("focus", "sheet", 3.0), ("line-strong", "sheet", 3.0),
    ("der", "sheet", 4.5), ("die", "sheet", 4.5), ("das", "sheet", 4.5), ("plural", "sheet", 4.5),
    ("lv5", "sheet", 3.0), ("lv4", "sheet", 3.0),
]


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


report = {}
bad = []
for theme, pal in (("light", LIGHT), ("dark", DARK)):
    lin = {k: oklch_to_linear_srgb(*v) for k, v in pal.items()}
    out_gamut = [k for k, v in lin.items() if any(x < -0.001 or x > 1.001 for x in v)]
    pairs = []
    for fg, bg, need in PAIRS:
        if fg in lin and bg in lin:
            r = contrast(lin[fg], lin[bg])
            pairs.append({"fg": fg, "bg": bg, "ratio": round(r, 2), "need": need, "ok": r >= need})
            if r < need:
                bad.append(f"{theme}: {fg} on {bg} = {r:.2f} < {need}")
    report[theme] = {
        "hex": {k: to_hex(v) for k, v in lin.items()},
        "oklch": {k: f"oklch({v[0] * 100:.1f}% {v[1]:.3f} {v[2]:g})" for k, v in pal.items()},
        "out_of_gamut": out_gamut, "pairs": pairs,
    }
    if out_gamut:
        bad.append(f"{theme}: out of gamut {out_gamut}")

report["failures"] = bad
print(json.dumps(report, ensure_ascii=False, indent=1))
sys.exit(1 if bad else 0)
