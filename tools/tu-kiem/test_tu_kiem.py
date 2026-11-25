"""Test tu_kiem.py bằng log giả lập: đủ bằng chứng thì đạt, có bước hỏng thì báo chưa đạt.

Chạy: python3 -m unittest tools/tu-kiem/test_tu_kiem.py
"""
import contextlib
import io
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import tu_kiem  # noqa: E402

MIG = '\n'.join(f'✔ {s}' for s in tu_kiem.MIGRATION_STEPS)
SCHEMA_JSON = json.dumps({
    'checks': [{'name': f'c{i}', 'ok': True} for i in range(16)],
    'behaviour': [{'case': f'b{i}', 'ok': True} for i in range(22)],
    'server': '11.4.13-MariaDB', 'table_count': 91,
}, ensure_ascii=False, indent=1)
PEST_TITLES = [
    'chưa đăng nhập vào /admin thì về trang đăng nhập', 'không lộ file .env và log',
    'lỗi máy chủ chỉ hiện trang lỗi chung, không lộ nội dung lỗi hay đường dẫn',
    'nhập sai mật khẩu 5 lần thì bị chặn tạm thời, kể cả lần sau đúng mật khẩu',
    "ghi rồi đọc chữ có dấu qua database giữ nguyên with ('Brötchen')",
    'kết nối sai bảng mã thì chữ có dấu đọc ra bị hỏng, nên test bắt được', 'kết nối database dùng utf8mb4',
    'lịch có việc ghi nhịp chạy mỗi phút', 'job thử chạy xong qua hàng đợi database', 'job cố ý lỗi vào failed_jobs sau đúng 3 lần thử',
    '10 phút lịch chạy mỗi phút qua hàng đợi thì đủ 10 nhịp', 'thiếu nhịp (lịch hay hàng đợi dừng) thì kiểm nhịp báo lỗi',
]


def php_log(server='11.4.13-MariaDB-ubu2404', migration=MIG, schema=SCHEMA_JSON, seed='5 40 10 9 14', pest=None, extra_steps=''):
    pest = pest if pest is not None else '\n'.join(f'  \x1b[32m✓\x1b[39m it {t}   0.05s' for t in PEST_TITLES)
    return f"""workflow: ci  job: php  status: success
commit: abcdef1234567  ref: work/x  run: 111

{migration}
✔ Khớp với schema.sql (0 khác biệt)
✔ Quy ước LoWo (không khoá ngoại, thời gian số nguyên)
✔ Seeder chạy hai lần không nhân đôi
✔ test_schema.py (cấu trúc và tình huống)
✔ Test công cụ so khớp và quy ước
{extra_steps}
KẾT QUẢ: 14/14 bước đạt

----- cuối log -----
Máy chủ database: {server}
lần 1: {seed} | lần 2: {seed}
::group::test_schema.py (cấu trúc và tình huống)
{schema}
✔ test_schema.py (cấu trúc và tình huống)
{pest}
"""


def web_log(contrast='70/70', fonts=2):
    lines = []
    for p in ('may-tinh', 'dien-thoai')[:fonts]:
        lines.append(f'  ✓   1 [{p}] › tests/e2e/chu.spec.ts:74:1 › mọi mẫu chữ hiện đúng font, đủ dấu tiếng Việt và ä ö ü ß ẞ (2.0s)')
        lines.append(f'  ✓   4 [{p}] › tests/e2e/chu.spec.ts:95:1 › thiếu file font: phép kiểm báo lỗi, chữ vẫn đọc được bằng font dự phòng (1.1s)')
    return f"""workflow: ci  job: web  status: success
commit: abcdef1234567  ref: work/x  run: 111

✔ Tương phản màu WCAG AA (palette_check.py trên tokens.css)
✔ Test palette_check (cả trường hợp lỗi)

----- cuối log -----
Tương phản: {contrast} cặp đạt WCAG 2 AA (sáng và tối)
""" + '\n'.join(lines)


def runs():
    def j(name, concl, start='2026-11-25T10:00:00Z', end='2026-11-25T10:02:10Z'):
        return {'name': name, 'conclusion': concl, 'started': start, 'completed': end}
    return [
        {'id': 1, 'workflow': 'ci', 'branch': 'work/ci-red', 'sha': 'x', 'title': 'Thử CI đỏ', 'event': 'push', 'conclusion': 'failure',
         'created': '', 'jobs': [j('php (PHP 8.4)', 'failure'), j('web', 'success'), j('deploy lên alwaysdata', 'skipped')]},
        {'id': 2, 'workflow': 'ci', 'branch': 'main', 'sha': 'y', 'title': 'ok', 'event': 'push', 'conclusion': 'success',
         'created': '', 'jobs': [j('php (PHP 8.4)', 'success'), j('web', 'success'), j('deploy lên alwaysdata', 'skipped')]},
        {'id': 3, 'workflow': 'windows', 'branch': 'main', 'sha': 'y', 'title': 'ok', 'event': 'push', 'conclusion': 'success',
         'created': '', 'jobs': [j('setup', 'success', end='2026-11-25T10:03:30Z')]},
        {'id': 4, 'workflow': 'nightly', 'branch': 'nightly/run', 'sha': 'y', 'title': 'n', 'event': 'push', 'conclusion': 'success',
         'created': '', 'jobs': [j('php trên mariadb:11.4', 'success'), j('php trên mysql:8.4', 'success')]},
    ]


class TuKiemTest(unittest.TestCase):
    def run_check(self, files, run_list=None, args=()):
        with tempfile.TemporaryDirectory() as d:
            for name, text in files.items():
                Path(d, name).write_text(text, encoding='utf-8')
            rp = Path(d, 'runs.json')
            rp.write_text(json.dumps(run_list if run_list is not None else runs()), encoding='utf-8')
            out = Path(d, 'kq.json')
            with contextlib.redirect_stdout(io.StringIO()):
                code = tu_kiem.main(['--ci', d, '--runs', str(rp), '--out', str(out), *args])
            data = json.loads(out.read_text(encoding='utf-8'))
            return code, {i['so']: i for i in data['muc']}, data

    def good_files(self):
        return {
            'latest-ci-php.txt': php_log(),
            'latest-ci-web.txt': web_log(),
            'latest-nightly-php-mariadb-2.txt': php_log(server='11.8.3-MariaDB'),
            'latest-nightly-php-mysql-3.txt': php_log(server='8.4.7'),
        }

    def test_du_bang_chung_thi_dat(self):
        code, m, data = self.run_check(self.good_files())
        self.assertEqual(code, 0)
        for so in (1, 2, 3, 4, 5, 6, 11, 12, 14, 15):
            self.assertEqual(m[so]['trang_thai'], 'dat', (so, m[so]))
        self.assertEqual(m[13]['trang_thai'], 'dat_mot_phan')
        self.assertEqual(m[16]['trang_thai'], 'dat_mot_phan')
        for so in (7, 8, 9, 10):
            self.assertEqual(m[so]['trang_thai'], 'chua_chay')
            self.assertIn('DEPLOY_ENABLED', m[so]['ghi_chu'])
        self.assertEqual(data['tong']['chua_dat'], 0)
        self.assertEqual(len(data['muc']), 16)

    def test_log_cu_cung_ban_database_bi_bo_qua(self):
        files = self.good_files()
        old = php_log(server='8.4.7', migration=MIG.replace('✔ Migrate lên lại', '✘ Migrate lên lại')).replace('run: 111', 'run: 50')
        files['latest-nightly-php-mysql-2.txt'] = old
        _, m, _ = self.run_check(files)
        self.assertEqual(m[1]['trang_thai'], 'dat')
        self.assertEqual(sum('MySQL 8.4' in e for e in m[1]['bang_chung']), 1)

    def test_thieu_ban_database_thi_dat_mot_phan(self):
        files = self.good_files()
        del files['latest-nightly-php-mysql-3.txt']
        _, m, _ = self.run_check(files)
        self.assertEqual(m[1]['trang_thai'], 'dat_mot_phan')
        self.assertIn('MySQL 8.4', m[1]['ghi_chu'])

    def test_migration_hong_tren_mot_ban_thi_chua_dat(self):
        files = self.good_files()
        files['latest-nightly-php-mysql-3.txt'] = php_log(server='8.4.7', migration=MIG.replace('✔ Migrate xuống hết', '✘ Migrate xuống hết'))
        code, m, _ = self.run_check(files)
        self.assertEqual(m[1]['trang_thai'], 'chua_dat')
        self.assertEqual(code, 1)

    def test_test_schema_thieu_mot_kiem_tra_thi_chua_dat(self):
        bad = json.loads(SCHEMA_JSON)
        bad['checks'][3]['ok'] = False
        files = self.good_files()
        files['latest-ci-php.txt'] = php_log(schema=json.dumps(bad))
        _, m, _ = self.run_check(files)
        self.assertEqual(m[5]['trang_thai'], 'chua_dat')
        self.assertIn('15/16', m[5]['bang_chung'][0])

    def test_seeder_nhan_doi_thi_chua_dat(self):
        files = self.good_files()
        files['latest-ci-php.txt'] = php_log(seed='10 80 20 9 14')
        _, m, _ = self.run_check(files)
        self.assertEqual(m[4]['trang_thai'], 'chua_dat')

    def test_test_pest_hong_thi_muc_do_chua_dat(self):
        pest = '\n'.join(
            (f'  ⨯ it {t}' if t == 'không lộ file .env và log' else f'  ✓ it {t}') for t in PEST_TITLES)
        files = self.good_files()
        files['latest-ci-php.txt'] = php_log(pest=pest)
        _, m, _ = self.run_check(files)
        self.assertEqual(m[11]['trang_thai'], 'chua_dat')
        self.assertTrue(any('HỎNG' in e for e in m[11]['bang_chung']))
        self.assertEqual(m[12]['trang_thai'], 'dat')

    def test_font_chi_mot_co_man_hinh_thi_chua_dat(self):
        files = self.good_files()
        files['latest-ci-web.txt'] = web_log(fonts=1)
        _, m, _ = self.run_check(files)
        self.assertEqual(m[14]['trang_thai'], 'chua_dat')

    def test_tuong_phan_thieu_mot_cap_thi_chua_dat(self):
        files = self.good_files()
        files['latest-ci-web.txt'] = web_log(contrast='69/70')
        _, m, _ = self.run_check(files)
        self.assertEqual(m[15]['trang_thai'], 'chua_dat')

    def test_khong_co_log_thi_chua_chay(self):
        _, m, data = self.run_check({}, run_list=[])
        for so in (1, 2, 3, 4, 5, 6, 11, 12, 13, 14, 15):
            self.assertEqual(m[so]['trang_thai'], 'chua_chay', so)
        self.assertEqual(data['tong']['chua_dat'], 0)

    def test_ten_test_pest_bi_cat_van_nhan_ra(self):
        pest = '\n'.join(f'  ✓ it {t}   0.05s' for t in PEST_TITLES)
        pest = pest.replace('chữ có dấu đọc ra bị hỏng, nên test bắt được   0.05s', 'chữ có dấu đọc ra bị hỏng, nên test bắt… 1.13s')
        files = self.good_files()
        files['latest-ci-php.txt'] = php_log(pest=pest)
        _, m, _ = self.run_check(files)
        self.assertEqual(m[12]['trang_thai'], 'dat')

    def test_ten_test_khac_thi_khong_nham(self):
        pest = '\n'.join(f'  ✓ it {t}   0.05s' for t in PEST_TITLES if not t.startswith('không lộ'))
        pest += '\n  ✓ it không lộ   0.05s'
        files = self.good_files()
        files['latest-ci-php.txt'] = php_log(pest=pest)
        _, m, _ = self.run_check(files)
        self.assertEqual(m[11]['trang_thai'], 'chua_chay')

    def test_phut_ci_tinh_tron_len_va_windows_nhan_2(self):
        _, m, _ = self.run_check(self.good_files())
        ev = ' '.join(m[16]['bang_chung'])
        # ci: 3 job 2m10s -> 3+3 phút (deploy bỏ qua không tính); windows: 3m30s -> 4 phút x 2 = 8
        self.assertIn('windows: 1 lần, trung bình 8.0 phút/lần', ev)
        self.assertIn('ci: 2 lần, trung bình 6.0 phút/lần', ev)

    def test_phut_ci_vuot_muc_thi_chua_dat(self):
        # windows chạy mỗi lần đẩy code (120 lần x 8 phút) thì vượt 1.500 phút
        _, m, _ = self.run_check(self.good_files(), args=('--windows-moi-thang', '120'))
        self.assertEqual(m[16]['trang_thai'], 'chua_dat')
        self.assertIn('vượt mức', m[16]['ghi_chu'])

    def test_deploy_that_va_quay_lai_co_bang_chung_thi_dat(self):
        r = runs()
        r[1]['jobs'][2]['conclusion'] = 'success'
        r.append({'id': 9, 'workflow': 'rollback', 'branch': 'main', 'sha': 'y', 'title': 'rb', 'event': 'workflow_dispatch',
                  'conclusion': 'success', 'created': '', 'jobs': []})
        _, m, _ = self.run_check(self.good_files(), run_list=r)
        self.assertEqual(m[7]['trang_thai'], 'dat')
        self.assertEqual(m[10]['trang_thai'], 'dat')


if __name__ == '__main__':
    unittest.main()
