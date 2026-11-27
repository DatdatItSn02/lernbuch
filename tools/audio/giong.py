"""Giọng Piper cho audio tạo sẵn: kiểm giấy phép, tên file, địa chỉ tải model.

Tên giọng theo Piper: <ngôn ngữ>-<tên>-<chất lượng>, vd de_DE-thorsten-medium. Chỉ giọng có trong
tools/audio/giong.json (mục cho_phep) mới được dùng: bộ dữ liệu gốc phải cho dùng thương mại.

Kiểm một giọng (workflow audio gọi trước khi tạo):
    python3 tools/audio/giong.py de_DE-thorsten-medium
Mã thoát 0 là dùng được; 1 là không dùng được, kèm lý do.
"""
import hashlib
import json
import re
import sys
import unicodedata
from pathlib import Path

DANH_SACH = Path(__file__).with_name("giong.json")
TOC_DO = "normal"  # nghe chậm phát ở tốc độ 0,65 trong trình duyệt, không tạo file riêng
MAU_TEN = re.compile(r"^(?P<lang>[a-z]{2}_[A-Z]{2})-(?P<ten>[a-z0-9_]+)-(?P<chat_luong>x_low|low|medium|high)$")
# Bản cố định của kho giọng Piper trên Hugging Face, để chạy lại vẫn ra đúng model cũ
KHO_MODEL = "https://huggingface.co/rhasspy/piper-voices/resolve/v1.0.0"


class GiongKhongDung(ValueError):
    """Giọng không được dùng: sai tên, chưa có trong danh sách, hoặc bộ dữ liệu cấm dùng thương mại."""


def doc_danh_sach(duong: Path = DANH_SACH) -> dict:
    return json.loads(duong.read_text(encoding="utf-8"))


def kiem(giong: str, ds: dict | None = None) -> dict:
    """Trả thông tin giấy phép của giọng dùng được; không dùng được thì báo GiongKhongDung kèm lý do."""
    ds = ds if ds is not None else doc_danh_sach()
    m = MAU_TEN.match(giong.strip())
    if not m:
        raise GiongKhongDung(f"Tên giọng '{giong}' không đúng dạng của Piper, vd de_DE-thorsten-medium.")
    ten, chat_luong = m["ten"], m["chat_luong"]
    if ten in ds.get("cam", {}):
        c = ds["cam"][ten]
        raise GiongKhongDung(
            f"Giọng {ten} không được dùng để bán: bộ dữ liệu {c['bo_du_lieu']} theo {c['giay_phep']} ({c['ly_do']}). Nguồn: {c['nguon']}"
        )
    if ten not in ds.get("cho_phep", {}):
        raise GiongKhongDung(
            f"Giọng {ten} chưa có trong danh sách giọng được dùng để bán (tools/audio/giong.json). "
            "Kiểm giấy phép bộ dữ liệu ở model card rồi mới thêm."
        )
    g = ds["cho_phep"][ten]
    if chat_luong not in g["chat_luong"]:
        raise GiongKhongDung(f"Giọng {ten} không có bản chất lượng {chat_luong}; có: {', '.join(g['chat_luong'])}.")
    return {**g, "giong": giong.strip(), "ten": ten, "chat_luong": chat_luong, "lang": m["lang"]}


def dia_chi_model(giong: str) -> tuple[str, str]:
    """Địa chỉ tải model (.onnx) và cấu hình (.onnx.json) của giọng trên kho Piper."""
    m = MAU_TEN.match(giong)
    if not m:
        raise GiongKhongDung(f"Tên giọng '{giong}' không đúng dạng của Piper.")
    lang = m["lang"]
    goc = f"{KHO_MODEL}/{lang.split('_')[0]}/{lang}/{m['ten']}/{m['chat_luong']}/{giong}.onnx"
    return goc, goc + ".json"


def chuan_hoa(chu: str) -> str:
    """Chữ cần đọc: Unicode NFC, bỏ khoảng trắng thừa. Máy chủ (PHP) chuẩn hoá đúng như vậy trước khi tính hash."""
    return re.sub(r"\s+", " ", unicodedata.normalize("NFC", chu)).strip()


def hash_audio(giong: str, chu: str, toc_do: str = TOC_DO) -> str:
    """sha1(giọng|tốc độ|chữ), như cột media_assets.hash."""
    return hashlib.sha1(f"{giong}|{toc_do}|{chuan_hoa(chu)}".encode("utf-8")).hexdigest()


def duong_dan(giong: str, ma: str) -> str:
    """Đường dẫn file trong thư mục audio dùng chung: <giọng>/<2 ký tự đầu của hash>/<hash>.mp3."""
    return f"{giong}/{ma[:2]}/{ma}.mp3"


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print("Dùng: python3 tools/audio/giong.py <tên giọng Piper>", file=sys.stderr)
        return 2
    try:
        g = kiem(argv[1])
    except GiongKhongDung as e:
        print(f"✘ {e}", file=sys.stderr)
        return 1
    print(f"✔ Giọng {g['giong']} dùng được: bộ dữ liệu {g['bo_du_lieu']}, {g['giay_phep']}. Nguồn: {g['nguon']}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
