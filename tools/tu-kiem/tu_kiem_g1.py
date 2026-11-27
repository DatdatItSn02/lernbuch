"""Tự kiểm thử G1: chấm 19 mục của kế hoạch G1 từ bằng chứng có thật, ghi kết quả ra JSON (docs/tu-kiem-g1.json).

Bằng chứng lấy từ --ci (bản sao nhánh ci-results):
  ket-qua/php/ket-qua.tsv, ket-qua/web/ket-qua.tsv  từng test Pest, Vitest, Playwright, tên đầy đủ (gon_ket_qua.py)
  latest-ci-php.txt, latest-ci-web.txt                các bước của CI và bảng hiệu năng (đọc như công cụ G0)
  latest-audio-*.txt                                  workflow audio (giọng không được dùng để bán thì dừng)
  runs/*-ci-deploy.txt, latest-audio-*.txt            log máy chủ: dung lượng, số nội dung đã nhập
và --tay: JSON kết quả thử tay mà CI không có (bộ dò Impeccable, nhập trên máy bạn); mỗi mục ghi nguồn để truy lại.

Dùng:
  git clone --depth 1 -b ci-results <repo> /tmp/ci-results
  python3 tools/tu-kiem/tu_kiem_g1.py --ci /tmp/ci-results --tay docs/tu-kiem-g1-tay.json --out docs/tu-kiem-g1.json

Trạng thái mỗi mục như G0: dat, dat_mot_phan, chua_dat, chua_chay. Mã thoát 1 nếu có mục chua_dat.
"""
import argparse
import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import tu_kiem as g0  # noqa: E402

DAT, MOT_PHAN, CHUA_DAT, CHUA_CHAY = g0.DAT, g0.MOT_PHAN, g0.CHUA_DAT, g0.CHUA_CHAY
CO_MAN_HINH = 2
GB = 1024

# Số liệu nhập Lernbuch bản x19 (docs/nhap-lernbuch-cu.md), so với dòng "Nội dung:" của app:dung-luong trong log deploy
NOI_DUNG_MONG_DOI = {'thẻ': 11882, 'chủ đề nhỏ': 620, 'bài ngữ pháp': 219, 'câu bài tập': 6887, 'động từ': 889}
# Bước của CI mà mọi migration mới phải qua (như G0)
BUOC_DATABASE = ['Migrate lên', 'Khớp với schema.sql (0 khác biệt)', 'Quy ước LoWo (không khoá ngoại, thời gian số nguyên)',
                 'Migrate xuống hết', 'Sau khi xuống chỉ còn bảng migrations', 'Migrate lên lại']


# ---------- kết quả từng test ----------

class KetQua:
    """Bảng từng test (ket-qua.tsv): (trạng thái, bộ, cỡ màn hình, file, tên)."""

    def __init__(self, dong=(), nguon=''):
        self.dong = list(dong)
        self.nguon = nguon

    @classmethod
    def doc(cls, *files):
        dong, nguon = [], []
        for f in files:
            p = Path(f)
            if not p.is_file():
                continue
            for d in p.read_text(encoding='utf-8', errors='replace').splitlines():
                cot = d.split('\t')
                if len(cot) == 5:
                    dong.append(tuple(cot))
            nguon.append(f'{p.parent.parent.name}/{p.parent.name}/{p.name}')
        return cls(dong, ', '.join(nguon))

    def tim(self, bo, ten=None, file=None):
        """Các dòng của một test (Pest có bộ dữ liệu thì tên kèm "with ..."); ten None là mọi test của file."""
        ra = []
        for tt, b, co, f, t in self.dong:
            if b != bo or (file and not f.endswith(file)):
                continue
            if ten is None or t == ten or t.startswith(ten + ' with ') or t.startswith(ten + ' with('):
                ra.append((tt, co, t))
        return ra


def bang_chung(kq, loai, ten=None, file=None, toi_thieu=1):
    """Một bằng chứng: (kết quả 'dat' | 'hong' | 'thieu', câu mô tả)."""
    bo = {'pest': 'pest', 'pest-file': 'pest', 'vitest': 'vitest', 'vitest-file': 'vitest', 'pw': 'playwright'}[loai]
    dong = kq.tim(bo, None if loai.endswith('-file') else ten, file)
    ten_hien = f'{file} (cả file)' if loai.endswith('-file') else f'"{ten}"'
    if loai == 'pw':
        co = {c for tt, c, _ in dong if tt == g0.DAT}
        hong = sorted({c for tt, c, _ in dong if tt == 'hong'})
        if hong:
            return 'hong', f'Playwright {ten_hien}: hỏng ở {", ".join(hong)}'
        if len(co) >= toi_thieu:
            return 'dat', f'Playwright {ten_hien}: đạt {len(co)}/{CO_MAN_HINH} cỡ màn hình'
        return 'thieu', f'Playwright {ten_hien}: chưa có kết quả' + (f' (mới đạt {len(co)} cỡ màn hình)' if co else '')
    ten_bo = 'Pest' if bo == 'pest' else 'Vitest'
    hong = [t for tt, _, t in dong if tt == 'hong']
    dat = [t for tt, _, t in dong if tt == g0.DAT]
    if hong:
        return 'hong', f'{ten_bo} {ten_hien}: hỏng {len(hong)} test' + (f' ({hong[0]})' if loai.endswith('-file') else '')
    if len(dat) >= toi_thieu:
        return 'dat', f'{ten_bo} {ten_hien}: đạt' + (f' {len(dat)} test' if loai.endswith('-file') or len(dat) > 1 else '')
    return 'thieu', f'{ten_bo} {ten_hien}: chưa có kết quả'


def item(so, ten, dat_khi, trang_thai, bang, ghi_chu=''):
    return g0.item(so, ten, dat_khi, trang_thai, bang, ghi_chu)


def gop(ds, them=()):
    """Gộp kết quả các bằng chứng: có hỏng thì chưa đạt; đủ thì đạt; không có gì thì chưa chạy; còn lại đạt một phần."""
    kq = [k for k, _ in ds] + list(them)
    if 'hong' in kq:
        return CHUA_DAT
    if all(k == 'dat' for k in kq):
        return DAT
    if all(k == 'thieu' for k in kq):
        return CHUA_CHAY
    return MOT_PHAN


def muc_test(so, ten, dat_khi, kq, specs, ghi_chu=''):
    ds = [bang_chung(kq, *s) for s in specs]
    return item(so, ten, dat_khi, gop(ds), [m for _, m in ds], ghi_chu)


# ---------- log máy chủ và workflow audio ----------

def so_mb(text, mau):
    m = re.search(mau, text, re.M)
    return g0.mb(m.group(1)) if m else None


class LogMayChu(g0.Log):
    """Log deploy hay log workflow audio: dung lượng thư mục nhà, database, dòng Nội dung."""

    def __init__(self, name, text):
        super().__init__(name, text)
        self.nha_mb = so_mb(self.text, r'^Cả thư mục nhà: (\S+)')
        self.db_mb = so_mb(self.text, r'^Database: (\S+) \(')
        self.audio_mb = so_mb(self.text, r'^Audio trên máy chủ: (\S+)')
        m = re.search(r'^Nội dung: (\d+) thẻ, (\d+) chủ đề nhỏ, (\d+) bài ngữ pháp, (\d+) câu bài tập, (\d+) động từ, (\d+) file audio', self.text, re.M)
        self.noi_dung = dict(zip([*NOI_DUNG_MONG_DOI, 'file audio'], map(int, m.groups()))) if m else None


def doc_log_may_chu(ci_dir):
    """Log deploy (runs/) và log workflow audio (latest-audio-*, runs/ nếu có), cũ trước mới sau; một lần chạy chỉ lấy một bản."""
    ci = Path(ci_dir)
    files = [p for p in ci.glob('runs/*.txt') if p.name.endswith('-ci-deploy.txt') or '-audio-' in p.name]
    files += sorted(ci.glob('latest-audio-*.txt'))
    ra = {}
    for p in files:
        log = LogMayChu(p.name, p.read_text(encoding='utf-8', errors='replace'))
        job = re.search(r'job: (\S+)', log.text)
        ra.setdefault((log.run, job.group(1) if job else p.name), log)
    return sorted(ra.values(), key=lambda l: int(l.run or 0))


# ---------- 19 mục ----------

CHE_DO = [
    'Flashcard: Space lật, 1–4 chấm, P, S, E nghe, ← → xem lại, Esc thoát',
    'Quiz: phím 1–4 chọn, Enter và Space đi tiếp, P và S nghe; chọn sai thì câu quay lại sau 3 câu',
    'Listening: đọc từ khi câu hiện; đang gõ thì Alt+P, Alt+S nghe lại, P là chữ p; gợi ý nghĩa là Khó; gõ sai thì câu quay lại',
    'Typing: chưa trả lời thì không nghe được, trả lời rồi P, S nghe; nút ä; thiếu mạo từ, gợi ý chữ đầu là Khó; gõ sai thì câu quay lại',
    'Ghép cặp: Enter chọn ô, P và S nghe từ vừa chọn; nối sai thì báo, thẻ đó chỉ được Khó; 10 thẻ thành 2 vòng 5 cặp',
    'Hỗn hợp: thẻ mới có câu ví dụ thành Đặt câu, thẻ khác thành Quiz; xem đáp án là sai, câu quay lại',
    'Đặt câu: P và S nghe câu; sai thứ tự thì thử lại, Backspace bỏ chữ cuối, sửa rồi đúng là Khó; Enter kiểm tra và đi tiếp',
]

# Mục chỉ cần test: số mục → (tên, đạt khi, các bằng chứng). Bằng chứng: ('pest', tên), ('pest-file', None, file, số test ít nhất),
# ('vitest-file', None, file, số test ít nhất), ('pw', tên, None, số cỡ màn hình). Tên đúng như trong file test (test_tu_kiem_g1.py kiểm).
KIEM_TEST = {
    2: ('quy tắc dữ liệu', 'báo đúng dòng, không ghi gì', [
        ('pest', 'quy tắc dữ liệu: cụm từ trong Từ vựng, thẻ không có chủ đề thì báo đúng dòng, không ghi gì'),
        ('pest', 'quy tắc bài tập: sig trùng hoặc dài quá 64 ký tự thì báo đúng câu, không ghi gì'),
    ]),
    3: ('lặp lại ngắt quãng', 'đúng mọi nhánh của bảng ca thử chung ở Pest và Vitest, cả đổi giờ mùa hè, đổi múi giờ', [
        ('pest', 'tính đúng như bảng ca thử chung'),
        ('pest', 'bảng ca thử phủ đủ 6 mức × 4 cách chấm và có ca đổi giờ, đổi múi giờ'),
        ('pest', 'đầu ngày mai và đầu ngày trước đúng cả ngày 23 giờ và 25 giờ'),
        ('vitest-file', None, 'resources/js/app/lib/srs.test.ts'),
    ]),
    4: ('lượt chấm gửi trùng', 'ghi một lần, xu cộng một lần', [
        ('pest', 'mục 4: gửi một lô hai lần (và trùng ngay trong lô) thì ghi một lần, xu cộng một lần'),
    ]),
    5: ('mất mạng giữa phiên', 'đủ 8 lượt tới máy chủ, mỗi lượt một lần', [
        ('pw', 'mục 5: mất mạng sau thẻ 3, chấm tiếp 5 thẻ, tải lại trang, có mạng lại: đủ 8 lượt tới máy chủ, mỗi lượt một lần', None, CO_MAN_HINH),
        ('vitest-file', None, 'resources/js/app/lib/hangCho.test.ts'),
    ]),
    6: ('hai máy chấm cùng thẻ', 'kết quả theo giờ chấm, giống nhau dù lô nào tới trước', [
        ('pest', 'mục 6: hai máy chấm cùng thẻ, lô tới lệch thứ tự: kết quả theo giờ chấm, như nhau dù lô nào tới trước'),
    ]),
    7: ('chấm quá nhanh', 'tiến độ vẫn ghi, xu không cộng, nhật ký ghi lý do', [
        ('pest', 'mục 7: chấm 20 thẻ trong 2 giây thì tiến độ vẫn ghi, xu không cộng, nhật ký ghi lý do; chấm thong thả thì có xu'),
    ]),
    8: ('người A không đọc được dữ liệu người B', 'trả 404, không lộ dữ liệu', [
        ('pest', 'mục 8: phiên của người khác thì 404 và không ghi gì; thẻ riêng của người khác thì bỏ lượt; số liệu tách theo người'),
        ('pest', 'người A không đọc, không sửa được sổ tay của người B; thẻ không xem được thì 404 (mục tự kiểm 8)'),
        ('pest', 'xoá lịch sử: chỉ mục của mình; mục của người khác thì 404 và vẫn còn (mục tự kiểm 8)'),
    ]),
    9: ('tài khoản', 'báo đúng lỗi; link cũ không dùng được; chưa xác minh thì về trang gửi lại email; '
        'sai mật khẩu lần thứ 6 bị chặn tạm; quá 5 lần đăng ký một giờ từ một IP thì bị chặn tạm', [
        ('pest', 'đăng ký: email đã có thì báo lỗi ngay ô email'),
        ('pest', 'link xác minh hết hạn hoặc không khớp email thì không dùng được'),
        ('pest', 'quên mật khẩu: gửi link, đặt mật khẩu mới, dùng lại link thì không được'),
        ('pest', 'chưa xác minh email mà vào học thì được dẫn tới trang gửi lại thư'),
        ('pest', 'sai mật khẩu 5 lần thì lần thứ 6 bị chặn tạm, kể cả đúng mật khẩu'),
        ('pest', 'đăng ký: mỗi địa chỉ IP tạo tối đa 5 tài khoản mỗi giờ, lần thứ 6 bị chặn tạm'),
    ]),
    10: ('quản trị', 'người học bị từ chối; mọi lần sửa nội dung ghi nhật ký', [
        ('pest', 'người học (không phải nhân viên) vào /admin thì bị từ chối 403; nhân viên bị khoá cũng vậy'),
        ('pest', 'nhân viên sửa nội dung thì ghi người sửa và nhật ký trước/sau; người học tự sửa hồ sơ thì không ghi nhật ký'),
        ('pest-file', None, 'tests/Feature/QuanTriNoiDungTest.php', 10),
        ('pest-file', None, 'tests/Feature/QuanTriNguoiHocTest.php', 4),
        ('pw', 'trang quản trị (sáng): không lỗi, không tràn ngang, đạt WCAG 2.2 AA', None, CO_MAN_HINH),
        ('pw', 'trang quản trị (tối): không lỗi, không tràn ngang, đạt WCAG 2.2 AA', None, CO_MAN_HINH),
    ]),
    11: ('audio dự phòng', 'thiếu file, file 404, không có giọng Đức: tự chuyển sang giọng trình duyệt; '
         'không có giọng nào thì báo nhẹ, trang không lỗi', [
        ('vitest-file', None, 'resources/js/app/lib/amThanh.test.ts', 8),
        ('vitest-file', None, 'resources/js/app/lib/speech.test.ts', 6),
        ('pest', 'API: thẻ có file thì kèm địa chỉ audio, chưa có thì null; đổi AUDIO_URL thì đổi địa chỉ'),
    ]),
    13: ('7 chế độ và phím tắt', 'chấm đúng, tổng kết đúng số, không tràn khung, ở cỡ máy tính và điện thoại',
         [('pw', t, None, CO_MAN_HINH) for t in CHE_DO]),
    15: ('PWA', 'cài được; mất mạng vẫn mở app và học tiếp chủ đề đã tải', [
        ('pw', 'cài app và học khi mất mạng › đủ điều kiện cài; mất mạng vẫn mở app và học tiếp chủ đề đã tải; có mạng lại thì gửi lượt chấm',
         None, CO_MAN_HINH),
        ('pw', 'màn mới của PWA ở giao diện tối › ô tải chủ đề, dòng báo mất mạng, phiên học từ chủ đề đã tải, phần cài app: đạt WCAG 2.2 AA',
         None, CO_MAN_HINH),
        ('pest-file', None, 'tests/Feature/HocOfflineTest.php', 4),
        ('vitest-file', None, 'resources/js/app/lib/offline.test.ts', 10),
    ]),
    16: ('tra từ', 'gõ không dấu ở 4 thứ tiếng ra đúng thẻ; "zahlen" và "zählen" khớp đúng chữ đứng đầu', [
        ('pest', '"zahlen" và "zählen": từ đúng từng chữ đứng đầu, rồi dạng khác (số nhiều), rồi từ gần giống'),
        ('pest', 'gõ không dấu ở 4 thứ tiếng: Đức (ö → o hoặc oe), Việt, Anh, Trung; nghĩa trùng đứng trước nghĩa chứa'),
        ('pw', 'tra từ: "zahlen" và "zählen" ra đúng thẻ đứng đầu; gõ không dấu tiếng Việt, tiếng Đức (mục tự kiểm 16)', None, CO_MAN_HINH),
    ]),
    19: ('chuẩn bị đa ngôn ngữ', 'không còn chữ viết thẳng trong code giao diện; thiếu bản dịch thì hiện tiếng Việt, không hiện mã khoá', [
        ('vitest-file', None, 'tests/js/chuVietThang.test.ts', 2),
        ('pest', 'chọn ngôn ngữ chưa có bản dịch (tiếng Anh khi G1 chỉ có tiếng Việt): trang, API và email đều tiếng Việt'),
        ('pest', 'ngôn ngữ đã bật nhưng còn thiếu bản dịch: chữ thiếu hiện tiếng Việt, không bao giờ hiện mã khoá'),
        ('pest-file', None, 'tests/Feature/BanDichFilamentTest.php', 3),
    ]),
}

PEST_NHAP = [
    'chạy thử: đếm đúng theo loại, nguồn, cấp; bỏ thẻ đã tắt và dòng rỗng; không ghi gì',
    'ghi: thẻ, chủ đề 3 tầng, nghĩa, câu ví dụ, dạng từ, ngữ pháp, bài tập, động từ đúng nội dung',
    'chạy lại lần hai: không thêm dòng nào, giữ id (nên giữ tiến độ học)',
    'nguồn đánh dấu "Không" thì không có dòng nào của nguồn đó, kể cả dòng đã nhập lần trước',
]
PEST_HIEU_NANG = 'dữ liệu cỡ thật: tạo phiên p95 dưới 150 ms, các API học và nội dung p95 dưới 200 ms'
BUOC_AUDIO = 'Test công cụ audio (giọng được dùng để bán, tên file theo hash)'
BUOC_TUONG_PHAN = 'Tương phản màu WCAG AA (palette_check.py trên tokens.css)'
# Mục 14, ngoài các test Playwright có axe: bảng màu trang quản trị (nút Filament, sáng và tối)
TRO_NANG_THEM = [('pest-file', None, 'tests/Feature/MauQuanTriTest.php', 3)]


def muc_nhap(kq, logs_may_chu, tay):
    """1. Pest trên dữ liệu thử; số liệu thật trên máy chủ (log deploy) hoặc trên máy bạn (--tay)."""
    ds = [bang_chung(kq, 'pest', t) for t in PEST_NHAP]
    ev = [m for _, m in ds]
    nd = next((l for l in reversed(logs_may_chu) if l.noi_dung), None)
    them = []
    if nd:
        khac = {k: (nd.noi_dung[k], v) for k, v in NOI_DUNG_MONG_DOI.items() if nd.noi_dung[k] != v}
        ev.append(f'máy chủ ({nd.name}, run {nd.run}): ' + ', '.join(f'{v} {k}' for k, v in nd.noi_dung.items())
                  + (' — khớp số liệu nhập' if not khac else ' — lệch: ' + ', '.join(f'{k} {a} (cần {b})' for k, (a, b) in khac.items())))
        them.append('hong' if khac else 'dat')
    e = tay.get(1)
    if e:
        ev.append(f'{e.get("ket_qua", "")} ({g0.tay_ref(e)})'.strip())
        if not nd:
            them.append({True: 'dat', False: 'hong'}.get(e.get('khop'), 'thieu'))
    if not nd and not e:
        them.append('thieu')
    return item(1, 'nhập đủ và đúng', 'số liệu khớp Lernbuch cũ (11.882 thẻ, 620 chủ đề nhỏ, 219 bài ngữ pháp, 6.887 câu, 889 động từ); '
                'lần hai không thêm dòng; nguồn "Không" không có dòng nào', gop(ds, them), ev,
                '' if nd else 'Số liệu trên máy chủ: lần deploy tới ghi dòng "Nội dung:" (app:dung-luong).')


def muc_giong(logs):
    """12. Workflow audio cố ý chọn pavoque thì dừng; công cụ audio có test cả trường hợp này."""
    php = logs.get('ci-php')
    chan = next((l for k, l in logs.items() if k.startswith('audio-') and re.search(r'^✘ Giọng pavoque không được dùng để bán', l.text, re.M)), None)
    ev, them = [], []
    if chan:
        ev.append(f'workflow audio chọn pavoque: dừng, "Giọng pavoque không được dùng để bán" ({chan.ref()}, trạng thái {chan.status})')
        them.append('dat' if chan.status == 'failure' else 'hong')
    else:
        them.append('thieu')
    if php and BUOC_AUDIO in php.steps:
        ev.append(f'{BUOC_AUDIO}: {"đạt" if php.ok(BUOC_AUDIO) else "hỏng"} ({php.ref()})')
        them.append('dat' if php.ok(BUOC_AUDIO) else 'hong')
    return item(12, 'giọng có giấy phép', 'workflow dừng, báo giọng này không được dùng để bán', gop([], them), ev,
                '' if chan else 'Chạy workflow audio ở chế độ nghe thử với giọng pavoque để có bằng chứng.')


def muc_tro_nang(kq, logs, tay):
    """14. Mọi test Playwright có axe (tên có "WCAG") đạt ở hai cỡ màn hình; tương phản màu; bộ dò Impeccable (--tay)."""
    web = logs.get('ci-web')
    wcag = sorted({t for _, _, t in kq.tim('playwright') if 'WCAG' in t})
    ds = [bang_chung(kq, 'pw', t, None, CO_MAN_HINH) for t in wcag]
    ev = [f'{sum(1 for k, _ in ds if k == "dat")}/{len(ds)} test Playwright có axe WCAG 2.2 AA đạt ở cả hai cỡ màn hình']
    ev += [m for k, m in ds if k != 'dat']
    them = ['dat' if ds else 'thieu']
    for s in TRO_NANG_THEM:
        k, m = bang_chung(kq, *s)
        ev.append(m)
        them.append(k)
    m = re.search(r'Tương phản: (\d+)/(\d+)', web.text) if web else None
    if web and BUOC_TUONG_PHAN in web.steps:
        ev.append(f'{BUOC_TUONG_PHAN}: {"đạt" if web.ok(BUOC_TUONG_PHAN) else "hỏng"}' + (f', {m.group(0)} cặp' if m else '') + f' ({web.ref()})')
        them.append('dat' if web.ok(BUOC_TUONG_PHAN) else 'hong')
    else:
        them.append('thieu')
    e = tay.get(14)
    if e and 'loi_impeccable' in e:
        ev.append(f'bộ dò Impeccable: {e["loi_impeccable"]} lỗi, {e.get("luu_y_impeccable", 0)} lưu ý ({g0.tay_ref(e)})')
        them.append('dat' if e['loi_impeccable'] == 0 else 'hong')
    else:
        them.append('thieu')
    return item(14, 'trợ năng và giao diện', '0 lỗi WCAG 2.2 AA trên mọi màn mới, sáng và tối; mọi cặp màu đạt tương phản; bộ dò Impeccable 0 lỗi',
                gop(ds, them), ev, '' if e and 'loi_impeccable' in e else 'Còn kết quả bộ dò Impeccable (ghi vào file --tay).')


def muc_hieu_nang(kq, logs):
    """17. Test đạt, và bảng p95 in trong log php: tạo phiên dưới 150 ms, API khác dưới 200 ms."""
    php = logs.get('ci-php')
    ds = [bang_chung(kq, 'pest', PEST_HIEU_NANG)]
    bang = re.findall(r'^\s+(\S.*?)\s+p50\s+([\d.]+) ms\s+p95\s+([\d.]+) ms', php.text, re.M) if php else []
    ev, them = [m for _, m in ds], []
    phien = [float(p95) for ten, _, p95 in bang if ten.startswith('POST hoc/phien')]
    khac = [float(p95) for ten, _, p95 in bang if not ten.startswith('POST hoc/phien')]
    if phien and khac:
        ev.append(f'tạo phiên p95 cao nhất {max(phien):.1f} ms, API khác p95 cao nhất {max(khac):.1f} ms ({len(bang)} API, {php.ref()})')
        them.append('dat' if max(phien) < 150 and max(khac) < 200 else 'hong')
    else:
        them.append('thieu')
    return item(17, 'hiệu năng', '11.882 thẻ, 5.000 dòng tiến độ: tạo phiên dưới 150 ms, API p95 dưới 200 ms', gop(ds, them), ev)


def muc_dung_luong(logs_may_chu):
    """18. Thư mục nhà (log mới nhất có số này: deploy hay audio) cộng database (log deploy mới nhất)."""
    ten, dat_khi = 'dung lượng', 'dưới 60% của 1 GB sau khi nhập và tạo audio'
    nha = next((l for l in reversed(logs_may_chu) if l.nha_mb is not None), None)
    db = next((l for l in reversed(logs_may_chu) if l.db_mb is not None), None)
    if not nha:
        return item(18, ten, dat_khi, CHUA_CHAY, [], 'Chưa có log máy chủ ghi dung lượng.')
    dung = nha.nha_mb + (db.db_mb if db else 0)
    ev = [f'thư mục nhà {nha.nha_mb:.0f} MB ({nha.name}, run {nha.run})' + (f', trong đó audio {nha.audio_mb:.0f} MB' if nha.audio_mb else ''),
          f'database {db.db_mb:.1f} MB ({db.name}, run {db.run})' if db else 'database: chưa đo',
          f'tổng {dung:.0f} MB = {dung / GB:.0%} của 1 GB']
    if dung / GB >= 0.6:
        return item(18, ten, dat_khi, CHUA_DAT, ev, f'Vượt mức: {dung / GB:.0%} (cần dưới 60%).')
    if not db or int(db.run or 0) < int(nha.run or 0):
        return item(18, ten, dat_khi, MOT_PHAN, ev, 'Database đo trước lần đổi dung lượng gần nhất: lần deploy tới đo lại cả hai.')
    return item(18, ten, dat_khi, DAT, ev)


def check(kq, logs, logs_may_chu, tay):
    out = [muc_nhap(kq, logs_may_chu, tay)]
    for so in range(2, 20):
        if so in KIEM_TEST:
            ten, dat_khi, specs = KIEM_TEST[so]
            out.append(muc_test(so, ten, dat_khi, kq, specs))
        elif so == 12:
            out.append(muc_giong(logs))
        elif so == 14:
            out.append(muc_tro_nang(kq, logs, tay))
        elif so == 17:
            out.append(muc_hieu_nang(kq, logs))
        elif so == 18:
            out.append(muc_dung_luong(logs_may_chu))
    return out


def dieu_kien_chung(logs):
    """Mọi migration mới vẫn qua các phép kiểm của G0."""
    php = logs.get('ci-php')
    if not php:
        return {'trang_thai': CHUA_CHAY, 'bang_chung': []}
    ok = [s for s in BUOC_DATABASE if php.ok(s)]
    return {'trang_thai': DAT if len(ok) == len(BUOC_DATABASE) else CHUA_DAT,
            'bang_chung': [f'{len(ok)}/{len(BUOC_DATABASE)} bước database của G0 đạt: {", ".join(ok)} ({php.ref()})']}


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--ci', required=True, help='thư mục bản sao nhánh ci-results')
    ap.add_argument('--tay', help='JSON kết quả thử tay (bộ dò Impeccable, nhập trên máy bạn)')
    ap.add_argument('--out', help='ghi kết quả JSON ra file này')
    a = ap.parse_args(argv)
    try:
        tay = g0.load_tay(a.tay)
    except (ValueError, KeyError, json.JSONDecodeError) as err:
        print(f'Lỗi file --tay: {err}', file=sys.stderr)
        return 2
    ci = Path(a.ci)
    kq = KetQua.doc(ci / 'ket-qua/php/ket-qua.tsv', ci / 'ket-qua/web/ket-qua.tsv')
    logs = g0.load_logs(ci)
    items = check(kq, logs, doc_log_may_chu(ci), tay)
    tally = {s: sum(1 for i in items if i['trang_thai'] == s) for s in (DAT, MOT_PHAN, CHUA_DAT, CHUA_CHAY)}
    result = {
        'ten': 'Tự kiểm thử G1 (19 mục)',
        'luc': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'tong': tally,
        'nguon_ket_qua_test': kq.nguon,
        'migration_moi': dieu_kien_chung(logs),
        'muc': items,
    }
    text = json.dumps(result, ensure_ascii=False, indent=1)
    if a.out:
        Path(a.out).write_text(text + '\n', encoding='utf-8')
    ten = {DAT: 'đạt', MOT_PHAN: 'đạt một phần', CHUA_DAT: 'CHƯA ĐẠT', CHUA_CHAY: 'chưa chạy'}
    for i in items:
        print(f'{i["so"]:>2}. {i["ten"]}: {ten[i["trang_thai"]]}')
    print(f'Tổng: {tally[DAT]} đạt, {tally[MOT_PHAN]} đạt một phần, {tally[CHUA_DAT]} chưa đạt, {tally[CHUA_CHAY]} chưa chạy')
    return 1 if tally[CHUA_DAT] else 0


if __name__ == '__main__':
    sys.exit(main())
