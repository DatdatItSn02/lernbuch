"""Sinh tests/chung/lap-lich.json: bảng ca thử chung cho luật lặp lại ngắt quãng, Pest và Vitest cùng đọc.

Kết quả mong đợi tính bằng một bản thứ ba, độc lập với PHP và TypeScript: chép nguyên srs.py của Lernbuch,
chỉ thay giờ máy bằng múi giờ của người học (zoneinfo). Chạy lại khi đổi luật: python3 tools/hoc/sinh_ca_lap_lich.py
"""
import json
from datetime import datetime, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

NGAY = {1: 1, 2: 3, 3: 7, 4: 14, 5: 30}
HOC_LAI_GIAY = 600
TEN_CHAM = ["Quên", "Khó", "Tốt", "Dễ"]


def muc_moi(muc: int, cham: int) -> int:
    goc = max(muc, 1)  # thẻ mới tính như mức 1
    if cham == 0:
        return 1 if muc <= 1 else max(1, muc - 2)
    if cham == 1:
        return goc
    if cham == 2:
        return min(5, goc + 1)
    return min(5, goc + 2)


def so_ngay(muc: int, cham: int) -> int:
    ngay = NGAY[muc_moi(muc, cham)]
    return max(1, ngay // 2) if cham == 1 else ngay


def dau_ngay(luc: int, mui_gio: str) -> int:
    d = datetime.fromtimestamp(luc, ZoneInfo(mui_gio))
    return int(d.replace(hour=0, minute=0, second=0).timestamp())


def cham_the(muc: int, cham: int, luc: int, mui_gio: str) -> tuple[int, int, bool]:
    moi = muc_moi(muc, cham)
    if cham == 0:
        return moi, luc + HOC_LAI_GIAY, muc >= 1
    # +12 giờ như Lernbuch để không lệch ngày khi đổi giờ mùa hè
    return moi, dau_ngay(luc + so_ngay(muc, cham) * 86400 + 43200, mui_gio), False


def gio_dia_phuong(luc: int, mui_gio: str) -> str:
    return datetime.fromtimestamp(luc, ZoneInfo(mui_gio)).strftime("%Y-%m-%dT%H:%M:%S")


def ca(ten: str, muc: int, cham: int, dia_phuong: str, mui_gio: str) -> dict:
    luc = int(datetime.fromisoformat(dia_phuong).replace(tzinfo=ZoneInfo(mui_gio)).timestamp())
    moi, han, quen = cham_the(muc, cham, luc, mui_gio)
    return {
        "ten": ten,
        "muc": muc,
        "cham": cham,
        "luc": datetime.fromtimestamp(luc, timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "mui_gio": mui_gio,
        "luc_dia_phuong": gio_dia_phuong(luc, mui_gio),
        "muc_moi": moi,
        "so_ngay": None if cham == 0 else so_ngay(muc, cham),
        "han": gio_dia_phuong(han, mui_gio),
        "quen": quen,
    }


def main() -> None:
    hcm, berlin, ny = "Asia/Ho_Chi_Minh", "Europe/Berlin", "America/New_York"
    cac_ca = []
    # Mọi nhánh: 6 mức × 4 cách chấm, cùng một giờ buổi sáng
    for muc in range(6):
        for cham in range(4):
            cac_ca.append(ca(f"mức {muc}, chấm {TEN_CHAM[cham]}", muc, cham, "2026-11-27T09:00:00", hcm))
    # Làm tròn về đầu ngày: cộng 12 giờ nên chấm từ trưa trở đi thì hạn sang thêm một ngày, như Lernbuch
    cac_ca += [
        ca("làm tròn: 11:59:59 vẫn về ngày của hạn", 1, 2, "2026-11-27T11:59:59", hcm),
        ca("làm tròn: 12:00:00 sang ngày kế", 1, 2, "2026-11-27T12:00:00", hcm),
        ca("làm tròn: chấm buổi tối", 1, 2, "2026-11-27T20:00:00", hcm),
        ca("làm tròn: chấm lúc nửa đêm", 3, 3, "2026-11-28T00:00:00", hcm),
        ca("Quên lúc 23:55: ôn lại sau 10 phút, sang ngày mới", 4, 0, "2026-11-27T23:55:00", hcm),
    ]
    # Đổi giờ mùa hè (Berlin: 29/3 lùi 1 giờ ngủ, 25/10 thêm 1 giờ; New York: 8/3 và 1/11)
    cac_ca += [
        ca("Berlin, đêm trước khi sang giờ mùa hè", 1, 1, "2026-03-28T23:30:00", berlin),
        ca("Berlin, 14 ngày qua ngày sang giờ mùa hè", 3, 2, "2026-03-22T12:00:00", berlin),
        ca("Berlin, 1 giờ sáng ngày sang giờ mùa hè", 2, 2, "2026-03-29T01:30:00", berlin),
        ca("Berlin, đêm trước khi về giờ mùa đông", 2, 1, "2026-10-24T23:30:00", berlin),
        ca("Berlin, 7 ngày qua ngày về giờ mùa đông", 2, 2, "2026-10-18T10:00:00", berlin),
        ca("Berlin, 30 ngày qua ngày về giờ mùa đông", 5, 3, "2026-10-10T08:00:00", berlin),
        ca("New York, 3 ngày qua ngày sang giờ mùa hè", 1, 2, "2026-03-06T21:00:00", ny),
        ca("New York, 1 ngày qua ngày về giờ mùa đông", 1, 1, "2026-10-31T22:00:00", ny),
    ]
    # Đổi múi giờ: cùng một lúc chấm, người học ở múi giờ khác thì hạn tính theo ngày ở múi giờ đó
    for mui_gio in [hcm, berlin, "America/Los_Angeles", "Pacific/Auckland"]:
        luc = datetime(2026, 11, 27, 2, 0, tzinfo=timezone.utc).astimezone(ZoneInfo(mui_gio)).strftime("%Y-%m-%dT%H:%M:%S")
        cac_ca.append(ca(f"đổi múi giờ: cùng lúc 02:00 UTC ở {mui_gio}", 1, 2, luc, mui_gio))

    du_lieu = {
        "mo_ta": "Luật lặp lại ngắt quãng 6 mức của Lernbuch (srs.py). luc là giờ chấm (UTC); han là hạn ôn theo giờ "
        "địa phương của mui_gio; so_ngay là số ngày ghi trên nút chấm (Quên: ôn lại sau 10 phút). Sinh bằng "
        "tools/hoc/sinh_ca_lap_lich.py, đừng sửa tay.",
        "ca": cac_ca,
        "loi": [
            {"ten": "mức 6 không có", "muc": 6, "cham": 2},
            {"ten": "mức âm", "muc": -1, "cham": 2},
            {"ten": "chấm 4 không có", "muc": 1, "cham": 4},
            {"ten": "chấm âm", "muc": 1, "cham": -1},
        ],
    }
    ra = Path(__file__).resolve().parents[2] / "tests" / "chung" / "lap-lich.json"
    ra.write_text(json.dumps(du_lieu, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"{len(cac_ca)} ca, {len(du_lieu['loi'])} ca lỗi → {ra}")


if __name__ == "__main__":
    main()
