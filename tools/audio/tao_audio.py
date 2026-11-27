"""Tạo audio đọc tiếng Đức bằng Piper: MP3 32 kbps một kênh, tên file theo hash (giọng|tốc độ|chữ).

Chạy trên GitHub Actions (workflow audio), cần piper-tts và ffmpeg:
    python3 tools/audio/tao_audio.py --giong de_DE-thorsten-medium --model de_DE-thorsten-medium.onnx \
        --vao danh-sach.txt --ra ra/ [--ghep nghe-thu.mp3]

File vào: mỗi dòng một chữ cần đọc, hoặc mỗi dòng một JSON {"text": "..."}; chữ trùng chỉ tạo một lần.
Ra: ra/<giọng>/<2 ký tự đầu hash>/<hash>.mp3, và ra/manifest.jsonl (một dòng mỗi file: hash, path, text, voice,
speed, bytes, duration_ms) để máy chủ ghi vào media_assets. File đã có thì bỏ qua, nên chạy lại chỉ tạo phần thiếu.
--ghep: thêm một file nghe thử, đọc lần lượt mọi chữ, cách nhau 0,7 giây.
"""
import argparse
import io
import json
import re
import shutil
import subprocess
import sys
import time
import wave
from collections.abc import Callable, Iterable
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass, field
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from giong import TOC_DO, GiongKhongDung, chuan_hoa, duong_dan, hash_audio, kiem  # noqa: E402

KBPS = 32
NGHI_GIUA_MS = 700


@dataclass
class Am:
    """Âm thanh PCM một kênh (hoặc nhiều kênh) từ Piper."""
    khung: bytes
    tan_so: int
    kenh: int = 1
    do_rong: int = 2  # số byte mỗi mẫu (16 bit)

    @property
    def ms(self) -> int:
        return round(len(self.khung) / (self.do_rong * self.kenh) * 1000 / self.tan_so)


@dataclass
class KetQua:
    tao: int = 0
    bo_qua: int = 0
    loi: list[str] = field(default_factory=list)
    byte_tu: list[int] = field(default_factory=list)
    byte_cau: list[int] = field(default_factory=list)
    giay_doc: float = 0.0


def doc_vao(duong: Path) -> list[str]:
    """Danh sách chữ cần đọc, theo thứ tự trong file, bỏ dòng trống và chữ trùng (sau khi chuẩn hoá)."""
    ra: list[str] = []
    da: set[str] = set()
    for so, dong in enumerate(duong.read_text(encoding="utf-8").splitlines(), 1):
        dong = dong.strip()
        if not dong or dong.startswith("#"):
            continue
        if dong.startswith("{"):
            try:
                dong = str(json.loads(dong)["text"])
            except (ValueError, KeyError, TypeError) as e:
                raise ValueError(f"dòng {so}: JSON phải có khoá text ({e})") from e
        chu = chuan_hoa(dong)
        if chu and chu not in da:
            da.add(chu)
            ra.append(chu)
    return ra


def la_cau(chu: str) -> bool:
    """Câu (để thống kê riêng dung lượng từ và câu): có dấu kết câu hoặc từ 4 chữ trở lên; "das Brötchen" vẫn là từ."""
    return bool(re.search(r"[.!?…]$", chu)) or len(chu.split()) >= 4


def wav_tu_am(am: Am) -> bytes:
    buf = io.BytesIO()
    with wave.open(buf, "wb") as w:
        w.setnchannels(am.kenh)
        w.setsampwidth(am.do_rong)
        w.setframerate(am.tan_so)
        w.writeframes(am.khung)
    return buf.getvalue()


def nen_mp3(wav: bytes, dich: Path) -> None:
    """WAV → MP3 32 kbps một kênh bằng ffmpeg."""
    dich.parent.mkdir(parents=True, exist_ok=True)
    tam = dich.with_suffix(".tmp.mp3")
    subprocess.run(
        ["ffmpeg", "-nostdin", "-y", "-loglevel", "error", "-f", "wav", "-i", "pipe:0", "-ac", "1",
         "-codec:a", "libmp3lame", "-b:a", f"{KBPS}k", str(tam)],
        input=wav, check=True,
    )
    tam.replace(dich)  # đổi tên sau cùng: file dở dang không bao giờ mang tên thật


def bo_doc_piper(model: Path) -> Callable[[str], Am]:
    """Nạp model Piper một lần, trả hàm đọc một chữ. Chạy được với piper-tts 1.2 và 1.3 trở lên."""
    try:
        from piper import PiperVoice
    except ImportError:  # piper-tts 1.2
        from piper.voice import PiperVoice
    giong = PiperVoice.load(str(model))

    def doc(chu: str) -> Am:
        buf = io.BytesIO()
        with wave.open(buf, "wb") as w:
            if hasattr(giong, "synthesize_wav"):
                giong.synthesize_wav(chu, w)
            else:
                giong.synthesize(chu, w)
        buf.seek(0)
        with wave.open(buf, "rb") as r:
            return Am(r.readframes(r.getnframes()), r.getframerate(), r.getnchannels(), r.getsampwidth())

    return doc


def tao(
    giong: str,
    cac_chu: Iterable[str],
    ra: Path,
    doc: Callable[[str], Am],
    nen: Callable[[bytes, Path], None] = nen_mp3,
    ghep: Path | None = None,
    so_luong: int = 4,
) -> KetQua:
    """Tạo file cho mọi chữ chưa có file; ghi manifest.jsonl. doc và nen thay được trong test."""
    kq = KetQua()
    ra.mkdir(parents=True, exist_ok=True)
    cac_am: list[Am] = []
    dong_manifest: list[dict] = []
    viec = []
    with ThreadPoolExecutor(max_workers=so_luong) as pool:
        for chu in cac_chu:
            ma = hash_audio(giong, chu)
            rel = duong_dan(giong, ma)
            dich = ra / rel
            if dich.exists() and ghep is None:
                kq.bo_qua += 1
                continue
            bat = time.monotonic()
            try:
                am = doc(chu)
            except Exception as e:  # một chữ lỗi không làm hỏng cả lượt
                kq.loi.append(f"{chu}: {e}")
                continue
            kq.giay_doc += time.monotonic() - bat
            if ghep is not None:
                cac_am.append(am)
            dong = {"hash": ma, "path": rel, "text": chu, "voice": giong, "speed": TOC_DO, "duration_ms": am.ms}
            viec.append((pool.submit(nen, wav_tu_am(am), dich), dich, dong, la_cau(chu)))
        for tuong_lai, dich, dong, cau in viec:
            try:
                tuong_lai.result()
            except Exception as e:
                kq.loi.append(f"{dong['text']}: không nén được MP3 ({e})")
                continue
            dong["bytes"] = dich.stat().st_size
            (kq.byte_cau if cau else kq.byte_tu).append(dong["bytes"])
            dong_manifest.append(dong)
            kq.tao += 1

    with (ra / "manifest.jsonl").open("a", encoding="utf-8") as f:
        for dong in dong_manifest:
            f.write(json.dumps(dong, ensure_ascii=False) + "\n")

    if ghep is not None and cac_am:
        nen(wav_tu_am(ghep_am(cac_am)), ghep)
    return kq


def ghep_am(cac_am: list[Am]) -> Am:
    """Nối các đoạn âm, chèn khoảng lặng giữa hai đoạn (các đoạn cùng tần số, cùng dạng)."""
    dau = cac_am[0]
    lang = b"\x00" * (dau.do_rong * dau.kenh * round(dau.tan_so * NGHI_GIUA_MS / 1000))
    return Am(lang.join(a.khung for a in cac_am), dau.tan_so, dau.kenh, dau.do_rong)


def _kb_trung_binh(ds: list[int]) -> float:
    return round(sum(ds) / len(ds) / 1024, 1) if ds else 0.0


def bao_cao(giong: str, kq: KetQua, tong: int) -> str:
    tb = _kb_trung_binh
    dong = [
        f"Giọng {giong}: {tong} chữ, tạo {kq.tao}, đã có {kq.bo_qua}, lỗi {len(kq.loi)}",
        f"Trung bình: từ {tb(kq.byte_tu)} KB ({len(kq.byte_tu)} file), câu {tb(kq.byte_cau)} KB ({len(kq.byte_cau)} file)",
        f"Thời gian đọc: {kq.giay_doc:.1f} giây, {kq.giay_doc / max(1, kq.tao + len(kq.loi)):.2f} giây mỗi chữ",
        f"Dung lượng mới: {round((sum(kq.byte_tu) + sum(kq.byte_cau)) / 1024 / 1024, 2)} MB",
    ]
    dong += [f"✘ {e}" for e in kq.loi[:20]]
    return "\n".join(dong)


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    p.add_argument("--giong", required=True, help="tên giọng Piper, vd de_DE-thorsten-medium")
    p.add_argument("--model", required=True, type=Path, help="file .onnx của giọng (cùng chỗ với .onnx.json)")
    p.add_argument("--vao", required=True, type=Path)
    p.add_argument("--ra", required=True, type=Path)
    p.add_argument("--ghep", type=Path, help="thêm một file MP3 đọc lần lượt mọi chữ (để nghe thử)")
    a = p.parse_args(argv)
    try:
        kiem(a.giong)
    except GiongKhongDung as e:
        print(f"✘ {e}", file=sys.stderr)
        return 1
    if shutil.which("ffmpeg") is None:
        print("✘ Thiếu ffmpeg.", file=sys.stderr)
        return 1
    cac_chu = doc_vao(a.vao)
    kq = tao(a.giong, cac_chu, a.ra, bo_doc_piper(a.model), ghep=a.ghep)
    print(bao_cao(a.giong, kq, len(cac_chu)))
    return 1 if kq.loi else 0


if __name__ == "__main__":
    sys.exit(main())
