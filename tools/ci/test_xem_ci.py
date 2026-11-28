"""Test công cụ xem kết quả CI (tools/ci/xem_ci.py), cả trường hợp lỗi. Chạy: python3 -m unittest tools/ci/test_xem_ci.py"""
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import xem_ci  # noqa: E402

SHA = 'e21bb2c3bcc8f1a6b00fbf4e594b7c79de535e15'
DAU = f'workflow: ci  job: php  status: failure\ncommit: {SHA}  ref: work/g2-tuan1  run: 46052446154\n'
TOM_HONG = DAU + """
✔ Pint (định dạng code)
✘ Larastan (phân tích tĩnh)
✘ Pest (database lernbuch_test)
KẾT QUẢ: 17/19 bước đạt
✘ lỗi: Larastan (phân tích tĩnh)
✘ lỗi: Pest (database lernbuch_test)

----- cuối log -----
   \x1b[41;1m FAIL \x1b[49;22m Tests\\Feature\\AiHanMucTest
  ✓ it dùng hết lượt thì bị chặn
  ⨯ it qua nửa đêm thì có lượt mới
  \x1b[41;1m FAILED \x1b[49;22m \x1b[1mTests\\Feature\\AiHanMucTest\x1b[22m > it qua nửa đêm thì có lượt mới
  Failed asserting that 0 is identical to 50.

  at tests/Feature/AiHanMucTest.php:41
KẾT QUẢ: 17/19 bước đạt
✘ lỗi: Larastan (phân tích tĩnh)
✘ lỗi: Pest (database lernbuch_test)
"""
LOG = """2026-11-28T01:00:00.1000000Z ##[group]Pint (định dạng code)
2026-11-28T01:00:01.1000000Z ✔ Pint (định dạng code)
2026-11-28T01:00:01.2000000Z ##[endgroup]
2026-11-28T01:00:02.1000000Z ##[group]Larastan (phân tích tĩnh)
2026-11-28T01:00:03.1000000Z   12     Parameter #1 $x of method A::b() expects int, string given.
2026-11-28T01:00:03.2000000Z  [ERROR] Found 1 error
2026-11-28T01:00:03.3000000Z ##[endgroup]
2026-11-28T01:00:04.1000000Z ##[group]Test công cụ so khớp và quy ước
2026-11-28T01:00:04.2000000Z OK
2026-11-28T01:00:04.3000000Z ##[endgroup]
2026-11-28T01:00:05.1000000Z ##[group]Pest (database lernbuch_test)
2026-11-28T01:00:06.1000000Z    FAILED  Tests\\Feature\\AiHanMucTest > it qua nửa đêm thì có lượt mới
2026-11-28T01:00:06.2000000Z   Failed asserting that 0 is identical to 50.
2026-11-28T01:00:06.3000000Z ##[endgroup]
"""


class DocTomTat(unittest.TestCase):
    def test_hai_dong_dau(self):
        self.assertEqual(xem_ci.doc_dau(DAU), {'job': 'php', 'status': 'failure', 'commit': SHA, 'ref': 'work/g2-tuan1', 'run': '46052446154'})

    def test_dau_sai_dang_hoac_thieu_dong(self):
        for chu in ('', 'workflow: ci  job: php  status: success', 'xin chào\ncommit: abc', 'workflow: ci  job: php  status: ok\ncommit: KHONG  ref: x  run: 1'):
            self.assertIsNone(xem_ci.doc_dau(chu), chu)

    def test_buoc_hong_khong_lap_va_dong_ket_qua_dau_tien(self):
        self.assertEqual(xem_ci.cac_buoc(TOM_HONG), (['Larastan (phân tích tĩnh)', 'Pest (database lernbuch_test)'], '17/19 bước đạt'))
        self.assertEqual(xem_ci.cac_buoc('không có gì'), ([], None))

    def test_ten_ngan_cua_buoc(self):
        self.assertEqual(xem_ci.ten_ngan('Pest (database lernbuch_test)'), 'Pest')
        self.assertEqual(xem_ci.ten_ngan('Playwright: font, bố cục, đăng nhập'), 'Playwright')
        self.assertEqual(xem_ci.ten_ngan('Kiểm kiểu TypeScript'), 'Kiểm kiểu TypeScript')


class KhoiHong(unittest.TestCase):
    def test_pest_chi_lay_khoi_failed_bo_ma_mau_khong_lay_bang_liet_ke_theo_file(self):
        khoi = xem_ci.khoi_hong(TOM_HONG)
        self.assertEqual(len(khoi), 1)
        self.assertTrue(khoi[0].lstrip().startswith('FAILED  Tests\\Feature\\AiHanMucTest > it qua nửa đêm'), khoi[0])
        self.assertIn('Failed asserting that 0 is identical to 50.', khoi[0])
        self.assertNotIn('\x1b', khoi[0])

    def test_playwright_va_vitest(self):
        pw = '  1) [may-tinh] › tests/e2e/ai.spec.ts:12:5 › hỏi AI\n\n    Error: axe\n  2) [dien-thoai] › tests/e2e/ai.spec.ts:12:5 › hỏi AI\n    Error: axe\n'
        self.assertEqual(len(xem_ci.khoi_hong(pw)), 2)
        vt = ' FAIL  resources/js/app/components/ai/CongAi.test.tsx > Cổng AI > chưa đủ tuổi\nAssertionError: expected null to be truthy\n'
        self.assertEqual(len(xem_ci.khoi_hong(vt)), 1)

    def test_gioi_han_so_khoi_va_so_dong(self):
        chu = ''.join(f'   FAILED  Tests\\Feature\\T{i} > it x\n  dòng 1\n  dòng 2\n  dòng 3\n' for i in range(5))
        khoi = xem_ci.khoi_hong(chu, toi_da=2, dong=2)
        self.assertEqual(len(khoi), 2)
        self.assertEqual(len(khoi[0].splitlines()), 2)

    def test_khong_co_khoi_nao(self):
        self.assertEqual(xem_ci.khoi_hong('  ✓ it chạy tốt\n  Tests: 3 passed\n'), [])


class BangKetQua(unittest.TestCase):
    def test_chi_lay_dong_hong(self):
        tsv = 'dat\tpest\t-\ttests/Feature/A.php\tổn\nhong\tpest\t-\ttests/Feature/B.php\thỏng\nbo_qua\tvitest\t-\ta.test.ts\tbỏ\nhong\tthiếu cột\n\n'
        self.assertEqual(xem_ci.hong_tsv(tsv), [('pest', 'tests/Feature/B.php', 'hỏng')])


class CatBuoc(unittest.TestCase):
    def test_lay_dung_buoc_bo_gio_dau_dong(self):
        khoi = xem_ci.cat_buoc(LOG, 'Larastan')
        self.assertEqual(khoi.splitlines()[0], '##[group]Larastan (phân tích tĩnh)')
        self.assertEqual(khoi.splitlines()[-1], '##[endgroup]')
        self.assertIn('[ERROR] Found 1 error', khoi)
        self.assertNotIn('Pint', khoi)

    def test_ten_bat_dau_duoc_uu_tien_hon_ten_chua(self):
        # "Test công cụ…" chứa chữ "test", nhưng bước tên bắt đầu bằng "Pest" phải được chọn khi hỏi "Pest"
        self.assertIn('AiHanMucTest', xem_ci.cat_buoc(LOG, 'Pest'))
        self.assertIn('OK', xem_ci.cat_buoc(LOG, 'so khớp'))

    def test_khong_co_buoc(self):
        self.assertIsNone(xem_ci.cat_buoc(LOG, 'Playwright'))
        self.assertIsNone(xem_ci.cat_buoc('', 'Pest'))

    def test_cat_bot_khi_qua_dai(self):
        khoi = xem_ci.cat_buoc(LOG, 'Larastan', dong=2)
        self.assertEqual(len(khoi.splitlines()), 3)
        self.assertIn('cắt, còn 2 dòng', khoi.splitlines()[-1])

    def test_chi_tiet_lay_khoi_hong_neu_co_khong_thi_lay_cuoi(self):
        self.assertTrue(xem_ci.chi_tiet(xem_ci.cat_buoc(LOG, 'Pest')).lstrip().startswith('FAILED'))
        self.assertEqual(xem_ci.chi_tiet('a\nb\nc\nd', dong=2), 'c\nd')


class TomTat(unittest.TestCase):
    def test_dat_thi_mot_dong(self):
        tom = f'workflow: ci  job: web  status: success\ncommit: {SHA}  ref: main  run: 7\n\n✔ Vitest\nKẾT QUẢ: 9/9 bước đạt\n'
        self.assertEqual(xem_ci.tom_tat('web', tom, 'dat\tvitest\t-\ta\tb\n', SHA), ('web: success (run 7) 9/9 bước đạt', 0))
        self.assertEqual(xem_ci.tom_tat('web', tom, None, SHA[:7])[1], 0)

    def test_chua_co_ket_qua_cua_commit_nay(self):
        chu, ma = xem_ci.tom_tat('php', DAU, None, 'abcdef0123456789')
        self.assertEqual(ma, 2)
        self.assertIn('chưa có kết quả của abcdef0', chu)
        self.assertIn('e21bb2c trên work/g2-tuan1', chu)

    def test_khong_co_file_tom_tat(self):
        self.assertEqual(xem_ci.tom_tat('php', None, None, SHA), ('php: chưa có file tóm tắt', 2))
        self.assertEqual(xem_ci.tom_tat('php', 'rác', None, SHA)[1], 2)

    def test_hong_co_log_day_du_in_chi_tiet_tung_buoc(self):
        tsv = 'hong\tpest\t-\ttests/Feature/AiHanMucTest.php\tqua nửa đêm thì có lượt mới\n'
        chu, ma = xem_ci.tom_tat('php', TOM_HONG, tsv, SHA, LOG)
        self.assertEqual(ma, 1)
        dong = chu.splitlines()
        self.assertEqual(dong[0], 'php: failure (run 46052446154) 17/19 bước đạt')
        self.assertIn('  test hỏng (1):', dong)
        self.assertIn('    pest tests/Feature/AiHanMucTest.php › qua nửa đêm thì có lượt mới', dong)
        self.assertIn('[ERROR] Found 1 error', chu)
        self.assertIn('Failed asserting that 0 is identical to 50.', chu)
        self.assertNotIn('log một bước', chu)

    def test_hong_khong_co_log_thi_dung_phan_cuoi_va_chi_lenh_xem_buoc(self):
        chu, ma = xem_ci.tom_tat('php', TOM_HONG, None, SHA)
        self.assertEqual(ma, 1)
        self.assertIn('Failed asserting that 0 is identical to 50.', chu)
        self.assertTrue(chu.splitlines()[-1].endswith('--buoc php "Larastan"'), chu.splitlines()[-1])

    def test_nhieu_hon_ba_buoc_hong_van_liet_ke_du_ten(self):
        ten = ['Pint', 'Larastan', 'Migrate lên', 'Pest', 'Hiệu năng']
        tom = DAU + '\n' + ''.join(f'✘ lỗi: {t}\n' for t in ten)
        chu, _ = xem_ci.tom_tat('php', tom, None, SHA, LOG)
        for t in ten:
            self.assertIn(f'✘ {t}', chu)


class ChayLenh(unittest.TestCase):
    def test_byte_hong_khong_lam_do(self):
        ra = xem_ci.chay([sys.executable, '-c', "import sys; sys.stdout.buffer.write(b'\\xbbn l\\xc3\\xa2u\\n')"])
        self.assertEqual(ra, '�n lâu\n')

    def test_lenh_loi_hoac_khong_co_thi_none(self):
        self.assertIsNone(xem_ci.chay([sys.executable, '-c', 'import sys; sys.exit(2)']))
        self.assertIsNone(xem_ci.chay(['lenh-khong-co-that-xyz']))


if __name__ == '__main__':
    unittest.main()
