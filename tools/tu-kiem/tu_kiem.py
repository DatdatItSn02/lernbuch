"""Tự kiểm thử G0: chấm 16 mục của kế hoạch G0 từ bằng chứng có thật, ghi kết quả ra JSON.

Bằng chứng lấy từ:
  --ci    thư mục bản sao nhánh ci-results (latest-*.txt do .github/scripts/report.sh ghi)
  --runs  JSON danh sách lần chạy GitHub Actions kèm job (tools/tu-kiem/lay_runs.py)

Dùng:
  git clone --depth 1 -b ci-results <repo> /tmp/ci-results
  python3 tools/tu-kiem/lay_runs.py --out /tmp/runs.json
  python3 tools/tu-kiem/tu_kiem.py --ci /tmp/ci-results --runs /tmp/runs.json --out docs/tu-kiem-g0.json

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


def check(logs, runs, assumptions):
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

    # 7–10. deploy và quay lại (cần alwaysdata)
    deploys = [r for r in runs if r['workflow'] == 'ci' and r['branch'] == 'main' and (d := job(r, 'deploy')) and d['conclusion'] == 'success']
    out.append(item(7, 'deploy thật', '/up trả 200; trang chào hiện; đăng nhập được /admin', DAT if deploys else CHUA_CHAY,
                    [f'run {r["id"]}: deploy success' for r in deploys[:2]], '' if deploys else DEPLOY_NEEDS))
    for so, ten, dat_khi in ((8, 'migration lỗi khi deploy', 'deploy dừng; current vẫn là bản cũ; trang vẫn chạy'),
                             (9, 'bản mới lỗi sau khi đổi', 'workflow tự đổi current về bản trước')):
        out.append(item(so, ten, dat_khi, CHUA_CHAY, [], DEPLOY_NEEDS + ' Sau đó đẩy một bản cố ý hỏng theo kế hoạch.'))
    rb = [r for r in runs if r['workflow'] == 'rollback' and r['conclusion'] == 'success']
    out.append(item(10, 'quay lại bằng tay', 'bản trước chạy, /up trả 200', DAT if rb else CHUA_CHAY,
                    [f'run {r["id"]}: rollback success' for r in rb[:2]], '' if rb else DEPLOY_NEEDS))

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
        it13['trang_thai'] = MOT_PHAN
        it13['ghi_chu'] = ('CI đã chạy lịch và hàng đợi thật (bảng jobs) qua 10 phút mô phỏng. Còn chạy thật 10 phút trên alwaysdata: '
                           'sau khi bật tác vụ schedule:run và dịch vụ queue:work, chạy php artisan app:kiem-nhip.')
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

    # 16. phút CI (đo được) và dung lượng alwaysdata (cần deploy)
    out.append(ci_minutes_item(runs, assumptions))
    return out


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


def ci_minutes_item(runs, a):
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
    return item(16, 'dung lượng và phút CI', 'đĩa alwaysdata dùng dưới 60% của 1 GB; ước phút CI mỗi tháng dưới 1.500',
                MOT_PHAN if ok_ci else CHUA_DAT, ev,
                ('Phút CI đạt. ' if ok_ci else 'Phút CI vượt mức. ') + 'Dung lượng alwaysdata đo sau 5 lần deploy: ' + DEPLOY_NEEDS)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--ci', required=True, help='thư mục bản sao nhánh ci-results')
    ap.add_argument('--runs', required=True, help='JSON từ lay_runs.py')
    ap.add_argument('--out', help='ghi kết quả JSON ra file này')
    ap.add_argument('--day-code-moi-thang', type=int, default=120, help='số lần đẩy code mỗi tháng để ước phút CI')
    ap.add_argument('--windows-moi-thang', type=int, default=12,
                    help='số lần chạy workflow windows mỗi tháng (chỉ chạy khi đổi setup/, gói, migration; thêm 1 lần mỗi tuần)')
    a = ap.parse_args(argv)
    logs = load_logs(a.ci)
    runs = json.loads(Path(a.runs).read_text(encoding='utf-8'))
    assumptions = {'day_code_moi_thang': a.day_code_moi_thang, 'windows_moi_thang': a.windows_moi_thang}
    items = check(logs, runs, assumptions)
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
