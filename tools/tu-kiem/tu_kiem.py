"""Tự kiểm thử G0: chấm 16 mục của kế hoạch G0 từ bằng chứng có thật, ghi kết quả ra JSON.

Bằng chứng lấy từ:
  --ci    thư mục bản sao nhánh ci-results: latest-*.txt (log job mới nhất) và runs/*-ci-deploy.txt,
          runs/*-rollback-rollback.txt (log máy chủ của từng lần deploy và quay lại, cho mục 7–10, 13, 16)
  --runs  JSON danh sách lần chạy GitHub Actions kèm job (tools/tu-kiem/lay_runs.py)
  --tay   JSON kết quả đo trên máy chủ mà log CI không có (mục 7: đăng nhập /admin; mục 13: thử hàng đợi);
          mỗi mục ghi nguồn, công cụ tự so với ngưỡng

Dùng:
  git clone --depth 1 -b ci-results <repo> /tmp/ci-results
  python3 tools/tu-kiem/lay_runs.py --out /tmp/runs.json
  python3 tools/tu-kiem/tu_kiem.py --ci /tmp/ci-results --runs /tmp/runs.json --tay docs/tu-kiem-g0-tay.json --out docs/tu-kiem-g0.json

Trạng thái mỗi mục: dat (đạt), dat_mot_phan (phần kiểm được đã đạt, còn phần chờ điều kiện),
chua_dat (có bằng chứng hỏng), chua_chay (chưa có bằng chứng). Mã thoát 1 nếu có mục chua_dat.
"""
import argparse
import json
import math
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

ANSI = re.compile(r'\x1b\[[0-9;]*m')
DAT, MOT_PHAN, CHUA_DAT, CHUA_CHAY = 'dat', 'dat_mot_phan', 'chua_dat', 'chua_chay'

MIGRATION_STEPS = ['Migrate lên', 'Migrate xuống hết', 'Sau khi xuống chỉ còn bảng migrations', 'Migrate lên lại']
REQUIRED_DB = [('MariaDB', '11.4'), ('MariaDB', '11.8'), ('MySQL', '8.4')]
DEPLOY_NEEDS = 'Cần bật deploy: khoá SSH alwaysdata trong Secrets (ALWAYSDATA_SSH_KEY, DB_PASSWORD) và biến DEPLOY_ENABLED=true (docs/huong-dan-alwaysdata.md).'


# ---------- đọc log ----------

class Log:
    """Một file latest-*.txt của ci-results."""

    def __init__(self, name, text):
        self.name = name
        self.text = ANSI.sub('', text)
        head = self.text.split('----- cuối log -----')[0]
        self.steps = {}
        for line in head.splitlines():
            m = re.match(r'^([✔✘]) (.+)$', line.strip())
            if m and not m.group(2).startswith('lỗi:'):
                self.steps[m.group(2).strip()] = m.group(1) == '✔'
        m = re.search(r'status: (\S+)', self.text)
        self.status = m.group(1) if m else ''
        m = re.search(r'commit: ([0-9a-f]{7,40})', self.text)
        self.commit = m.group(1)[:7] if m else ''
        m = re.search(r'run: (\d+)', self.text)
        self.run = m.group(1) if m else ''
        m = re.search(r'Máy chủ database: (.+)', self.text)
        self.server = m.group(1).strip() if m else ''

    def ok(self, step):
        return self.steps.get(step) is True

    def ref(self):
        return f'{self.name} (commit {self.commit}, run {self.run})'

    def results(self):
        """Các dòng kết quả test của Pest: (đạt?, tên, bị cắt?). Pest cắt tên dài bằng dấu … theo bề rộng cửa sổ."""
        out = []
        for line in self.text.splitlines():
            m = re.match(r'^\s*([✓⨯✗])\s+(?:it\s+)?(.+?)(?:\s+[\d.]+m?s)?\s*$', line)
            if m:
                name = m.group(2).strip()
                out.append((m.group(1) == '✓', name.rstrip('…').rstrip(), name.endswith('…')))
        return out

    def _match(self, title, ok):
        for passed, name, cut in self.results():
            if passed != ok:
                continue
            if name == title or name.startswith(title + ' with ') or (cut and len(name) >= 20 and title.startswith(name)):
                return True
        return False

    def passed_test(self, title):
        """Có dòng Pest đạt cho tên test này (kể cả dòng dữ liệu "with (...)" và tên bị cắt)."""
        return self._match(title, True)

    def failed_test(self, title):
        return self._match(title, False)

    def passed_test_count(self, title):
        """Số dòng Playwright đạt có tiêu đề này (mỗi cỡ màn hình một dòng)."""
        pat = re.compile(r'✓\s+\d+\s+\[[^\]]+\]\s+›\s+\S+\s+›\s+' + re.escape(title))
        return len(pat.findall(self.text))


def load_logs(ci_dir):
    logs = {}
    for p in sorted(Path(ci_dir).glob('latest-*.txt')):
        logs[p.stem.removeprefix('latest-')] = Log(p.name, p.read_text(encoding='utf-8', errors='replace'))
    return logs


def schema_report(log):
    """JSON của test_schema.py nằm trong log job php."""
    if not log:
        return None
    i = log.text.find('::group::test_schema.py')
    j = log.text.find('{', i) if i >= 0 else -1
    if j < 0:
        return None
    depth = 0
    for k in range(j, len(log.text)):
        c = log.text[k]
        if c == '{':
            depth += 1
        elif c == '}':
            depth -= 1
            if depth == 0:
                try:
                    return json.loads(log.text[j:k + 1])
                except json.JSONDecodeError:
                    return None
    return None


def db_family(server):
    m = re.match(r'(\d+)\.(\d+)', server)
    if not m:
        return None
    return ('MariaDB' if 'mariadb' in server.lower() else 'MySQL', f'{m.group(1)}.{m.group(2)}')


# ---------- lần chạy ----------

def minutes(job):
    try:
        a = datetime.fromisoformat(job['started'].replace('Z', '+00:00'))
        b = datetime.fromisoformat(job['completed'].replace('Z', '+00:00'))
    except (KeyError, TypeError, AttributeError, ValueError):
        return 0
    if job.get('conclusion') == 'skipped' or b <= a:
        return 0
    return math.ceil((b - a).total_seconds() / 60)


def billable(run):
    """GitHub tính tròn lên từng phút mỗi job; máy Windows nhân 2."""
    mult = 2 if run['workflow'] == 'windows' else 1
    return sum(minutes(j) for j in run['jobs']) * mult


def job(run, prefix):
    return next((j for j in run['jobs'] if j['name'].startswith(prefix)), None)


# ---------- 16 mục ----------

def item(so, ten, dat_khi, trang_thai, bang_chung, ghi_chu=''):
    return {'so': so, 'ten': ten, 'dat_khi': dat_khi, 'trang_thai': trang_thai, 'bang_chung': bang_chung, 'ghi_chu': ghi_chu}


def check(logs, runs, assumptions, tay=None, slogs=()):
    tay = tay or {}
    php = logs.get('ci-php')
    web = logs.get('ci-web')
    db_logs = [l for k, l in logs.items() if k == 'ci-php' or k.startswith('nightly-php')]
    out = []

    # 1. migration lên, xuống trên các bản database
    # Mỗi bản database lấy log mới nhất (ci-results còn giữ file của ma trận cũ)
    newest = {}
    for l in db_logs:
        fam = db_family(l.server)
        if fam and (fam not in newest or int(l.run or 0) > int(newest[fam].run or 0)):
            newest[fam] = l
    ok_db, bad_db = [], []
    for fam, l in newest.items():
        (ok_db if all(l.ok(s) for s in MIGRATION_STEPS) else bad_db).append((fam, l))
    have = {f for f, _ in ok_db}
    missing = [f'{a} {b}' for a, b in REQUIRED_DB if (a, b) not in have]
    ev = [f'{a} {b}: {", ".join(MIGRATION_STEPS)} đạt ({l.ref()})' for (a, b), l in sorted(ok_db, key=lambda x: x[0])]
    ev += [f'{a} {b}: có bước hỏng ({l.ref()})' for (a, b), l in bad_db]
    st = CHUA_DAT if bad_db else (DAT if not missing else (CHUA_CHAY if not ok_db else MOT_PHAN))
    out.append(item(1, 'migration lên, xuống', 'không lỗi trên MariaDB 11.4, 11.8 và MySQL 8.4; sau khi rollback chỉ còn bảng migrations',
                    st, ev, ('Chưa có bằng chứng cho: ' + ', '.join(missing)) if missing else ''))

    # 2. khớp schema.sql, kèm lần sửa cố ý
    s1, s2 = 'Khớp với schema.sql (0 khác biệt)', 'Test công cụ so khớp và quy ước'
    st = DAT if php and php.ok(s1) and php.ok(s2) else (CHUA_DAT if php and (s1 in php.steps or s2 in php.steps) else CHUA_CHAY)
    out.append(item(2, 'khớp với schema.sql', '0 khác biệt; sửa cố ý kiểu một cột thì công cụ chỉ đúng cột đó', st,
                    [f'{s1}; {s2} (test_compare_schema.py: test_2_changed_column_type_is_reported_exactly) ({php.ref()})'] if php else []))

    # 3. quy ước LoWo, kèm khoá ngoại cố ý
    s1 = 'Quy ước LoWo (không khoá ngoại, thời gian số nguyên)'
    st = DAT if php and php.ok(s1) and php.ok(s2) else (CHUA_DAT if php and s1 in php.steps else CHUA_CHAY)
    out.append(item(3, 'quy ước LoWo', 'không khoá ngoại, cột thời gian đúng kiểu; thêm cố ý một khoá ngoại thì bị bắt', st,
                    [f'{s1}; test_check_conventions.py: test_foreign_key_in_migration_is_caught ({php.ref()})'] if php else []))

    # 4. seeder chạy lại
    s1 = 'Seeder chạy hai lần không nhân đôi'
    m = re.search(r'lần 1: ([\d ]+)\| lần 2: ([\d ]+)', php.text) if php else None
    counts = m.group(1).split()[:3] if m else []
    st = DAT if php and php.ok(s1) and counts == ['5', '40', '10'] else (CHUA_DAT if php and s1 in php.steps else CHUA_CHAY)
    out.append(item(4, 'seeder chạy lại', 'số dòng không đổi: 5 gói, 40 quyền lợi, 10 prompt', st,
                    [f'{s1}: lần 1 = {m.group(1).strip()}, lần 2 = {m.group(2).strip()} ({php.ref()})'] if m else []))

    # 5. test_schema.py
    rep = schema_report(php)
    if rep:
        c = sum(1 for x in rep.get('checks', []) if x.get('ok')), len(rep.get('checks', []))
        b = sum(1 for x in rep.get('behaviour', []) if x.get('ok')), len(rep.get('behaviour', []))
        st = DAT if c == (16, 16) and b == (22, 22) else CHUA_DAT
        ev = [f'{c[0]}/{c[1]} kiểm tra cấu trúc, {b[0]}/{b[1]} tình huống trên {rep.get("server", "?")}, {rep.get("table_count", "?")} bảng ({php.ref()})']
    else:
        st, ev = CHUA_CHAY, []
    out.append(item(5, 'kiểm thử database cũ', '16/16 kiểm tra cấu trúc, 22/22 tình huống', st, ev))

    # 6. CI chặn lỗi
    red = [r for r in runs if r['workflow'] == 'ci' and r['conclusion'] == 'failure'
           and any((j['conclusion'] == 'failure') for j in r['jobs'])]
    red_ok = [r for r in red if (d := job(r, 'deploy')) is not None and d['conclusion'] in ('skipped', None)]
    # Ưu tiên lần đẩy test cố ý hỏng (tên commit ghi "cố ý" hoặc "CI đỏ") hơn lần hỏng ngoài ý muốn
    red_ok = [r for r in red_ok if re.search(r'cố ý|CI đỏ', r['title'])] or red_ok
    st = DAT if red_ok else CHUA_CHAY
    out.append(item(6, 'CI chặn lỗi', 'CI đỏ, job deploy không chạy', st,
                    [f'run {r["id"]} ({r["branch"]}, "{r["title"]}"): '
                     + ', '.join(f'{j["name"].split(" (")[0]} {j["conclusion"]}' for j in r['jobs']) for r in red_ok[:2]]))

    # 7–10. deploy và quay lại trên alwaysdata: đọc log máy chủ của từng lần (ci-results/runs)
    out.append(deploy_item(slogs, tay.get(7)))
    out.append(migration_fail_item(slogs))
    out.append(auto_rollback_item(slogs))
    out.append(manual_rollback_item(slogs))

    # 11. bảo mật cơ bản (Pest)
    sec = ['chưa đăng nhập vào /admin thì về trang đăng nhập', 'không lộ file .env và log',
           'lỗi máy chủ chỉ hiện trang lỗi chung, không lộ nội dung lỗi hay đường dẫn',
           'nhập sai mật khẩu 5 lần thì bị chặn tạm thời, kể cả lần sau đúng mật khẩu']
    out.append(tests_item(11, 'bảo mật cơ bản', 'trang lỗi chung, /.env và /storage/logs trả 404, /admin về trang đăng nhập, sai mật khẩu nhiều lần bị chặn', php, sec))

    # 12. chữ có dấu qua database (Pest, có kết nối sai bảng mã)
    utf = ['ghi rồi đọc chữ có dấu qua database giữ nguyên', 'kết nối sai bảng mã thì chữ có dấu đọc ra bị hỏng, nên test bắt được', 'kết nối database dùng utf8mb4']
    out.append(tests_item(12, 'chữ có dấu qua database', 'đọc lại giống hệt; kết nối sai thì test báo lỗi', php, utf))

    # 13. lịch và hàng đợi: CI mô phỏng; chạy thật 10 phút cần máy chủ có lịch và worker
    q = ['lịch có việc ghi nhịp chạy mỗi phút', 'job thử chạy xong qua hàng đợi database', 'job cố ý lỗi vào failed_jobs sau đúng 3 lần thử',
         '10 phút lịch chạy mỗi phút qua hàng đợi thì đủ 10 nhịp', 'thiếu nhịp (lịch hay hàng đợi dừng) thì kiểm nhịp báo lỗi']
    it13 = tests_item(13, 'lịch và hàng đợi', 'sau 10 phút có ít nhất 9 nhịp; job thử chạy xong; job lỗi vào failed_jobs sau 3 lần thử', php, q)
    if it13['trang_thai'] == DAT:
        it13 = server_queue(it13, slogs, tay.get(13))
    out.append(it13)

    # 14. font và chữ (Playwright, có mất file font cố ý)
    fonts = ['mọi mẫu chữ hiện đúng font, đủ dấu tiếng Việt và ä ö ü ß ẞ', 'thiếu file font: phép kiểm báo lỗi, chữ vẫn đọc được bằng font dự phòng']
    ev, ok = [], bool(web)
    for t in fonts:
        n = web.passed_test_count(t) if web else 0
        ok = ok and n >= 2
        ev.append(f'"{t}": đạt {n}/2 cỡ màn hình')
    if web:
        ev.append(web.ref())
    out.append(item(14, 'font và chữ', 'đủ dấu tiếng Việt và ä ö ü ß ẞ đúng font ở máy tính và điện thoại; thiếu file thì test báo, chữ vẫn đọc được',
                    DAT if ok else (CHUA_DAT if web else CHUA_CHAY), ev))

    # 15. tương phản màu
    s1, s2 = 'Tương phản màu WCAG AA (palette_check.py trên tokens.css)', 'Test palette_check (cả trường hợp lỗi)'
    m = re.search(r'Tương phản: (\d+)/(\d+)', web.text) if web else None
    st = DAT if web and web.ok(s1) and web.ok(s2) and m and m.group(1) == m.group(2) else (CHUA_DAT if web and s1 in web.steps else CHUA_CHAY)
    out.append(item(15, 'tương phản màu', 'mọi cặp chữ và nền đạt WCAG AA', st,
                    [f'{m.group(0)} cặp đạt; {s2} đạt ({web.ref()})'] if m else []))

    # 16. phút CI và dung lượng alwaysdata (log deploy)
    out.append(ci_minutes_item(runs, assumptions, slogs))
    return out


# ---------- log máy chủ: deploy và quay lại (ci-results/runs) ----------

def mb(size):
    """Cỡ in bởi du -h (đơn vị 1024) ra MB: '107M' → 107, '1.3G' → 1331.2, '15K' → 0.015."""
    m = re.fullmatch(r'(\d+(?:[.,]\d+)?)([KMGT]?)', (size or '').strip())
    if not m:
        return None
    return float(m.group(1).replace(',', '.')) * {'': 1 / 1048576, 'K': 1 / 1024, 'M': 1, 'G': 1024, 'T': 1048576}[m.group(2)]


class ServerLog(Log):
    """runs/<số>-ci-deploy.txt hay runs/<số>-rollback-rollback.txt: những gì máy chủ và bước kiểm in ra trong một lần deploy hay quay lại."""

    def __init__(self, name, text):
        super().__init__(name, text)
        self.kind = 'rollback' if '-rollback-' in name else 'deploy'
        t = self.text.split('----- cuối log -----', 1)[-1]

        def one(pat):
            m = re.search(pat, t, re.M)
            return m.group(1) if m else None

        def groups(pat):
            m = re.search(pat, t, re.M)
            return m.groups() if m else None

        self.release = one(r'^== Giải nén (\S+)')
        self.switched = one(r'^== Đổi current sang (\S+)')
        self.kept = one(r'^✘ Deploy dừng, giữ nguyên bản đang chạy: (\S+)')
        heads = re.findall(r'^== (\S+)', t[:t.find('✘ Deploy dừng')], re.M) if self.kept else []
        self.stopped_at = heads[-1] if heads else None
        # Migration hỏng: có dòng FAIL trong phần "== Migration" (tới bước kế), hoặc deploy dừng ngay ở bước đó
        i = t.find('== Migration')
        ends = [x for x in (t.find('\n== ', i + 1), t.find('✘ Deploy dừng', i)) if x >= 0] if i >= 0 else []
        sec = t[i:min(ends)] if ends else (t[i:] if i >= 0 else '')
        self.migration_failed = bool(re.search(r'\bFAIL\s*$', sec, re.M)) or self.stopped_at == 'Migration'
        self.before_up = one(r'^Trước deploy: /up trả (\S+)')
        self.before_current = one(r'^Bản đang chạy: (\S+)')
        self.release_dirs = re.findall(r'^Dung lượng: \S+\s+releases/(\S+)', t, re.M)
        self.web_on = one(r'^✔ Web đã chạy bản (\S+)')
        self.up_ok = one(r'^✔ /up trả 200 \((\S+)\)')
        self.web_timeout = bool(re.search(r'^✘ Sau \d+ giây trang chủ vẫn chưa báo bản', t, re.M))
        self.public_up = one(r'^✘ /up trả (\S+), trang chủ trả')
        self.app_up = one(r'^app tự trả /up: (\S+)')
        self.rolled = groups(r'^Đã quay lại: (\S+) -> (\S+)')
        self.after = groups(r'^Sau khi quay lại: web chạy (?:Bản )?(\S+), /up trả (\S+)')
        self.beats = groups(r'^Nhịp trong (\d+) phút qua: (\d+)/\d+')
        # Thư mục app: dòng "Thư mục app: 337M" (từ 27/11) hoặc dòng du cũ "337M<tab>/home/<tài khoản>/lernbuch"
        self.app_mb = mb(one(r'^Thư mục app: (\S+)') or one(r'^(\S+)\t\S*/lernbuch$'))
        self.home_mb = mb(one(r'^Cả thư mục nhà: (\S+)'))
        self.db_mb = mb(one(r'^Database: (\S+) \('))

    def ref(self):
        return f'run {self.run}, commit {self.commit}'

    @property
    def current_after(self):
        """Bản current trỏ tới khi lần này kết thúc."""
        if self.rolled:
            return self.rolled[1]
        return self.kept or self.switched

    @property
    def new_broken(self):
        """Bản mới đúng là hỏng: app tự trả /up khác 200 trên máy chủ, hoặc /up trên web trả 5xx."""
        return (self.app_up is not None and self.app_up != '200') or (self.public_up or '').startswith('5')


def load_server_logs(ci_dir):
    """Log máy chủ của từng lần deploy và quay lại, cũ trước mới sau (số run tăng theo thời gian)."""
    out = [ServerLog(p.name, p.read_text(encoding='utf-8', errors='replace'))
           for p in Path(ci_dir).glob('runs/*.txt') if p.name.endswith(('-ci-deploy.txt', '-rollback-rollback.txt'))]
    return sorted(out, key=lambda l: int(l.run or 0))


# ---------- kết quả đo trên máy chủ mà log CI không có (--tay) ----------

def load_tay(path):
    """{số mục: bản ghi}. Mỗi bản ghi phải có 'nguon' (lệnh đã chạy, ở đâu) để truy lại."""
    if not path:
        return {}
    data = json.loads(Path(path).read_text(encoding='utf-8'))
    out = {}
    for e in data.get('muc', []):
        if not str(e.get('nguon', '')).strip():
            raise ValueError(f'bằng chứng tay mục {e.get("so")} thiếu "nguon"')
        out[int(e['so'])] = e
    return out


def tay_ref(e):
    return f'{e["nguon"]}, {e.get("luc", "?")}'


# ---------- mục 7–10 ----------

def deploy_item(slogs, e):
    ten, dat_khi = 'deploy thật', '/up trả 200; trang chào hiện; đăng nhập được /admin'
    ok = [l for l in slogs if l.kind == 'deploy' and l.status == 'success' and l.up_ok and l.web_on == l.up_ok]
    if not ok:
        return item(7, ten, dat_khi, CHUA_CHAY, [], DEPLOY_NEEDS)
    ev = [f'{l.ref()}: trang chào hiện "Bản {l.web_on}", /up trả 200' for l in reversed(ok[-2:])]
    admin = (e or {}).get('dang_nhap_admin')
    if admin is True:
        return item(7, ten, dat_khi, DAT, ev + [f'đăng nhập /admin được: {tay_ref(e)}'])
    if admin is False:
        return item(7, ten, dat_khi, CHUA_DAT, ev + [f'đăng nhập /admin không được: {tay_ref(e)}'], 'Không đạt: không đăng nhập được /admin.')
    return item(7, ten, dat_khi, MOT_PHAN, ev,
                'Còn đăng nhập /admin: SSH vào alwaysdata, chạy cd ~/lernbuch/current && php artisan app:create-admin, '
                'rồi đăng nhập ở https://lernbuch.alwaysdata.net/admin.')


def migration_fail_item(slogs):
    ten, dat_khi = 'migration lỗi khi deploy', 'deploy dừng; current vẫn là bản cũ; trang vẫn chạy'
    deploys = [l for l in slogs if l.kind == 'deploy']
    hong = [l for l in deploys if l.migration_failed]
    if not hong:
        return item(8, ten, dat_khi, CHUA_CHAY, [], DEPLOY_NEEDS + ' Sau đó đẩy lên main một migration cố ý hỏng.')
    l = hong[-1]
    ev = [f'{l.ref()}: migration của bản {l.release} hỏng' + (f', deploy dừng ở bước Migration, giữ bản {l.kept}' if l.kept else '')]
    bad = []
    if not l.kept or l.stopped_at != 'Migration':
        bad.append('deploy không dừng ngay ở bước Migration')
    if l.switched:
        bad.append(f'vẫn đổi current sang {l.switched}')
    if l.status != 'failure':
        bad.append(f'job deploy báo {l.status}, không báo lỗi')
    if bad:
        return item(8, ten, dat_khi, CHUA_DAT, ev, 'Không đạt: ' + '; '.join(bad))
    prev = next((x for x in reversed(deploys) if int(x.run) < int(l.run) and x.current_after), None)
    if prev and prev.current_after == l.kept:
        ev.append(f'đúng là bản đang chạy sau lần deploy trước ({prev.ref()})')
    elif prev:
        bad.append(f'bản giữ lại {l.kept} khác bản đang chạy trước đó {prev.current_after}')
    nxt = next((x for x in deploys if int(x.run) > int(l.run)), None)
    if not nxt or not nxt.before_current:
        st = CHUA_DAT if bad else MOT_PHAN
        return item(8, ten, dat_khi, st, ev, ('Không đạt: ' + '; '.join(bad)) if bad else
                    'Chưa có lần deploy sau ghi trạng thái (bản đang chạy, /up) để xác nhận trang vẫn chạy bản cũ.')
    ev.append(f'lần deploy sau ({nxt.ref()}), trước khi deploy: bản đang chạy {nxt.before_current}, /up trả {nxt.before_up}')
    if nxt.before_current != l.kept:
        bad.append(f'lần sau thấy bản đang chạy là {nxt.before_current}, không phải {l.kept}')
    if nxt.before_up != '200':
        bad.append(f'/up trả {nxt.before_up}')
    if l.release and nxt.release_dirs:
        if l.release in nxt.release_dirs:
            bad.append(f'bản dở dang {l.release} chưa bị xoá')
        else:
            ev.append(f'bản dở dang {l.release} đã bị xoá (không còn trong releases/)')
    return item(8, ten, dat_khi, CHUA_DAT if bad else DAT, ev, ('Không đạt: ' + '; '.join(bad)) if bad else '')


def auto_rollback_item(slogs):
    ten, dat_khi = 'bản mới lỗi sau khi đổi', 'workflow tự đổi current về bản trước'
    lui = [l for l in slogs if l.kind == 'deploy' and l.rolled]
    if not lui:
        return item(9, ten, dat_khi, CHUA_CHAY, [], DEPLOY_NEEDS + ' Sau đó đẩy lên main một bản cố ý làm /up lỗi.')
    # Ưu tiên lần bản mới đúng là hỏng hơn lần quay lại vì lý do khác (web chưa đổi bản, cấu hình site)
    l = ([x for x in lui if x.new_broken] or lui)[-1]
    ev = [f'{l.ref()}: đổi current sang {l.switched}']
    if l.web_timeout:
        ev.append('sau 240 giây web vẫn chạy bản cũ (PHP còn nhớ đường dẫn current cũ)')
    if l.public_up and l.public_up != '?':
        ev.append(f'/up trên web trả {l.public_up}')
    if l.app_up:
        ev.append(f'app tự trả /up ngay trên máy chủ: {l.app_up}')
    ev.append(f'workflow tự quay lại: {l.rolled[0]} -> {l.rolled[1]}')
    if not l.after:
        return item(9, ten, dat_khi, MOT_PHAN, ev, 'Log không ghi bản web chạy và /up sau khi quay lại.')
    ev.append(f'sau khi quay lại: web chạy bản {l.after[0]}, /up trả {l.after[1]}')
    bad = []
    if l.switched and l.rolled[0] != l.switched:
        bad.append(f'quay lại từ {l.rolled[0]}, không phải từ bản mới {l.switched}')
    if l.after[0] != l.rolled[1]:
        bad.append(f'web chạy {l.after[0]}, không phải {l.rolled[1]}')
    if l.after[1] != '200':
        bad.append(f'/up trả {l.after[1]}')
    if bad:
        return item(9, ten, dat_khi, CHUA_DAT, ev, 'Không đạt: ' + '; '.join(bad))
    if not l.new_broken:
        return item(9, ten, dat_khi, MOT_PHAN, ev, 'Đã tự quay lại, nhưng log không cho thấy bản mới hỏng: cần một lần đẩy bản cố ý làm /up lỗi.')
    note = ('Lúc thử, web còn chạy bản cũ (site chưa có 2 dòng php.ini) nên lỗi của bản mới được bắt bằng /up gọi ngay trên máy chủ; '
            'workflow vẫn tự quay lại.') if l.web_timeout else ''
    return item(9, ten, dat_khi, DAT, ev, note)


def manual_rollback_item(slogs):
    ten, dat_khi = 'quay lại bằng tay', 'bản trước chạy, /up trả 200'
    rb = [l for l in slogs if l.kind == 'rollback']
    if not rb:
        return item(10, ten, dat_khi, CHUA_CHAY, [],
                    'Chạy workflow rollback: GitHub → Actions → rollback → Run workflow. Xong thì Actions → ci → Run workflow trên main để về bản mới nhất.')
    l = rb[-1]
    ev = [f'{l.ref()}: workflow rollback {l.status}']
    if l.rolled:
        ev.append(f'quay lại: {l.rolled[0]} -> {l.rolled[1]}')
    if l.after:
        ev.append(f'sau khi quay lại: web chạy bản {l.after[0]}, /up trả {l.after[1]}')
    if not l.rolled:
        return item(10, ten, dat_khi, CHUA_DAT, ev, 'Không đạt: workflow không đổi được current (xem log run).')
    if not l.after:
        return item(10, ten, dat_khi, MOT_PHAN, ev, 'Log không ghi bản web chạy và /up sau khi quay lại.')
    bad = []
    if l.after[0] != l.rolled[1]:
        bad.append(f'web chạy {l.after[0]}, không phải {l.rolled[1]}')
    if l.after[1] != '200':
        bad.append(f'/up trả {l.after[1]}')
    return item(10, ten, dat_khi, CHUA_DAT if bad else DAT, ev, ('Không đạt: ' + '; '.join(bad)) if bad else '')


def server_queue(it13, slogs, e):
    """Mục 13 trên máy chủ. Nhịp: log deploy mới nhất có dòng app:kiem-nhip, không có thì lấy kết quả tay.
    Job thử và job cố ý lỗi: kết quả tay (app:thu-hang-doi)."""
    e = e or {}
    bl = next((l for l in reversed(slogs) if l.beats), None)
    if bl:
        phut, co, nguon = int(bl.beats[0]), int(bl.beats[1]), f'app:kiem-nhip trước lần deploy {bl.ref()}'
    elif isinstance(e.get('nhip'), int):
        phut, co, nguon = int(e.get('trong_phut', 10)), e['nhip'], f'app:kiem-nhip; {tay_ref(e)}'
    else:
        phut = co = nguon = None
    hang_doi = 'job_loi_so_lan_thu' in e
    if co is None and not hang_doi:
        it13['trang_thai'] = MOT_PHAN
        it13['ghi_chu'] = ('CI đã chạy lịch và hàng đợi thật (bảng jobs) qua 10 phút mô phỏng. Còn chạy thật 10 phút trên alwaysdata: '
                           'sau khi bật tác vụ schedule:run và queue:work, chạy php artisan app:kiem-nhip.')
        return it13
    bad, wait = [], []
    if co is None:
        wait.append('đếm nhịp trên máy chủ (php artisan app:kiem-nhip)')
    else:
        it13['bang_chung'].append(f'trên máy chủ: {co}/{phut} nhịp ({nguon})')
        if co < max(1, phut - 1):
            bad.append(f'chỉ {co}/{phut} nhịp, cần ít nhất {max(1, phut - 1)}')
    if not hang_doi:
        wait.append('thử hàng đợi trên máy chủ (php artisan app:thu-hang-doi, rồi lệnh --kiem=<mã> nó in ra)')
    else:
        xong, lan, vao = e.get('job_thu_xong') is True, e.get('job_loi_so_lan_thu'), e.get('job_loi_vao_failed_jobs') is True
        it13['bang_chung'].append(f'trên máy chủ: job thử {"chạy xong" if xong else "chưa chạy xong"}; job cố ý lỗi thử {lan} lần, '
                                  f'{"đã" if vao else "chưa"} vào failed_jobs (app:thu-hang-doi; {tay_ref(e)})')
        if not xong:
            bad.append('job thử chưa chạy xong')
        if lan != 3:
            bad.append(f'job lỗi thử {lan} lần, cần đúng 3')
        if not vao:
            bad.append('job lỗi chưa vào failed_jobs')
    if bad:
        it13['trang_thai'], it13['ghi_chu'] = CHUA_DAT, 'Không đạt trên máy chủ: ' + '; '.join(bad)
    elif wait:
        it13['trang_thai'], it13['ghi_chu'] = MOT_PHAN, 'Còn ' + '; '.join(wait) + '.'
    else:
        it13['trang_thai'], it13['ghi_chu'] = DAT, ''
    return it13


def tests_item(so, ten, dat_khi, log, titles):
    if not log:
        return item(so, ten, dat_khi, CHUA_CHAY, [])
    ev, all_ok, any_fail = [], True, False
    for t in titles:
        if log.passed_test(t):
            ev.append(f'Pest đạt: {t}')
        else:
            all_ok = False
            any_fail = any_fail or log.failed_test(t)
            ev.append(f'Pest {"HỎNG" if log.failed_test(t) else "không thấy"}: {t}')
    ev.append(log.ref())
    return item(so, ten, dat_khi, DAT if all_ok else (CHUA_DAT if any_fail else CHUA_CHAY), ev)


def ci_minutes_item(runs, a, slogs=()):
    total = sum(billable(r) for r in runs)
    by = {}
    for r in runs:
        by.setdefault(r['workflow'], []).append(billable(r))
    avg = {k: (sum(v) / len(v) if v else 0) for k, v in by.items()}
    month = (avg.get('ci', 0) * a['day_code_moi_thang'] + avg.get('windows', 0) * a['windows_moi_thang']
             + avg.get('nightly', 0) * 30)
    ev = [f'G0 đến nay: {len(runs)} lần chạy, {total} phút tính tiền (Windows nhân 2)']
    ev += [f'{k}: {len(by[k])} lần, trung bình {avg[k]:.1f} phút/lần' for k in sorted(by)]
    ev.append(f'Ước mỗi tháng: ci {a["day_code_moi_thang"]} lần + windows {a["windows_moi_thang"]} lần + nightly 30 lần = {month:.0f} phút')
    ok_ci = month < 1500
    ten = 'dung lượng và phút CI'
    dat_khi = 'đĩa alwaysdata dùng dưới 60% của 1 GB (tính chung file và database) sau ít nhất 5 lần deploy; ước phút CI mỗi tháng dưới 1.500'
    ci_note = 'Phút CI đạt. ' if ok_ci else 'Phút CI vượt mức. '
    deploys = [l for l in slogs if l.kind == 'deploy']
    m = next((l for l in reversed(deploys) if l.home_mb is not None), None)
    if not m:
        old = next((l for l in reversed(deploys) if l.app_mb is not None), None)
        if old:
            ev.append(f'{old.ref()}: thư mục app {old.app_mb:.0f} MB (chưa tính phần còn lại của thư mục nhà và database)')
        return item(16, ten, dat_khi, MOT_PHAN if ok_ci else CHUA_DAT, ev, ci_note + (
            'Đĩa: lần deploy tới ghi cả thư mục nhà và database.' if old else 'Dung lượng alwaysdata đo sau 5 lần deploy: ' + DEPLOY_NEEDS))
    n = sum(1 for l in deploys if l.status == 'success' and l.up_ok and int(l.run) <= int(m.run))
    used = m.home_mb + (m.db_mb or 0)
    pct = used / 1024
    db = f'database {m.db_mb:.1f} MB' if m.db_mb is not None else 'database không đo được'
    ev.append(f'{m.ref()}: cả thư mục nhà {m.home_mb:.0f} MB (thư mục app {m.app_mb or 0:.0f} MB), {db}; '
              f'tổng {used:.0f} MB = {pct:.0%} của 1 GB; đo sau {n} lần deploy thành công')
    if pct >= 0.6:
        st, note = CHUA_DAT, f'Đĩa vượt mức: {pct:.0%} (cần dưới 60%).'
    elif m.db_mb is None:
        st, note = MOT_PHAN, 'Đĩa: chưa đo được database.'
    elif n < 5:
        st, note = MOT_PHAN, f'Đĩa: mới {n} lần deploy thành công, cần ít nhất 5.'
    else:
        st, note = DAT, 'Đĩa đạt.'
    if not ok_ci:
        st = CHUA_DAT
    return item(16, ten, dat_khi, st, ev, ci_note + note)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--ci', required=True, help='thư mục bản sao nhánh ci-results')
    ap.add_argument('--runs', required=True, help='JSON từ lay_runs.py')
    ap.add_argument('--tay', help='JSON kết quả đo trên máy chủ mà log CI không có (mục 7, 13)')
    ap.add_argument('--out', help='ghi kết quả JSON ra file này')
    ap.add_argument('--day-code-moi-thang', type=int, default=120, help='số lần đẩy code mỗi tháng để ước phút CI')
    ap.add_argument('--windows-moi-thang', type=int, default=12,
                    help='số lần chạy workflow windows mỗi tháng (chỉ chạy khi đổi setup/, gói, migration; thêm 1 lần mỗi tuần)')
    a = ap.parse_args(argv)
    logs = load_logs(a.ci)
    runs = json.loads(Path(a.runs).read_text(encoding='utf-8'))
    assumptions = {'day_code_moi_thang': a.day_code_moi_thang, 'windows_moi_thang': a.windows_moi_thang}
    try:
        tay = load_tay(a.tay)
    except (ValueError, KeyError, json.JSONDecodeError) as err:
        print(f'Lỗi file --tay: {err}', file=sys.stderr)
        return 2
    items = check(logs, runs, assumptions, tay, load_server_logs(a.ci))
    tally = {s: sum(1 for i in items if i['trang_thai'] == s) for s in (DAT, MOT_PHAN, CHUA_DAT, CHUA_CHAY)}
    result = {
        'ten': 'Tự kiểm thử G0 (16 mục)',
        'luc': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'tong': tally,
        'gia_dinh_phut_ci': assumptions,
        'muc': items,
    }
    text = json.dumps(result, ensure_ascii=False, indent=1)
    if a.out:
        Path(a.out).write_text(text + '\n', encoding='utf-8')
    names = {DAT: 'đạt', MOT_PHAN: 'đạt một phần', CHUA_DAT: 'CHƯA ĐẠT', CHUA_CHAY: 'chưa chạy'}
    for i in items:
        print(f'{i["so"]:>2}. {i["ten"]}: {names[i["trang_thai"]]}')
    print(f'Tổng: {tally[DAT]} đạt, {tally[MOT_PHAN]} đạt một phần, {tally[CHUA_DAT]} chưa đạt, {tally[CHUA_CHAY]} chưa chạy')
    return 1 if tally[CHUA_DAT] else 0


if __name__ == '__main__':
    sys.exit(main())
