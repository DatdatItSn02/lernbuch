"""Test tu_kiem.py bằng log giả lập (theo đúng dạng log thật): đủ bằng chứng thì đạt, có bước hỏng thì báo chưa đạt.

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


# ---------- log máy chủ (runs/*-ci-deploy.txt, runs/*-rollback-rollback.txt), theo dạng log thật ----------

R = {i: f'2026112{i}100000-{i}{i}{i}{i}{i}{i}{i}' for i in range(1, 9)}


def server_log(run, body, status='success', kind='deploy'):
    wf, job = ('ci', 'deploy') if kind == 'deploy' else ('rollback', 'rollback')
    head = '\n'.join(l for l in body.splitlines() if l.startswith(('✔', '✘')))
    return f"""workflow: {wf}  job: {job}  status: {status}
commit: {run:07d}{'0' * 33}  ref: main  run: {run}

{head}

----- cuối log -----
composer install ... (đóng gói)
{body}
"""


def status_lines(cur, rels, up='200', beats=None):
    out = [f'Trước deploy: /up trả {up}', f'Bản đang chạy: {cur}']
    out += [f'Dung lượng: 107M\treleases/{r}' for r in rels] + ['Dung lượng: 1.3M\tshared', 'Dung lượng: 15M\t.']
    if beats is not None:
        out.append(f'Nhịp trong 10 phút qua: {beats}/10 (cần ít nhất 9).')
    return out


def deploy_ok(rel, before=None, disk='new', home='380M', db='3.2M (92 bảng)', beats=None):
    out = status_lines(*before, beats=beats) if before else []
    out += [f'== Giải nén {rel}', '== Cập nhật .env (giữ APP_KEY cũ)', '== Migration', '   INFO  Nothing to migrate.',
            '== Cache cấu hình, route, view', '  config \x1b[90m....\x1b[39m 46.45ms \x1b[32;1mDONE\x1b[39;22m',
            f'== Đổi current sang {rel}', '   INFO  Broadcasting queue restart signal.',
            '== Giữ 3 bản gần nhất (và luôn giữ bản tốt gần nhất để còn chỗ quay lại)']
    if disk == 'new':
        out += ['== Dung lượng (gói Free: 1 GB tính chung file, mail và database)', 'Thư mục app: 337M', f'Cả thư mục nhà: {home}',
                db if db.startswith('Database') else f'Database: {db}']
    elif disk == 'old':
        out.append('337M\t/home/lernbuch/lernbuch')
    out += [f'Xong: {rel}', f'✔ Web đã chạy bản {rel} sau khoảng 0 giây', f'✔ /up trả 200 ({rel})']
    return '\n'.join(out)


def deploy_mig_fail(rel, kept, before=None, switched=False):
    out = status_lines(*before) if before else []
    out += [f'== Giải nén {rel}', '== Cập nhật .env (giữ APP_KEY cũ)', '== Migration', '   INFO  Running migrations.',
            '  2026_11_26_000001_thu_migration_hong \x1b[90m....\x1b[39m 4.07ms \x1b[31;1mFAIL\x1b[39;22m',
            'In 2026_11_26_000001_thu_migration_hong.php line 12:', '  Migration cố ý hỏng để thử mục 8.']
    if switched:
        out.append(f'== Đổi current sang {rel}')
    out.append(f'✘ Deploy dừng, giữ nguyên bản đang chạy: {kept}')
    return '\n'.join(out)


def deploy_rollback(rel, prev, before=None, app_up='500', after=None, web_timeout=True):
    out = status_lines(*before) if before else []
    out += [f'== Giải nén {rel}', '== Migration', '   INFO  Nothing to migrate.', f'== Đổi current sang {rel}', f'Xong: {rel}']
    if web_timeout:
        out += [f'✘ Sau 240 giây trang chủ vẫn chưa báo bản {rel}', '✘ /up trả ?, trang chủ trả 200']
    else:
        out += [f'✔ Web đã chạy bản {rel} sau khoảng 0 giây', '✘ /up trả 500, trang chủ trả 500']
    out += [f'current -> /home/lernbuch/lernbuch/releases/{rel}', 'có public/index.php', f'app tự trả /up: {app_up}',
            'Quay lại bản trước', f'Đã quay lại: {rel} -> {prev}']
    if after is not False:
        a, code = after or (prev, '200')
        out.append(f'Sau khi quay lại: web chạy Bản {a}, /up trả {code}')
    return '\n'.join(out)


def rollback_wf(cur, prev, after=None):
    a, code = after or (prev, '200')
    out = [f'Đã quay lại: {cur} -> {prev}', f'Sau khi quay lại: web chạy Bản {a}, /up trả {code}']
    if (a, code) == (prev, '200'):
        out.append(f'✔ Web chạy bản trước ({prev}), /up trả 200')
    return '\n'.join(out)


def history():
    """Lịch sử như G0: deploy tốt, migration hỏng, bản làm /up lỗi tự quay lại, quay lại bằng tay, đủ 5 lần deploy tốt."""
    return {
        'runs/1-ci-deploy.txt': server_log(100, deploy_ok(R[1], disk='old')),
        'runs/2-ci-deploy.txt': server_log(101, deploy_ok(R[2], before=(R[1], [R[1]]), disk='old')),
        'runs/3-ci-deploy.txt': server_log(102, deploy_mig_fail(R[3], kept=R[2], before=(R[2], [R[1], R[2]])), status='failure'),
        'runs/4-ci-deploy.txt': server_log(103, deploy_ok(R[4], before=(R[2], [R[1], R[2]]), disk='old')),
        'runs/5-ci-deploy.txt': server_log(104, deploy_rollback(R[5], prev=R[4], before=(R[4], [R[1], R[2], R[4]])), status='failure'),
        'runs/6-ci-deploy.txt': server_log(105, deploy_ok(R[6], before=(R[4], [R[2], R[4], R[5]]), beats=10)),
        'runs/1-rollback-rollback.txt': server_log(106, rollback_wf(R[6], R[4]), kind='rollback'),
        'runs/7-ci-deploy.txt': server_log(107, deploy_ok(R[7], before=(R[4], [R[4], R[5], R[6]]), beats=9)),
    }


TAY = {'muc': [
    {'so': 7, 'nguon': 'đăng nhập trên trình duyệt', 'luc': '2026-11-27', 'dang_nhap_admin': True},
    {'so': 13, 'nguon': 'SSH trên alwaysdata', 'luc': '2026-11-26', 'nhip': 9, 'trong_phut': 10,
     'job_thu_xong': True, 'job_loi_so_lan_thu': 3, 'job_loi_vao_failed_jobs': True},
]}


class TuKiemTest(unittest.TestCase):
    def run_check(self, files, run_list=None, args=(), tay=None):
        with tempfile.TemporaryDirectory() as d:
            for name, text in files.items():
                Path(d, name).parent.mkdir(parents=True, exist_ok=True)
                Path(d, name).write_text(text, encoding='utf-8')
            rp = Path(d, 'runs.json')
            rp.write_text(json.dumps(run_list if run_list is not None else runs()), encoding='utf-8')
            out = Path(d, 'kq.json')
            if tay is not None:
                Path(d, 'tay.json').write_text(json.dumps(tay, ensure_ascii=False), encoding='utf-8')
                args = (*args, '--tay', str(Path(d, 'tay.json')))
            with contextlib.redirect_stdout(io.StringIO()):
                code = tu_kiem.main(['--ci', d, '--runs', str(rp), '--out', str(out), *args])
            data = json.loads(out.read_text(encoding='utf-8'))
            return code, {i['so']: i for i in data['muc']}, data

    def with_history(self, changes=None):
        """Bộ log tốt + lịch sử máy chủ; changes: tên file -> nội dung thay vào."""
        return {**self.good_files(), **history(), **(changes or {})}

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
        for so in (7, 8, 9):
            self.assertEqual(m[so]['trang_thai'], 'chua_chay')
            self.assertIn('DEPLOY_ENABLED', m[so]['ghi_chu'])
        self.assertEqual(m[10]['trang_thai'], 'chua_chay')
        self.assertIn('Run workflow', m[10]['ghi_chu'])
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

    # ---------- mục 7–10, 13, 16 từ log máy chủ ----------

    def test_log_may_chu_du_thi_dat(self):
        code, m, data = self.run_check(self.with_history(), tay=TAY)
        for so in range(1, 17):
            self.assertEqual(m[so]['trang_thai'], 'dat', (so, m[so]))
        self.assertEqual(code, 0)
        self.assertIn(R[7], m[7]['bang_chung'][0])
        self.assertIn(f'giữ bản {R[2]}', m[8]['bang_chung'][0])
        self.assertTrue(any(f'bản dở dang {R[3]} đã bị xoá' in e for e in m[8]['bang_chung']))
        self.assertTrue(any(f'{R[5]} -> {R[4]}' in e for e in m[9]['bang_chung']))
        self.assertIn('php.ini', m[9]['ghi_chu'])
        self.assertTrue(any(f'{R[6]} -> {R[4]}' in e for e in m[10]['bang_chung']))
        # nhịp lấy từ log deploy mới nhất có dòng app:kiem-nhip (9/10), không phải từ kết quả tay
        self.assertTrue(any('9/10 nhịp (app:kiem-nhip trước lần deploy run 107' in e for e in m[13]['bang_chung']))
        self.assertTrue(any('383 MB = 37% của 1 GB; đo sau 5 lần deploy thành công' in e for e in m[16]['bang_chung']), m[16])

    def test_chua_dang_nhap_admin_thi_muc_7_dat_mot_phan(self):
        _, m, _ = self.run_check(self.with_history())
        self.assertEqual(m[7]['trang_thai'], 'dat_mot_phan')
        self.assertIn('app:create-admin', m[7]['ghi_chu'])

    def test_dang_nhap_admin_khong_duoc_thi_chua_dat(self):
        tay = {'muc': [{**TAY['muc'][0], 'dang_nhap_admin': False}, TAY['muc'][1]]}
        code, m, _ = self.run_check(self.with_history(), tay=tay)
        self.assertEqual(m[7]['trang_thai'], 'chua_dat')
        self.assertEqual(code, 1)

    def test_deploy_khong_bao_dung_ban_moi_thi_khong_tinh(self):
        # /up 200 nhưng trang chủ chưa báo bản mới (web còn chạy bản cũ): không phải bằng chứng deploy chạy được
        files = {'runs/1-ci-deploy.txt': server_log(100, deploy_ok(R[1]).replace(f'✔ Web đã chạy bản {R[1]} sau khoảng 0 giây', ''))}
        _, m, _ = self.run_check(files)
        self.assertEqual(m[7]['trang_thai'], 'chua_chay')

    def test_migration_hong_ma_van_doi_current_thi_chua_dat(self):
        bad = server_log(102, deploy_mig_fail(R[3], kept=R[2], before=(R[2], [R[1], R[2]]), switched=True), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/3-ci-deploy.txt': bad}))
        self.assertEqual(m[8]['trang_thai'], 'chua_dat')
        self.assertIn('vẫn đổi current', m[8]['ghi_chu'])

    def test_migration_hong_lan_sau_thay_ban_khac_thi_chua_dat(self):
        bad = server_log(103, deploy_ok(R[4], before=(R[3], [R[1], R[2]]), disk='old'))
        _, m, _ = self.run_check(self.with_history({'runs/4-ci-deploy.txt': bad}))
        self.assertEqual(m[8]['trang_thai'], 'chua_dat')
        self.assertIn(f'không phải {R[2]}', m[8]['ghi_chu'])

    def test_migration_hong_trang_khong_chay_thi_chua_dat(self):
        bad = server_log(103, deploy_ok(R[4], before=(R[2], [R[1], R[2]], '500'), disk='old'))
        _, m, _ = self.run_check(self.with_history({'runs/4-ci-deploy.txt': bad}))
        self.assertEqual(m[8]['trang_thai'], 'chua_dat')
        self.assertIn('/up trả 500', m[8]['ghi_chu'])

    def test_migration_hong_con_ban_do_dang_thi_chua_dat(self):
        bad = server_log(103, deploy_ok(R[4], before=(R[2], [R[1], R[2], R[3]]), disk='old'))
        _, m, _ = self.run_check(self.with_history({'runs/4-ci-deploy.txt': bad}))
        self.assertEqual(m[8]['trang_thai'], 'chua_dat')
        self.assertIn('chưa bị xoá', m[8]['ghi_chu'])

    def test_migration_hong_giu_sai_ban_thi_chua_dat(self):
        bad = server_log(102, deploy_mig_fail(R[3], kept=R[1], before=(R[2], [R[1], R[2]])), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/3-ci-deploy.txt': bad}))
        self.assertEqual(m[8]['trang_thai'], 'chua_dat')
        self.assertIn('khác bản đang chạy trước đó', m[8]['ghi_chu'])

    def test_migration_hong_chua_co_lan_deploy_sau_thi_dat_mot_phan(self):
        files = {k: v for k, v in self.with_history().items() if not k.startswith('runs/') or int(k[5]) <= 3 and 'deploy' in k}
        _, m, _ = self.run_check(files)
        self.assertEqual(m[8]['trang_thai'], 'dat_mot_phan')

    def test_quay_lai_nhung_web_chay_ban_khac_thi_chua_dat(self):
        bad = server_log(104, deploy_rollback(R[5], prev=R[4], after=(R[5], '200')), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/5-ci-deploy.txt': bad}))
        self.assertEqual(m[9]['trang_thai'], 'chua_dat')
        self.assertIn(f'web chạy {R[5]}', m[9]['ghi_chu'])

    def test_quay_lai_xong_up_loi_thi_chua_dat(self):
        bad = server_log(104, deploy_rollback(R[5], prev=R[4], after=(R[4], '500')), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/5-ci-deploy.txt': bad}))
        self.assertEqual(m[9]['trang_thai'], 'chua_dat')

    def test_quay_lai_khong_ghi_sau_khi_quay_lai_thi_dat_mot_phan(self):
        bad = server_log(104, deploy_rollback(R[5], prev=R[4], after=False), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/5-ci-deploy.txt': bad}))
        self.assertEqual(m[9]['trang_thai'], 'dat_mot_phan')

    def test_quay_lai_khi_ban_moi_khong_hong_thi_dat_mot_phan(self):
        # web chưa đổi bản nên workflow quay lại, nhưng app tự trả /up 200: chưa thử được bản hỏng
        bad = server_log(104, deploy_rollback(R[5], prev=R[4], app_up='200'), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/5-ci-deploy.txt': bad}))
        self.assertEqual(m[9]['trang_thai'], 'dat_mot_phan')

    def test_quay_lai_uu_tien_lan_ban_moi_hong(self):
        # như G0: lần thử bản hỏng (app tự trả /up 500), sau đó một lần quay lại vì web chưa đổi bản (app trả 200)
        later = server_log(108, deploy_rollback(R[8], prev=R[7], app_up='200'), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/8-ci-deploy.txt': later}), tay=TAY)
        self.assertEqual(m[9]['trang_thai'], 'dat')
        self.assertIn('run 104', m[9]['bang_chung'][0])

    def test_quay_lai_khi_web_da_doi_ban_thi_khong_ghi_chu_php_ini(self):
        log = server_log(104, deploy_rollback(R[5], prev=R[4], web_timeout=False), status='failure')
        _, m, _ = self.run_check(self.with_history({'runs/5-ci-deploy.txt': log}))
        self.assertEqual(m[9]['trang_thai'], 'dat')
        self.assertTrue(any('/up trên web trả 500' in e for e in m[9]['bang_chung']))
        self.assertEqual(m[9]['ghi_chu'], '')

    def test_rollback_bang_tay_web_khong_doi_ban_thi_chua_dat(self):
        bad = server_log(106, rollback_wf(R[6], R[4], after=(R[6], '200')), status='failure', kind='rollback')
        _, m, _ = self.run_check(self.with_history({'runs/1-rollback-rollback.txt': bad}))
        self.assertEqual(m[10]['trang_thai'], 'chua_dat')

    def test_rollback_bang_tay_khong_co_ban_truoc_thi_chua_dat(self):
        bad = server_log(106, f'Không có bản trước để quay lại (đang chạy {R[1]})', status='failure', kind='rollback')
        code, m, _ = self.run_check(self.with_history({'runs/1-rollback-rollback.txt': bad}))
        self.assertEqual(m[10]['trang_thai'], 'chua_dat')
        self.assertEqual(code, 1)

    def test_rollback_bang_tay_lay_lan_moi_nhat(self):
        files = self.with_history()
        files['runs/2-rollback-rollback.txt'] = server_log(109, rollback_wf(R[7], R[6], after=(R[7], '200')), status='failure', kind='rollback')
        _, m, _ = self.run_check(files)
        self.assertEqual(m[10]['trang_thai'], 'chua_dat')

    def test_dia_tren_60_phan_tram_thi_chua_dat(self):
        big = server_log(107, deploy_ok(R[7], before=(R[4], [R[4], R[5], R[6]]), home='700M'))
        code, m, _ = self.run_check(self.with_history({'runs/7-ci-deploy.txt': big}), tay=TAY)
        self.assertEqual(m[16]['trang_thai'], 'chua_dat')
        self.assertIn('vượt mức', m[16]['ghi_chu'])
        self.assertEqual(code, 1)

    def test_dia_tinh_ca_database(self):
        # 600 MB thư mục nhà + 20 MB database = 620 MB, trên 60% của 1 GB (614 MB)
        big = server_log(107, deploy_ok(R[7], before=(R[4], [R[4], R[5], R[6]]), home='600M', db='20.0M (92 bảng)'))
        _, m, _ = self.run_check(self.with_history({'runs/7-ci-deploy.txt': big}), tay=TAY)
        self.assertEqual(m[16]['trang_thai'], 'chua_dat')

    def test_dia_chi_do_thu_muc_app_thi_dat_mot_phan(self):
        files = {k: v.replace('Cả thư mục nhà', 'X').replace('Thư mục app: 337M', '337M\t/home/lernbuch/lernbuch')
                 for k, v in self.with_history().items()}
        _, m, _ = self.run_check(files)
        self.assertEqual(m[16]['trang_thai'], 'dat_mot_phan')
        self.assertIn('thư mục nhà', m[16]['ghi_chu'])
        self.assertTrue(any('thư mục app 337 MB' in e for e in m[16]['bang_chung']))

    def test_it_hon_5_lan_deploy_thi_dat_mot_phan(self):
        files = {**self.good_files(), 'runs/1-ci-deploy.txt': server_log(100, deploy_ok(R[1])),
                 'runs/2-ci-deploy.txt': server_log(101, deploy_ok(R[2], before=(R[1], [R[1]])))}
        _, m, _ = self.run_check(files)
        self.assertEqual(m[16]['trang_thai'], 'dat_mot_phan')
        self.assertIn('mới 2 lần', m[16]['ghi_chu'])

    def test_database_khong_do_duoc_thi_dat_mot_phan(self):
        log = server_log(107, deploy_ok(R[7], before=(R[4], [R[4], R[5], R[6]]), db='Database: không đo được (không kết nối được)'))
        _, m, _ = self.run_check(self.with_history({'runs/7-ci-deploy.txt': log}), tay=TAY)
        self.assertEqual(m[16]['trang_thai'], 'dat_mot_phan')
        self.assertTrue(any('database không đo được' in e for e in m[16]['bang_chung']))

    def test_thieu_nhip_tren_may_chu_thi_chua_dat(self):
        log = server_log(107, deploy_ok(R[7], before=(R[4], [R[4], R[5], R[6]]), beats=6))
        code, m, _ = self.run_check(self.with_history({'runs/7-ci-deploy.txt': log}), tay=TAY)
        self.assertEqual(m[13]['trang_thai'], 'chua_dat')
        self.assertIn('6/10', m[13]['ghi_chu'])
        self.assertEqual(code, 1)

    def test_job_loi_thu_khac_3_lan_thi_chua_dat(self):
        tay = {'muc': [TAY['muc'][0], {**TAY['muc'][1], 'job_loi_so_lan_thu': 0, 'job_loi_vao_failed_jobs': False}]}
        _, m, _ = self.run_check(self.with_history(), tay=tay)
        self.assertEqual(m[13]['trang_thai'], 'chua_dat')
        self.assertIn('cần đúng 3', m[13]['ghi_chu'])

    def test_co_nhip_chua_thu_hang_doi_thi_dat_mot_phan(self):
        _, m, _ = self.run_check(self.with_history())
        self.assertEqual(m[13]['trang_thai'], 'dat_mot_phan')
        self.assertIn('app:thu-hang-doi', m[13]['ghi_chu'])

    def test_nhip_tu_ket_qua_tay_khi_log_chua_co(self):
        files = {k: v.replace('Nhịp trong', 'X') for k, v in self.with_history().items()}
        _, m, _ = self.run_check(files, tay=TAY)
        self.assertEqual(m[13]['trang_thai'], 'dat')
        self.assertTrue(any('9/10 nhịp (app:kiem-nhip; SSH trên alwaysdata, 2026-11-26)' in e for e in m[13]['bang_chung']))

    def test_ket_qua_tay_thieu_nguon_thi_bao_loi(self):
        tay = {'muc': [{'so': 13, 'nhip': 10}]}
        with tempfile.TemporaryDirectory() as d:
            Path(d, 'runs.json').write_text('[]', encoding='utf-8')
            Path(d, 'tay.json').write_text(json.dumps(tay), encoding='utf-8')
            with contextlib.redirect_stderr(io.StringIO()) as err:
                code = tu_kiem.main(['--ci', d, '--runs', str(Path(d, 'runs.json')), '--tay', str(Path(d, 'tay.json'))])
        self.assertEqual(code, 2)
        self.assertIn('nguon', err.getvalue())

    def test_doi_don_vi_du(self):
        self.assertEqual(tu_kiem.mb('107M'), 107)
        self.assertAlmostEqual(tu_kiem.mb('1.3G'), 1331.2)
        self.assertAlmostEqual(tu_kiem.mb('15K'), 15 / 1024)
        self.assertEqual(tu_kiem.mb('0'), 0)
        self.assertIsNone(tu_kiem.mb('không'))
        self.assertIsNone(tu_kiem.mb(None))

if __name__ == '__main__':
    unittest.main()
