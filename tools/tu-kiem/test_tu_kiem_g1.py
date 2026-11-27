"""Test tu_kiem_g1.py bằng ci-results giả lập (đúng dạng file thật): đủ bằng chứng thì 19 mục đạt; test hỏng, thiếu cỡ màn
hình, chưa chạy, dung lượng vượt, số nhập lệch, hiệu năng chậm, thiếu kết quả Impeccable thì báo đúng trạng thái.
Tên test mà công cụ tìm phải có thật trong mã test (đổi tên test mà quên sửa công cụ thì test này hỏng).

Chạy: python3 -m unittest tools/tu-kiem/test_tu_kiem_g1.py
"""
import contextlib
import io
import json
import re
import sys
import tempfile
import unittest
from pathlib import Path

GOC = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
import tu_kiem_g1 as g1  # noqa: E402

CO = ('may-tinh', 'dien-thoai')
PERF_DAT = """  POST hoc/phien                 p50   10.1 ms   p95   10.8 ms   max   10.8 ms
  POST hoc/phien (Quiz)          p50   57.1 ms   p95   58.7 ms   max   58.8 ms
  GET hom-nay                    p50   38.0 ms   p95   38.8 ms   max   38.9 ms
  GET tra-tu                     p50   42.4 ms   p95   42.6 ms   max   43.2 ms"""
NOI_DUNG_DUNG = 'Nội dung: 11882 thẻ, 620 chủ đề nhỏ, 219 bài ngữ pháp, 6887 câu bài tập, 889 động từ, 10875 file audio'


def tat_ca_spec():
    """(số mục, bằng chứng) của mọi mục chấm bằng tên test."""
    for so, (_, _, specs) in g1.KIEM_TEST.items():
        for s in specs:
            yield so, s
    for t in g1.PEST_NHAP:
        yield 1, ('pest', t)
    for s in g1.TRO_NANG_THEM:
        yield 14, s
    yield 17, ('pest', g1.PEST_HIEU_NANG)


def dong_dat():
    """Bảng từng test như gon_ket_qua.py ghi: mọi test công cụ tìm đều đạt, Playwright ở cả hai cỡ màn hình."""
    ra = []
    for _, s in tat_ca_spec():
        if s[0] == 'pest':
            ra.append(['dat', 'pest', '-', 'tests/Feature/XTest.php', s[1]])
        elif s[0] in ('pest-file', 'vitest-file'):
            ra += [['dat', s[0].split('-')[0], '-', s[2], f'test {i}'] for i in range(s[3] if len(s) > 3 else 1)]
        else:
            ra += [['dat', 'playwright', co, 'tests/e2e/x.spec.ts', s[1]] for co in CO]
    return ra


def log(job, buoc=(), duoi='', status='success', run=200):
    dau = '\n'.join(f'{"✔" if ok else "✘"} {b}' for b, ok in buoc)
    return f'workflow: ci  job: {job}  status: {status}\ncommit: abcdef1234567  ref: work/x  run: {run}\n\n{dau}\n\n----- cuối log -----\n{duoi}\n'


def php_log(perf=PERF_DAT, hong=()):
    buoc = [(b, b not in hong) for b in [*g1.BUOC_DATABASE, g1.BUOC_AUDIO]]
    return log('php', buoc, f'::group::Hiệu năng\n{perf}\n')


def web_log():
    return log('web', [(g1.BUOC_TUONG_PHAN, True)], 'Tương phản: 70/70 cặp đạt WCAG 2 AA (sáng và tối)', run=201)


def audio_log(status='failure'):
    return ('workflow: audio  job: nghe-thu  status: ' + status + '\ncommit: 15a67df  ref: work/x  run: 150\n\n'
            '✔ Giọng de_DE-thorsten-medium dùng được: bộ dữ liệu Thorsten-Voice, CC0 1.0.\n'
            '✘ Giọng pavoque không được dùng để bán: bộ dữ liệu PAVOQUE theo CC BY-NC-SA 4.0 (cấm dùng thương mại).\n')


def deploy_log(run=300, nha='438M', db='40.1M', noi_dung=NOI_DUNG_DUNG):
    duoi = f'Cả thư mục nhà: {nha}\nDatabase: {db} (92 bảng)\n' + (f'{noi_dung}\n' if noi_dung else '')
    return log('deploy', [], duoi, run=run)


TAY = {'muc': [
    {'so': 1, 'nguon': 'php artisan app:nhap-lernbuch trên máy bạn (setup\\ket-qua-nhap.txt)', 'luc': '2026-11-27',
     'ket_qua': '11.882 thẻ, 620 chủ đề nhỏ, 219 bài ngữ pháp, 6.887 câu, 889 động từ', 'khop': True},
    {'so': 14, 'nguon': 'impeccable detect trên các trang đã build', 'luc': '2026-11-28', 'loi_impeccable': 0, 'luu_y_impeccable': 2},
]}


class TuKiemG1(unittest.TestCase):
    def setUp(self):
        self.dir = Path(tempfile.mkdtemp())
        dong = dong_dat()
        self.files = {
            'ket-qua/php/ket-qua.tsv': [d for d in dong if d[1] == 'pest'],
            'ket-qua/web/ket-qua.tsv': [d for d in dong if d[1] != 'pest'],
            'latest-ci-php.txt': php_log(),
            'latest-ci-web.txt': web_log(),
            'latest-audio-nghe-thu.txt': audio_log(),
            'runs/300-ci-deploy.txt': deploy_log(),
        }
        self.tay = json.loads(json.dumps(TAY))

    def chay(self):
        ci = self.dir / 'ci'
        for ten, nd in self.files.items():
            p = ci / ten
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(nd if isinstance(nd, str) else ''.join('\t'.join(d) + '\n' for d in nd), encoding='utf-8')
        tay = self.dir / 'tay.json'
        tay.write_text(json.dumps(self.tay, ensure_ascii=False), encoding='utf-8')
        out = self.dir / 'g1.json'
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            code = g1.main(['--ci', str(ci), '--tay', str(tay), '--out', str(out)])
        data = json.loads(out.read_text(encoding='utf-8')) if out.exists() else None
        return code, data, ({m['so']: m for m in data['muc']} if data else {})

    def sua_dong(self, ten_file, dieu_kien, doi):
        """Đổi (hay bỏ, khi doi trả None) các dòng của bảng test thoả điều kiện."""
        self.files[ten_file] = [x for x in ((doi(list(d)) if dieu_kien(d) else d) for d in self.files[ten_file]) if x is not None]

    # ---------- đủ bằng chứng ----------

    def test_du_bang_chung_thi_19_muc_dat(self):
        code, data, muc = self.chay()
        self.assertEqual(code, 0)
        self.assertEqual(sorted(muc), list(range(1, 20)))
        self.assertEqual({s: [m['so'] for m in muc.values() if m['trang_thai'] == s] for s in ('dat_mot_phan', 'chua_dat', 'chua_chay')},
                         {'dat_mot_phan': [], 'chua_dat': [], 'chua_chay': []})
        self.assertEqual(data['tong'], {'dat': 19, 'dat_mot_phan': 0, 'chua_dat': 0, 'chua_chay': 0})
        self.assertEqual(data['migration_moi']['trang_thai'], 'dat')
        self.assertIn('ket-qua/php/ket-qua.tsv', data['nguon_ket_qua_test'])
        # Mỗi mục có bằng chứng truy lại được
        for m in muc.values():
            self.assertTrue(m['bang_chung'], m['so'])
        self.assertIn('khớp số liệu nhập', ' '.join(muc[1]['bang_chung']))
        self.assertIn('tổng 478 MB = 47% của 1 GB', muc[18]['bang_chung'])
        self.assertIn('bộ dò Impeccable: 0 lỗi, 2 lưu ý', ' '.join(muc[14]['bang_chung']))

    def test_pest_co_bo_du_lieu_van_khop_ten(self):
        ten = 'tính đúng như bảng ca thử chung'
        self.sua_dong('ket-qua/php/ket-qua.tsv', lambda d: d[4] == ten, lambda d: [*d[:4], f'{ten} with dataset "mức 0, Quên"'])
        self.files['ket-qua/php/ket-qua.tsv'].append(['dat', 'pest', '-', 'tests/Feature/XTest.php', f'{ten} with dataset "mức 5, Dễ"'])
        _, _, muc = self.chay()
        self.assertEqual(muc[3]['trang_thai'], 'dat')

    # ---------- test hỏng, thiếu, chưa chạy ----------

    def test_mot_test_pest_hong_thi_muc_chua_dat_ma_thoat_1(self):
        ten = g1.KIEM_TEST[4][2][0][1]
        self.sua_dong('ket-qua/php/ket-qua.tsv', lambda d: d[4] == ten, lambda d: ['hong', *d[1:]])
        code, _, muc = self.chay()
        self.assertEqual(code, 1)
        self.assertEqual(muc[4]['trang_thai'], 'chua_dat')
        self.assertIn('hỏng', muc[4]['bang_chung'][0])

    def test_mot_test_trong_file_hong_thi_chua_dat(self):
        self.sua_dong('ket-qua/php/ket-qua.tsv', lambda d: d[3] == 'tests/Feature/HocOfflineTest.php' and d[4] == 'test 0',
                      lambda d: ['hong', *d[1:]])
        _, _, muc = self.chay()
        self.assertEqual(muc[15]['trang_thai'], 'chua_dat')

    def test_file_test_it_hon_so_can_thi_dat_mot_phan(self):
        self.sua_dong('ket-qua/web/ket-qua.tsv', lambda d: d[3] == 'resources/js/app/lib/offline.test.ts' and d[4] != 'test 0', lambda d: None)
        _, _, muc = self.chay()
        self.assertEqual(muc[15]['trang_thai'], 'dat_mot_phan')
        self.assertIn('chưa có kết quả', ' '.join(muc[15]['bang_chung']))

    def test_playwright_chi_dat_o_mot_co_man_hinh_thi_dat_mot_phan(self):
        ten = g1.KIEM_TEST[5][2][0][1]
        self.sua_dong('ket-qua/web/ket-qua.tsv', lambda d: d[4] == ten and d[2] == 'dien-thoai', lambda d: None)
        code, _, muc = self.chay()
        self.assertEqual(code, 0)
        self.assertEqual(muc[5]['trang_thai'], 'dat_mot_phan')
        self.assertIn('mới đạt 1 cỡ màn hình', muc[5]['bang_chung'][0])

    def test_playwright_hong_o_dien_thoai_thi_chua_dat(self):
        ten = g1.CHE_DO[3]
        self.sua_dong('ket-qua/web/ket-qua.tsv', lambda d: d[4] == ten and d[2] == 'dien-thoai', lambda d: ['hong', *d[1:]])
        _, _, muc = self.chay()
        self.assertEqual(muc[13]['trang_thai'], 'chua_dat')
        self.assertIn('hỏng ở dien-thoai', ' '.join(muc[13]['bang_chung']))

    def test_test_wcag_ngoai_danh_sach_hong_thi_muc_14_chua_dat(self):
        self.files['ket-qua/web/ket-qua.tsv'] += [
            ['dat', 'playwright', 'may-tinh', 'tests/e2e/app.spec.ts', 'trang Hôm nay: đạt WCAG 2.2 AA'],
            ['hong', 'playwright', 'dien-thoai', 'tests/e2e/app.spec.ts', 'trang Hôm nay: đạt WCAG 2.2 AA'],
        ]
        _, _, muc = self.chay()
        self.assertEqual(muc[14]['trang_thai'], 'chua_dat')
        self.assertIn('"trang Hôm nay: đạt WCAG 2.2 AA": hỏng ở dien-thoai', ' '.join(muc[14]['bang_chung']))

    def test_chua_co_bang_ket_qua_test_thi_cac_muc_test_chua_chay(self):
        del self.files['ket-qua/php/ket-qua.tsv'], self.files['ket-qua/web/ket-qua.tsv']
        code, data, muc = self.chay()
        self.assertEqual(code, 0)
        self.assertEqual(sorted(so for so, m in muc.items() if m['trang_thai'] == 'chua_chay'), sorted(g1.KIEM_TEST))
        self.assertEqual(data['nguon_ket_qua_test'], '')
        # Mục có bằng chứng khác (log máy chủ, log CI) chỉ đạt một phần
        self.assertEqual({so: muc[so]['trang_thai'] for so in (1, 14, 17)}, {1: 'dat_mot_phan', 14: 'dat_mot_phan', 17: 'dat_mot_phan'})

    def test_buoc_database_hong_thi_dieu_kien_chung_chua_dat(self):
        self.files['latest-ci-php.txt'] = php_log(hong={'Migrate xuống hết'})
        _, data, _ = self.chay()
        self.assertEqual(data['migration_moi']['trang_thai'], 'chua_dat')
        self.assertIn('5/6 bước', data['migration_moi']['bang_chung'][0])

    # ---------- giọng đọc ----------

    def test_workflow_audio_khong_dung_thi_muc_12_chua_dat(self):
        self.files['latest-audio-nghe-thu.txt'] = audio_log(status='success')
        _, _, muc = self.chay()
        self.assertEqual(muc[12]['trang_thai'], 'chua_dat')

    def test_chua_chay_thu_giong_cam_thi_muc_12_dat_mot_phan(self):
        del self.files['latest-audio-nghe-thu.txt']
        _, _, muc = self.chay()
        self.assertEqual(muc[12]['trang_thai'], 'dat_mot_phan')
        self.assertIn('pavoque', muc[12]['ghi_chu'])

    # ---------- số liệu nhập, dung lượng, hiệu năng ----------

    def test_so_lieu_tren_may_chu_lech_thi_muc_1_chua_dat(self):
        self.files['runs/300-ci-deploy.txt'] = deploy_log(noi_dung=NOI_DUNG_DUNG.replace('11882 thẻ', '11880 thẻ'))
        _, _, muc = self.chay()
        self.assertEqual(muc[1]['trang_thai'], 'chua_dat')
        self.assertIn('lệch: thẻ 11880 (cần 11882)', ' '.join(muc[1]['bang_chung']))

    def test_chua_co_so_lieu_may_chu_thi_dung_ket_qua_tay(self):
        self.files['runs/300-ci-deploy.txt'] = deploy_log(noi_dung='')
        _, _, muc = self.chay()
        self.assertEqual(muc[1]['trang_thai'], 'dat')
        self.assertIn('lần deploy tới', muc[1]['ghi_chu'])
        self.tay['muc'][0]['khop'] = False
        _, _, muc = self.chay()
        self.assertEqual(muc[1]['trang_thai'], 'chua_dat')

    def test_dung_luong_tu_60_phan_tram_thi_chua_dat(self):
        self.files['runs/300-ci-deploy.txt'] = deploy_log(nha='580M', db='40M')
        code, _, muc = self.chay()
        self.assertEqual(code, 1)
        self.assertEqual(muc[18]['trang_thai'], 'chua_dat')
        self.assertIn('60%', muc[18]['ghi_chu'])

    def test_database_do_truoc_lan_doi_dung_luong_thi_dat_mot_phan(self):
        # Workflow audio chạy sau deploy: thư mục nhà đo lại, database chưa
        self.files['runs/300-ci-deploy.txt'] = deploy_log(run=300, nha='370M', db='36.2M')
        self.files['latest-audio-tao-het.txt'] = log('tao-het', [], 'Audio trên máy chủ: 68M\nCả thư mục nhà: 438M', run=400)
        _, _, muc = self.chay()
        self.assertEqual(muc[18]['trang_thai'], 'dat_mot_phan')
        self.assertIn('thư mục nhà 438 MB (latest-audio-tao-het.txt, run 400), trong đó audio 68 MB', muc[18]['bang_chung'])

    def test_mot_lan_chay_co_hai_ban_log_chi_tinh_mot(self):
        self.files['latest-ci-deploy.txt'] = deploy_log(run=300)
        logs = None
        self.chay()
        logs = g1.doc_log_may_chu(self.dir / 'ci')
        self.assertEqual([(l.run, l.name) for l in logs], [('150', 'latest-audio-nghe-thu.txt'), ('300', '300-ci-deploy.txt')])

    def test_chua_co_log_may_chu_thi_muc_18_chua_chay(self):
        del self.files['runs/300-ci-deploy.txt']
        _, _, muc = self.chay()
        self.assertEqual(muc[18]['trang_thai'], 'chua_chay')

    def test_hieu_nang_p95_cham_thi_muc_17_chua_dat(self):
        self.files['latest-ci-php.txt'] = php_log(perf=PERF_DAT.replace('p95   10.8 ms', 'p95  151.0 ms'))
        _, _, muc = self.chay()
        self.assertEqual(muc[17]['trang_thai'], 'chua_dat')
        self.files['latest-ci-php.txt'] = php_log(perf=PERF_DAT.replace('p95   42.6 ms', 'p95  200.0 ms'))
        _, _, muc = self.chay()
        self.assertEqual(muc[17]['trang_thai'], 'chua_dat')

    def test_khong_thay_bang_hieu_nang_thi_dat_mot_phan(self):
        self.files['latest-ci-php.txt'] = php_log(perf='')
        _, _, muc = self.chay()
        self.assertEqual(muc[17]['trang_thai'], 'dat_mot_phan')

    # ---------- kết quả thử tay ----------

    def test_thieu_ket_qua_impeccable_thi_muc_14_dat_mot_phan(self):
        self.tay['muc'] = [m for m in self.tay['muc'] if m['so'] != 14]
        _, _, muc = self.chay()
        self.assertEqual(muc[14]['trang_thai'], 'dat_mot_phan')
        self.assertIn('Impeccable', muc[14]['ghi_chu'])

    def test_impeccable_con_loi_thi_muc_14_chua_dat(self):
        self.tay['muc'][1]['loi_impeccable'] = 3
        _, _, muc = self.chay()
        self.assertEqual(muc[14]['trang_thai'], 'chua_dat')

    def test_ket_qua_tay_thieu_nguon_thi_ma_thoat_2(self):
        self.tay['muc'][1]['nguon'] = ' '
        code, data, _ = self.chay()
        self.assertEqual(code, 2)
        self.assertIsNone(data)


class TenTestCoThat(unittest.TestCase):
    """Mọi tên test, file test mà công cụ tìm phải có trong mã test của repo."""

    @classmethod
    def setUpClass(cls):
        doc = lambda ds: {p: p.read_text(encoding='utf-8').replace("\\'", "'") for p in ds}
        cls.php = doc(GOC.glob('tests/**/*.php'))
        cls.e2e = doc(GOC.glob('tests/e2e/*.ts'))
        # Tên test viết bằng chuỗi mẫu test(`...${x}...`): phần ${...} khớp chữ bất kỳ
        cls.mau = [re.compile(''.join('.+?' if re.fullmatch(r'\$\{[^}]*\}', p) else re.escape(p) for p in re.split(r'(\$\{[^}]*\})', m)))
                   for c in cls.e2e.values() for m in re.findall(r'\b(?:test|it|describe)(?:\.\w+)*\(\s*`([^`]*\$\{[^`]*)`', c)]

    def co_trong_e2e(self, ten):
        return any(ten in c for c in self.e2e.values()) or any(m.fullmatch(ten) for m in self.mau)

    def test_ten_test_pest_va_playwright(self):
        thieu = []
        for so, s in tat_ca_spec():
            if s[0] == 'pest' and not any(s[1] in c for c in self.php.values()):
                thieu.append((so, s[1]))
            if s[0] == 'pw':
                thieu += [(so, phan) for phan in s[1].split(' › ') if not self.co_trong_e2e(phan)]
        self.assertEqual(thieu, [])

    def test_file_test_co_du_so_test(self):
        thieu = []
        for so, s in tat_ca_spec():
            if s[0] not in ('pest-file', 'vitest-file'):
                continue
            p = GOC / s[2]
            n = len(re.findall(r'^\s*(?:it|test)\(', p.read_text(encoding='utf-8'), re.M)) if p.is_file() else 0
            if n < (s[3] if len(s) > 3 else 1):
                thieu.append((so, s[2], n))
        self.assertEqual(thieu, [])

    def test_chuoi_mau_khop_ten_that(self):
        self.assertTrue(self.co_trong_e2e('trang quản trị (tối): không lỗi, không tràn ngang, đạt WCAG 2.2 AA'))
        self.assertFalse(self.co_trong_e2e('trang quản trị (tối): tên đã đổi'))


if __name__ == '__main__':
    unittest.main()
