"""Test công cụ audio: giọng được phép bán (mục tự kiểm 12), tên file theo hash, tạo file và bỏ qua file đã có.

Chạy: python3 -m unittest tools/audio/test_audio.py   (không cần Piper hay ffmpeg: phần đọc và nén thay bằng bản giả)
"""
import contextlib
import io
import json
import sys
import tempfile
import unittest
import unicodedata
from pathlib import Path

GOC = Path(__file__).resolve().parent
sys.path.insert(0, str(GOC))

import giong  # noqa: E402
import tao_audio  # noqa: E402
from giong import GiongKhongDung  # noqa: E402

CA_HASH = json.loads((GOC.parent.parent / "tests" / "chung" / "audio-hash.json").read_text(encoding="utf-8"))


class KiemGiong(unittest.TestCase):
    def test_giong_duoc_phep(self):
        g = giong.kiem("de_DE-thorsten-medium")
        self.assertEqual((g["ten"], g["chat_luong"], g["giay_phep"]), ("thorsten", "medium", "CC0 1.0"))
        for ten in ["de_DE-thorsten-high", "de_DE-kerstin-low", "de_DE-eva_k-x_low", "de_DE-karlsson-low", "de_DE-ramona-low"]:
            self.assertEqual(giong.kiem(ten)["giong"], ten)

    def test_pavoque_bi_chan_vi_cam_dung_thuong_mai(self):
        with self.assertRaises(GiongKhongDung) as e:
            giong.kiem("de_DE-pavoque-low")
        self.assertIn("không được dùng để bán", str(e.exception))
        self.assertIn("CC BY-NC-SA 4.0", str(e.exception))

    def test_giong_chua_co_trong_danh_sach_cung_bi_chan(self):
        for ten in ["de_DE-mls-medium", "de_DE-thorsten_emotional-medium"]:
            with self.assertRaises(GiongKhongDung) as e:
                giong.kiem(ten)
            self.assertIn("chưa có trong danh sách", str(e.exception))

    def test_sai_ten_hoac_chat_luong(self):
        for ten in ["thorsten", "de_DE-thorsten", "de-DE-thorsten-medium", "de_DE-thorsten-ultra", ""]:
            with self.assertRaises(GiongKhongDung, msg=ten):
                giong.kiem(ten)
        with self.assertRaises(GiongKhongDung) as e:
            giong.kiem("de_DE-kerstin-high")
        self.assertIn("không có bản chất lượng high", str(e.exception))

    def test_lenh_kiem_bao_ma_thoat_va_ly_do(self):
        loi = io.StringIO()
        with contextlib.redirect_stderr(loi):
            self.assertEqual(giong.main(["giong.py", "de_DE-pavoque-low"]), 1)
        self.assertIn("không được dùng để bán", loi.getvalue())
        ra = io.StringIO()
        with contextlib.redirect_stdout(ra):
            self.assertEqual(giong.main(["giong.py", "de_DE-thorsten-medium"]), 0)
        self.assertIn("CC0 1.0", ra.getvalue())
        with contextlib.redirect_stderr(io.StringIO()):
            self.assertEqual(giong.main(["giong.py"]), 2)

    def test_tao_audio_tu_choi_giong_cam_truoc_khi_nap_model(self):
        loi = io.StringIO()
        with contextlib.redirect_stderr(loi):
            ma = tao_audio.main(["--giong", "de_DE-pavoque-low", "--model", "khong-co.onnx", "--vao", "khong-co.txt", "--ra", "ra"])
        self.assertEqual(ma, 1)
        self.assertIn("pavoque", loi.getvalue())

    def test_dia_chi_model_co_dinh_ban(self):
        onnx, cau_hinh = giong.dia_chi_model("de_DE-thorsten-medium")
        self.assertEqual(onnx, "https://huggingface.co/rhasspy/piper-voices/resolve/v1.0.0/de/de_DE/thorsten/medium/de_DE-thorsten-medium.onnx")
        self.assertEqual(cau_hinh, onnx + ".json")


class TenFile(unittest.TestCase):
    def test_ca_thu_chung_voi_may_chu(self):
        self.assertGreaterEqual(len(CA_HASH["ca"]), 6)
        for ca in CA_HASH["ca"]:
            with self.subTest(ca["ten"]):
                self.assertEqual(giong.chuan_hoa(ca["chu"]), ca["chu_chuan"])
                self.assertEqual(giong.hash_audio(ca["giong"], ca["chu"]), ca["hash"])
                self.assertEqual(giong.duong_dan(ca["giong"], ca["hash"]), ca["path"])

    def test_cung_chu_khac_cach_viet_unicode_ra_cung_file_khac_giong_thi_khac(self):
        a = giong.hash_audio("de_DE-thorsten-medium", "zählen")
        self.assertEqual(a, giong.hash_audio("de_DE-thorsten-medium", unicodedata.normalize("NFD", "zählen")))
        self.assertNotEqual(a, giong.hash_audio("de_DE-thorsten-medium", "zahlen"))
        self.assertNotEqual(a, giong.hash_audio("de_DE-kerstin-low", "zählen"))


def am_gia(chu: str) -> tao_audio.Am:
    # 10 ms mỗi chữ cái, 22050 Hz, 16 bit
    return tao_audio.Am(b"\x01\x00" * (221 * len(chu)), 22050)


def nen_gia(wav: bytes, dich: Path) -> None:
    dich.parent.mkdir(parents=True, exist_ok=True)
    dich.write_bytes(wav[:1000])


class TaoFile(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp())

    def test_doc_file_vao_bo_trung_bo_dong_trong_doc_json(self):
        f = self.tmp / "vao.txt"
        f.write_text("# ghi chú\ndas Brötchen\n\n  das   Brötchen \n{\"text\": \"zählen\"}\nzahlen\n", encoding="utf-8")
        self.assertEqual(tao_audio.doc_vao(f), ["das Brötchen", "zählen", "zahlen"])
        f.write_text("{\"chu\": \"sai khoá\"}\n", encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "dòng 1"):
            tao_audio.doc_vao(f)

    def test_tao_file_dung_ten_ghi_manifest_chay_lai_chi_tao_phan_thieu(self):
        g = "de_DE-thorsten-medium"
        ra = self.tmp / "ra"
        kq = tao_audio.tao(g, ["das Brötchen", "Ich möchte zwei Brötchen, bitte."], ra, am_gia, nen_gia)
        self.assertEqual((kq.tao, kq.bo_qua, kq.loi), (2, 0, []))
        ma = giong.hash_audio(g, "das Brötchen")
        self.assertTrue((ra / g / ma[:2] / f"{ma}.mp3").exists())
        dong = [json.loads(x) for x in (ra / "manifest.jsonl").read_text(encoding="utf-8").splitlines()]
        self.assertEqual([d["text"] for d in dong], ["das Brötchen", "Ich möchte zwei Brötchen, bitte."])
        self.assertEqual(dong[0] | {"bytes": 0}, {"hash": ma, "path": f"{g}/{ma[:2]}/{ma}.mp3", "text": "das Brötchen", "voice": g,
                                                   "speed": "normal", "duration_ms": 120, "bytes": 0})
        self.assertEqual((len(kq.byte_tu), len(kq.byte_cau)), (1, 1))

        kq2 = tao_audio.tao(g, ["das Brötchen", "zählen"], ra, am_gia, nen_gia)
        self.assertEqual((kq2.tao, kq2.bo_qua), (1, 1))
        self.assertEqual(len((ra / "manifest.jsonl").read_text(encoding="utf-8").splitlines()), 3)

    def test_mot_chu_loi_khong_lam_hong_ca_luot(self):
        def doc(chu: str) -> tao_audio.Am:
            if chu == "hỏng":
                raise RuntimeError("model không đọc được")
            return am_gia(chu)

        kq = tao_audio.tao("de_DE-thorsten-medium", ["eins", "hỏng", "zwei"], self.tmp / "ra", doc, nen_gia)
        self.assertEqual(kq.tao, 2)
        self.assertEqual(len(kq.loi), 1)
        self.assertIn("hỏng: model không đọc được", kq.loi[0])
        self.assertIn("lỗi 1", tao_audio.bao_cao("de_DE-thorsten-medium", kq, 3))

    def test_loi_khi_nen_mp3_ghi_vao_bao_cao_khong_ghi_manifest(self):
        def nen(wav: bytes, dich: Path) -> None:
            raise RuntimeError("ffmpeg dừng")

        kq = tao_audio.tao("de_DE-thorsten-medium", ["eins"], self.tmp / "ra", am_gia, nen)
        self.assertEqual(kq.tao, 0)
        self.assertIn("không nén được MP3", kq.loi[0])
        self.assertEqual((self.tmp / "ra" / "manifest.jsonl").read_text(encoding="utf-8"), "")

    def test_file_nghe_thu_doc_lan_luot_co_khoang_lang(self):
        ghep = self.tmp / "nghe-thu.mp3"
        nhan: list[bytes] = []

        def nen(wav: bytes, dich: Path) -> None:
            nhan.append(wav)
            nen_gia(wav, dich)

        tao_audio.tao("de_DE-thorsten-medium", ["ab", "cd"], self.tmp / "ra", am_gia, nen, ghep=ghep)
        self.assertTrue(ghep.exists())
        ghep_am = tao_audio.ghep_am([am_gia("ab"), am_gia("cd")])
        self.assertEqual(ghep_am.ms, 20 + 20 + tao_audio.NGHI_GIUA_MS)
        self.assertEqual(nhan[-1], tao_audio.wav_tu_am(ghep_am))


if __name__ == "__main__":
    unittest.main()
