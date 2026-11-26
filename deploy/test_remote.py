"""Test remote-deploy.sh và remote-rollback.sh trên thư mục tạm, với lệnh `php` giả (không cần máy chủ thật).

Chạy: python3 -m unittest deploy/test_remote.py
"""

import os
import subprocess
import tarfile
import tempfile
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent

PHP_GIA = """#!/usr/bin/env bash
# php giả: ghi lại lệnh, cho migrate hỏng khi có biến MIGRATE_HONG, không đo được database khi có DUNG_LUONG_HONG
echo "$*" >> "$HOME/php.log"
case "$*" in
  *"migrate"*) [ -n "${MIGRATE_HONG:-}" ] && { echo "migrate hỏng"; exit 1; } ;;
  *"app:dung-luong"*) [ -n "${DUNG_LUONG_HONG:-}" ] && { echo "Database: không đo được"; exit 1; }
                      echo "Database: 1.0M (3 bảng)" ;;
esac
exit 0
"""


class RemoteScriptsTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.home = Path(self.tmp.name)
        self.app = self.home / "lernbuch"
        (self.app / "releases").mkdir(parents=True)
        (self.app / "incoming").mkdir()
        bin_dir = self.home / "bin"
        bin_dir.mkdir()
        php = bin_dir / "php"
        php.write_text(PHP_GIA)
        php.chmod(0o755)
        self.env = {**os.environ, "HOME": str(self.home), "PATH": f"{bin_dir}:{os.environ['PATH']}"}

    def tearDown(self):
        self.tmp.cleanup()

    def ban(self, name, tot=False):
        d = self.app / "releases" / name
        d.mkdir()
        if tot:
            (d / ".da-kiem").touch()
        return d

    def dat_current(self, name):
        cur = self.app / "current"
        if cur.is_symlink():
            cur.unlink()
        cur.symlink_to(self.app / "releases" / name)

    def current(self):
        return Path(os.readlink(self.app / "current")).name

    def chay(self, script, *args, **extra):
        return subprocess.run(["bash", str(HERE / script), *args], env={**self.env, **extra},
                              capture_output=True, text=True)

    def goi_ban(self, name):
        src = self.home / "src"
        src.mkdir(exist_ok=True)
        (src / "artisan").write_text("")
        with tarfile.open(self.app / "incoming" / f"{name}.tgz", "w:gz") as t:
            t.add(src / "artisan", arcname="./artisan")
        (self.app / "incoming" / f"{name}.env").write_text("APP_KEY=base64:abc\nAPP_ENV=production\n")

    # --- quay lại ---

    def test_quay_lai_uu_tien_ban_da_qua_kiem(self):
        self.ban("20260101000000-aaaaaaa", tot=True)
        self.ban("20260102000000-bbbbbbb")  # bản hỏng /up, chưa qua kiểm
        self.ban("20260103000000-ccccccc")
        self.dat_current("20260103000000-ccccccc")
        r = self.chay("remote-rollback.sh")
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        self.assertEqual(self.current(), "20260101000000-aaaaaaa")

    def test_quay_lai_chua_co_ban_qua_kiem_thi_lay_ban_ngay_truoc(self):
        self.ban("20260101000000-aaaaaaa")
        self.ban("20260102000000-bbbbbbb")
        self.ban("20260103000000-ccccccc")
        self.dat_current("20260103000000-ccccccc")
        r = self.chay("remote-rollback.sh")
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        self.assertEqual(self.current(), "20260102000000-bbbbbbb")

    def test_quay_lai_khong_chon_ban_moi_hon_ban_dang_chay(self):
        self.ban("20260101000000-aaaaaaa")
        self.ban("20260102000000-bbbbbbb")
        self.ban("20260103000000-ccccccc", tot=True)
        self.dat_current("20260102000000-bbbbbbb")
        r = self.chay("remote-rollback.sh")
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        self.assertEqual(self.current(), "20260101000000-aaaaaaa")

    def test_quay_lai_loi_khi_khong_co_ban_truoc(self):
        self.ban("20260101000000-aaaaaaa")
        self.dat_current("20260101000000-aaaaaaa")
        r = self.chay("remote-rollback.sh")
        self.assertNotEqual(r.returncode, 0)
        self.assertIn("Không có bản trước", r.stdout)
        self.assertEqual(self.current(), "20260101000000-aaaaaaa")

    # --- deploy ---

    def test_deploy_thanh_cong_doi_current_va_giu_ban_tot(self):
        self.ban("20260101000000-aaaaaaa", tot=True)
        for n in ("20260102000000-bbbbbbb", "20260103000000-ccccccc"):
            self.ban(n)
        self.dat_current("20260103000000-ccccccc")
        name = "20260104000000-ddddddd"
        self.goi_ban(name)
        r = self.chay("remote-deploy.sh", name)
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        self.assertEqual(self.current(), name)
        con = sorted(p.name for p in (self.app / "releases").iterdir())
        # 3 bản mới nhất + bản tốt gần nhất (aaaaaaa) không bị xoá
        self.assertEqual(con, ["20260101000000-aaaaaaa", "20260102000000-bbbbbbb",
                               "20260103000000-ccccccc", name])
        # Dung lượng: thư mục app, cả thư mục nhà, database (đúng dạng tools/tu-kiem đọc)
        self.assertRegex(r.stdout, r"(?m)^Thư mục app: \S+$")
        self.assertRegex(r.stdout, r"(?m)^Cả thư mục nhà: \S+$")
        self.assertIn("Database: 1.0M (3 bảng)", r.stdout)
        self.assertLess(r.stdout.index("Database:"), r.stdout.index(f"Xong: {name}"))

    def test_deploy_khong_do_duoc_database_van_xong(self):
        self.ban("20260101000000-aaaaaaa", tot=True)
        self.dat_current("20260101000000-aaaaaaa")
        name = "20260102000000-bbbbbbb"
        self.goi_ban(name)
        r = self.chay("remote-deploy.sh", name, DUNG_LUONG_HONG="1")
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        self.assertIn("Database: không đo được", r.stdout)
        self.assertIn(f"Xong: {name}", r.stdout)
        self.assertEqual(self.current(), name)

    def test_deploy_xoa_ban_cu_khi_ban_tot_nam_trong_3_ban_moi(self):
        for n in ("20260101000000-aaaaaaa", "20260102000000-bbbbbbb"):
            self.ban(n)
        self.ban("20260103000000-ccccccc", tot=True)
        self.dat_current("20260103000000-ccccccc")
        name = "20260104000000-ddddddd"
        self.goi_ban(name)
        r = self.chay("remote-deploy.sh", name)
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        con = sorted(p.name for p in (self.app / "releases").iterdir())
        self.assertEqual(con, ["20260102000000-bbbbbbb", "20260103000000-ccccccc", name])

    def test_deploy_migration_hong_giu_ban_dang_chay_va_xoa_ban_do_dang(self):
        self.ban("20260101000000-aaaaaaa", tot=True)
        self.dat_current("20260101000000-aaaaaaa")
        name = "20260102000000-bbbbbbb"
        self.goi_ban(name)
        r = self.chay("remote-deploy.sh", name, MIGRATE_HONG="1")
        self.assertNotEqual(r.returncode, 0)
        self.assertIn("giữ nguyên bản đang chạy: 20260101000000-aaaaaaa", r.stdout)
        self.assertEqual(self.current(), "20260101000000-aaaaaaa")
        self.assertFalse((self.app / "releases" / name).exists())

    def test_deploy_thieu_goi_thi_dung(self):
        self.ban("20260101000000-aaaaaaa", tot=True)
        self.dat_current("20260101000000-aaaaaaa")
        r = self.chay("remote-deploy.sh", "20260102000000-khongco")
        self.assertNotEqual(r.returncode, 0)
        self.assertEqual(self.current(), "20260101000000-aaaaaaa")
        self.assertFalse((self.app / "releases" / "20260102000000-khongco").exists())


if __name__ == "__main__":
    unittest.main()
