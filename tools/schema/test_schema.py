"""Kiểm thử schema Lernbuch Online trên MySQL/MariaDB thật.

Chạy trên database THỬ (script thêm dữ liệu mẫu):
    mysql -u root -p < schema.sql && mysql -u root -p < seed.sql
    pip install pymysql
    DB_HOST=127.0.0.1 DB_USER=root DB_PASSWORD=... python3 test_schema.py
Không đặt DB_HOST thì script nối qua socket /var/run/mysqld/mysqld.sock (Linux).
"""
import json
import os
import random
import statistics
import string
import sys
import time

import pymysql

DB = os.environ.get("DB_DATABASE", "lernbuch")
FRAMEWORK_TABLES = {
    "password_reset_tokens", "sessions", "cache", "cache_locks", "jobs", "job_batches",
    "failed_jobs", "personal_access_tokens", "notifications", "features", "migrations",
}
# cột *_id không phải khoá tham chiếu
NOT_REFERENCES = {"public_id", "provider_user_id", "gateway_txn_id", "device_uid"}

results = {"checks": [], "timings_ms": {}, "behaviour": [], "explain": {}}
failures = []


def conn():
    kw = dict(user=os.environ.get("DB_USER", "root"), password=os.environ.get("DB_PASSWORD", ""),
              database=DB, charset="utf8mb4", autocommit=True)
    sock = os.environ.get("DB_SOCKET", "/var/run/mysqld/mysqld.sock")
    if not os.environ.get("DB_HOST") and os.path.exists(sock):
        return pymysql.connect(unix_socket=sock, **kw)
    return pymysql.connect(host=os.environ.get("DB_HOST", "127.0.0.1"),
                           port=int(os.environ.get("DB_PORT", "3306")), **kw)


def check(name, ok, detail=""):
    results["checks"].append({"name": name, "ok": bool(ok), "detail": detail})
    if not ok:
        failures.append(f"{name}: {detail}")


c = conn()
cur = c.cursor()
cur.execute("SELECT VERSION()")
results["server"] = cur.fetchone()[0]

# ---------------------------------------------------------------- cấu trúc
cur.execute("SELECT table_name, engine, table_collation, table_comment FROM information_schema.tables "
            "WHERE table_schema=%s AND table_name <> 'migrations' ORDER BY table_name", (DB,))
tables = cur.fetchall()
results["table_count"] = len(tables)
check("mọi bảng dùng InnoDB", all(t[1] == "InnoDB" for t in tables))
check("mọi bảng có chú thích", all(t[3] for t in tables), [t[0] for t in tables if not t[3]])

cur.execute("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=%s", (DB,))
check("không có khoá ngoại", cur.fetchone()[0] == 0)

cur.execute("SELECT table_name, column_name, data_type, column_type FROM information_schema.columns "
            "WHERE table_schema=%s", (DB,))
cols = cur.fetchall()
cur.execute("SELECT table_name, index_name, column_name, seq_in_index FROM information_schema.statistics "
            "WHERE table_schema=%s", (DB,))
stats = cur.fetchall()
idx_first = {(r[0], r[2]) for r in stats if r[3] == 1}
# cột id đa hình (x_type, x_id) đứng thứ 2 sau cột _type trong cùng chỉ mục
by_index = {}
for t, i, col, seq in stats:
    by_index.setdefault((t, i), {})[seq] = col
poly = {(t, cols[2]) for (t, i), cols in by_index.items()
        if cols.get(1, "").endswith("_type") and 2 in cols and cols[2].endswith("_id")}
idx_first |= poly

missing_idx = []
for t, col, dt, ct in cols:
    if t in FRAMEWORK_TABLES or col in NOT_REFERENCES:
        continue
    if (col.endswith("_id") or col.endswith("_by") or col == "referred_by") and (t, col) not in idx_first:
        missing_idx.append(f"{t}.{col}")
check("mọi cột *_id / *_by có chỉ mục đứng đầu", not missing_idx, missing_idx)

bad_time = [f"{t}.{col} ({ct})" for t, col, dt, ct in cols
            if t not in FRAMEWORK_TABLES and col.endswith("_at") and not (dt == "int" and "unsigned" in ct)]
check("mọi cột *_at (trừ bảng framework) là INT UNSIGNED", not bad_time, bad_time)
dt_cols = [f"{t}.{col}" for t, col, dt, ct in cols
           if t not in FRAMEWORK_TABLES and dt in ("datetime", "timestamp", "date")]
check("không có DATETIME/TIMESTAMP/DATE ngoài bảng framework", not dt_cols, dt_cols)

by_table = {}
for t, col, *_ in cols:
    by_table.setdefault(t, set()).add(col)
audit6 = {"created_at", "created_by", "updated_at", "updated_by", "deleted_at", "deleted_by"}
managed = sorted(t for t, cs in by_table.items() if audit6 <= cs)
results["managed_tables"] = managed
partial = sorted(t for t, cs in by_table.items()
                 if t not in FRAMEWORK_TABLES and "deleted_at" in cs and not audit6 <= cs)
check("bảng có deleted_at thì đủ 6 cột người tạo/sửa/xoá", not partial, partial)

cur.execute("SELECT partition_name FROM information_schema.partitions WHERE table_schema=%s "
            "AND table_name='review_logs' ORDER BY partition_ordinal_position", (DB,))
results["review_partitions"] = [r[0] for r in cur.fetchall()]

# ---------------------------------------------------------------- dữ liệu khởi tạo
for table, expected in {"plans": 5, "plan_entitlements": 40, "roles": 9, "permissions": 14,
                        "card_collections": 7, "languages": 4, "ai_prompts": 10, "content_sources": 13}.items():
    cur.execute(f"SELECT COUNT(*) FROM {table}")
    n = cur.fetchone()[0]
    check(f"seed {table} = {expected}", n == expected, n)
cur.execute("SELECT COUNT(*) FROM plans WHERE tier = 2 AND is_active = 1")
check("gói Premium chưa có giá đang tắt", cur.fetchone()[0] == 0)

# ---------------------------------------------------------------- dữ liệu thử cỡ Lernbuch
random.seed(7)
NOW = 1795000000  # 2026-11-17
cur.execute("SET unique_checks=0")


def rand_word(n=7):
    return "".join(random.choice(string.ascii_lowercase) for _ in range(n))


topics = []
tid = 0
for g in range(21):
    tid += 1
    topics.append((tid, "de", None, 1, None, f"g{g:02d}", f"g{g:02d}", f"Nhóm {g}"))
group_ids = list(range(1, 22))
mid_ids = []
for m in range(266):
    tid += 1
    mid_ids.append(tid)
    topics.append((tid, "de", random.choice(group_ids), 2, None, f"m{m:03d}", f"m{m:03d}", f"Chủ đề vừa {m}"))
collections = (["vocab"] * 1684 + ["phrase"] * 361 + ["comm"] * 220 + ["sepverb"] * 81 +
               ["verbprep"] * 72 + ["pattern"] * 35 + ["name"] * 13)
fine = []
for i, col in enumerate(collections):
    tid += 1
    fine.append((tid, col))
    topics.append((tid, "de", random.choice(mid_ids), 3, col, f"f{i:04d}", f"f{i:04d}", f"Chủ đề nhỏ {i}"))
cur.executemany("INSERT INTO topics (id, lang, parent_id, depth, collection, code, slug, name_vi, created_at) "
                "VALUES (%s,%s,%s,%s,%s,%s,%s,%s," + str(NOW) + ")", topics)

counts = {"vocab": 8688, "phrase": 1891, "comm": 952, "pattern": 144, "verbprep": 187, "sepverb": 207, "name": 158}
fine_by_col = {}
for t, col in fine:
    fine_by_col.setdefault(col, []).append(t)
cards = []
cid = 0
for col, n in counts.items():
    pool = fine_by_col[col]
    for i in range(n):
        cid += 1
        word = rand_word(random.randint(4, 11))
        topic = pool[i % len(pool)]  # mọi chủ đề nhỏ đều có thẻ
        cards.append((cid, "de", col, f"k{cid:08d}", topic, word, random.choice([1, 2, 3, 4, None]),
                      f"{word} nghia {cid} meaning"))
cur.executemany("INSERT INTO cards (id, lang, collection, card_key, topic_id, front, level, search_text, created_at) "
                "VALUES (%s,%s,%s,%s,%s,%s,%s,%s," + str(NOW) + ")", cards)
cur.executemany("INSERT INTO card_translations (card_id, lang, meaning) VALUES (%s,%s,%s)",
                [(i, l, f"nghĩa {l} {i}") for i in range(1, cid + 1) for l in ("vi", "en", "zh")])
results["sample"] = {"topics": len(topics), "cards": cid}

USERS = 400
cur.executemany("INSERT INTO users (id, public_id, email, name, created_at) VALUES (%s,%s,%s,%s,%s)",
                [(u, f"{u:026d}", f"user{u}@example.com", f"User {u}", NOW) for u in range(1, USERS + 1)])
progress = []
for u in range(1, USERS + 1):
    n = random.randint(800, 3000)
    for card in random.sample(range(1, cid + 1), n):
        box = random.randint(1, 5)
        progress.append((u, card, box, NOW + random.randint(-5, 30) * 86400, random.randint(1, 20)))
    if len(progress) > 50000:
        cur.executemany("INSERT INTO card_progress (user_id, card_id, box, due_at, reps) VALUES (%s,%s,%s,%s,%s)", progress)
        progress = []
if progress:
    cur.executemany("INSERT INTO card_progress (user_id, card_id, box, due_at, reps) VALUES (%s,%s,%s,%s,%s)", progress)
cur.execute("SELECT COUNT(*) FROM card_progress")
results["sample"]["card_progress"] = cur.fetchone()[0]

logs = []
for i in range(600000):
    ts = random.randint(1767225600, 1830297599)  # 2026–2027
    logs.append((random.randint(1, USERS), random.randint(1, cid), "flashcard", 2, 1, 2, 5, ts))
    if len(logs) >= 50000:
        cur.executemany("INSERT INTO review_logs (user_id, card_id, mode, rating, box_before, box_after, coins, reviewed_at) "
                        "VALUES (%s,%s,%s,%s,%s,%s,%s,%s)", logs)
        logs = []
results["sample"]["review_logs"] = 600000

days = [(u, NOW - d * 86400, random.randint(0, 80)) for u in range(1, USERS + 1) for d in range(60)]
cur.executemany("INSERT INTO user_daily_stats (user_id, day_start, reviews) VALUES (%s,%s,%s)", days)
cur.execute("INSERT INTO subscriptions (user_id, plan_id, status, source, starts_at, ends_at, created_at) "
            "SELECT id, 2, 'active', 'order', %s, %s, %s FROM users WHERE id %% 5 = 0", (NOW - 86400, NOW + 30 * 86400, NOW))
cur.execute("SET unique_checks=1")
cur.execute("ANALYZE TABLE cards, card_progress, review_logs, topics, user_daily_stats, subscriptions")
cur.fetchall()

# ---------------------------------------------------------------- truy vấn chính
QUERIES = {
    "the_den_han": ("SELECT card_id FROM card_progress WHERE user_id=%s AND due_at <= %s ORDER BY due_at LIMIT 200",
                    lambda u: (u, NOW)),
    "tu_moi_trong_chu_de": ("SELECT c.id FROM cards c LEFT JOIN card_progress p ON p.user_id=%s AND p.card_id=c.id "
                            "WHERE c.topic_id=%s AND c.status='published' AND c.owner_user_id IS NULL "
                            "AND p.card_id IS NULL ORDER BY c.id LIMIT 20",
                            lambda u: (u, random.choice(fine)[0])),
    "muc_thuoc_theo_chu_de": ("SELECT c.topic_id, p.box, COUNT(*) FROM card_progress p JOIN cards c ON c.id=p.card_id "
                              "WHERE p.user_id=%s GROUP BY c.topic_id, p.box", lambda u: (u,)),
    "den_han_theo_chu_de": ("SELECT c.topic_id, COUNT(*) FROM card_progress p JOIN cards c ON c.id=p.card_id "
                            "WHERE p.user_id=%s AND p.due_at <= %s GROUP BY c.topic_id", lambda u: (u, NOW)),
    "goi_dang_dung": ("SELECT COALESCE(MAX(pl.tier),1) FROM subscriptions s JOIN plans pl ON pl.id=s.plan_id "
                      "WHERE s.user_id=%s AND s.status IN ('trialing','active') AND s.starts_at <= %s "
                      "AND (s.ends_at IS NULL OR s.ends_at > %s)", lambda u: (u, NOW, NOW)),
    "bieu_do_14_ngay": ("SELECT day_start, reviews FROM user_daily_stats WHERE user_id=%s AND day_start >= %s "
                        "ORDER BY day_start", lambda u: (u, NOW - 14 * 86400)),
    "nhat_ky_7_ngay": ("SELECT COUNT(*) FROM review_logs WHERE user_id=%s AND reviewed_at BETWEEN %s AND %s",
                       lambda u: (u, NOW - 7 * 86400, NOW)),
    "tra_tu_khong_dau": ("SELECT id FROM cards WHERE lang='de' AND search_text LIKE %s LIMIT 60",
                         lambda u: ("%" + rand_word(3) + "%",)),
}
for name, (sql, args) in QUERIES.items():
    runs = []
    for _ in range(30):
        u = random.randint(1, USERS)
        a = args(u)
        t0 = time.perf_counter()
        cur.execute(sql, a)
        cur.fetchall()
        runs.append((time.perf_counter() - t0) * 1000)
    results["timings_ms"][name] = {"median": round(statistics.median(runs), 2), "max": round(max(runs), 2)}
    cur.execute("EXPLAIN " + sql, args(1))
    rows = cur.fetchall()
    names = [d[0] for d in cur.description]
    results["explain"][name] = [{k: (r[names.index(k)] if k in names else None)
                                 for k in ("table", "partitions", "type", "key", "rows")} for r in rows]

# ---------------------------------------------------------------- hành vi
def expect_dup(label, sql, args):
    try:
        cur.execute(sql, args)
        results["behaviour"].append({"case": label, "ok": False, "detail": "không báo trùng"})
        failures.append(label)
    except pymysql.err.IntegrityError as e:
        results["behaviour"].append({"case": label, "ok": e.args[0] == 1062, "detail": e.args[0]})


payload = json.dumps({"id": 123, "amount": 199000})
ins_evt = ("INSERT IGNORE INTO payment_events (gateway, event_key, signature_ok, payload, received_at) "
           "VALUES ('sepay','evt-123',1,%s,%s)")
cur.execute(ins_evt, (payload, NOW)); first = cur.rowcount
cur.execute(ins_evt, (payload, NOW)); second = cur.rowcount
results["behaviour"].append({"case": "webhook gửi lại lần 2 bị bỏ qua", "ok": first == 1 and second == 0,
                             "detail": [first, second]})
cur.execute("INSERT INTO payments (gateway, gateway_txn_id, amount, status) VALUES ('sepay','TX1',199000,'succeeded')")
expect_dup("giao dịch trùng mã bị chặn",
           "INSERT INTO payments (gateway, gateway_txn_id, amount, status) VALUES ('sepay','TX1',199000,'succeeded')", ())
expect_dup("email trùng khác hoa thường bị chặn",
           "INSERT INTO users (public_id, email, name) VALUES ('X1','USER1@EXAMPLE.COM','x')", ())
cur.execute("INSERT INTO review_logs (user_id, card_id, client_uid, mode, rating, box_before, box_after, reviewed_at) "
            "VALUES (1,1,'01HZZZZZZZZZZZZZZZZZZZZZZZ','quiz',2,1,2,%s)", (NOW,))
expect_dup("lượt chấm gửi trùng bị chặn",
           "INSERT INTO review_logs (user_id, card_id, client_uid, mode, rating, box_before, box_after, reviewed_at) "
           "VALUES (1,1,'01HZZZZZZZZZZZZZZZZZZZZZZZ','quiz',2,1,2,%s)", (NOW,))
try:
    cur.execute("INSERT INTO verbs (lang, infinitive, created_at) VALUES ('de','zahlen',%s), ('de','zählen',%s)", (NOW, NOW))
    results["behaviour"].append({"case": "zahlen và zählen là 2 động từ khác nhau", "ok": True, "detail": cur.rowcount})
except pymysql.err.IntegrityError as e:
    results["behaviour"].append({"case": "zahlen và zählen là 2 động từ khác nhau", "ok": False, "detail": str(e)})
    failures.append("verbs collation")
cur.execute("INSERT INTO word_forms (lang, form, card_id, kind) VALUES ('de','häuser',1,'plural')")
cur.execute("SELECT COUNT(*) FROM word_forms WHERE lang='de' AND form='häuser'"); exact = cur.fetchone()[0]
cur.execute("SELECT COUNT(*) FROM word_forms WHERE lang='de' AND form='hauser'"); loose = cur.fetchone()[0]
results["behaviour"].append({"case": "tra dạng từ khớp chính xác (häuser ≠ hauser)", "ok": exact == 1 and loose == 0,
                             "detail": [exact, loose]})
try:
    cur.execute("INSERT INTO topics (lang, depth, code, slug, name_vi) VALUES ('de',3,'fä','x-1','x')")
    results["behaviour"].append({"case": "mã ascii từ chối ký tự có dấu", "ok": False, "detail": "đã nhận"})
    failures.append("ascii")
except pymysql.err.MySQLError as e:
    results["behaviour"].append({"case": "mã ascii từ chối ký tự có dấu", "ok": True, "detail": e.args[0]})
cur.execute("ALTER TABLE review_logs REORGANIZE PARTITION pmax INTO ("
            "PARTITION p2029 VALUES LESS THAN (1893456000), PARTITION pmax VALUES LESS THAN MAXVALUE)")
cur.execute("SELECT COUNT(*) FROM information_schema.partitions WHERE table_schema=%s AND table_name='review_logs'", (DB,))
results["behaviour"].append({"case": "thêm partition năm mới không mất dữ liệu", "ok": cur.fetchone()[0] == 5,
                             "detail": "p2029 added"})
cur.execute("SELECT COUNT(*) FROM review_logs")
results["behaviour"].append({"case": "đếm lại nhật ký sau khi thêm partition", "ok": cur.fetchone()[0] == 600001,
                             "detail": "600001"})

# --- AI dùng khoá chung, chấm phát âm, giới hạn tuổi
cur.execute("INSERT INTO users (public_id, email, name, birth_date) VALUES ('AI1','old@example.com','Người lớn tuổi',%s)",
            (-315619200,))  # 1960-01-01: trước 1970 phải lưu được
cur.execute("SELECT birth_date FROM users WHERE public_id='AI1'")
results["behaviour"].append({"case": "ngày sinh trước 1970 lưu được (INT có dấu)", "ok": cur.fetchone()[0] == -315619200,
                             "detail": "1960-01-01"})
adult_cut = NOW - 18 * 365 * 86400 - 5 * 86400   # mốc 18 tuổi gần đúng; ứng dụng tính theo lịch
cur.execute("INSERT INTO users (public_id, email, name, birth_date) VALUES ('AI2','teen@example.com','Học sinh',%s),"
            "('AI3','noage@example.com','Chưa khai',NULL)", (NOW - 16 * 365 * 86400,))
cur.execute("SELECT public_id FROM users WHERE public_id IN ('AI1','AI2','AI3') AND birth_date IS NOT NULL "
            "AND birth_date <= %s ORDER BY public_id", (adult_cut,))
allowed = [r[0] for r in cur.fetchall()]
results["behaviour"].append({"case": "chỉ người khai từ 18 tuổi được dùng AI (16 tuổi và chưa khai bị chặn)",
                             "ok": allowed == ["AI1"], "detail": allowed})
cur.execute("INSERT INTO ai_requests (user_id, kind, status, created_at) VALUES (1,'translate','ok',%s)", (NOW,))
cur.execute("SELECT provider FROM ai_requests WHERE id=LAST_INSERT_ID()")
results["behaviour"].append({"case": "nhật ký AI mặc định nhà cung cấp gemini", "ok": cur.fetchone()[0] == "gemini",
                             "detail": "gemini"})
cur.execute("INSERT INTO user_ai_lessons (user_id, cache_hash, title, created_at) VALUES (1,%s,'Đi chợ',%s)",
            ("a" * 64, NOW))
expect_dup("một bài học AI không lưu trùng cho cùng người",
           "INSERT INTO user_ai_lessons (user_id, cache_hash, title, created_at) VALUES (1,%s,'Đi chợ',%s)", ("a" * 64, NOW))
words = json.dumps([{"word": "Brötchen", "score": 62, "error": "Mispronunciation", "phonemes": [{"p": "øː", "score": 40}]}],
                   ensure_ascii=False)
cur.execute("INSERT INTO pronunciation_attempts (user_id, reference_text, provider, status, overall_score, words, created_at) "
            "VALUES (1,'Ich möchte zwei Brötchen.','azure','ok',78,%s,%s)", (words, NOW))
cur.execute("SELECT JSON_EXTRACT(words, '$[0].word') FROM pronunciation_attempts WHERE id=LAST_INSERT_ID()")
results["behaviour"].append({"case": "chấm phát âm lưu điểm từng từ có ö, ß", "ok": cur.fetchone()[0] == '"Brötchen"',
                             "detail": "Brötchen"})
try:
    cur.execute("INSERT INTO pronunciation_attempts (user_id, reference_text, provider, status, overall_score, created_at) "
                "VALUES (1,'x','azure','ok',300,%s)", (NOW,))
    results["behaviour"].append({"case": "điểm phát âm ngoài 0–255 bị chặn", "ok": False, "detail": "đã nhận"})
except pymysql.err.MySQLError as e:
    results["behaviour"].append({"case": "điểm phát âm ngoài 0–255 bị chặn", "ok": True, "detail": e.args[0]})
cur.execute("INSERT INTO ai_conversations (public_id, user_id, scenario, created_at) VALUES ('C1',1,'restaurant',%s)", (NOW,))
conv = cur.lastrowid
cur.executemany("INSERT INTO ai_messages (conversation_id, role, content, created_at) VALUES (%s,%s,%s,%s)",
                [(conv, "user", "Ich möchte bestellen.", NOW), (conv, "assistant", "Gern! Was möchten Sie?", NOW + 1)])
cur.execute("DELETE FROM ai_messages WHERE conversation_id=%s", (conv,))
cur.execute("DELETE FROM ai_conversations WHERE id=%s", (conv,))
cur.execute("SELECT COUNT(*) FROM ai_messages WHERE conversation_id=%s", (conv,))
results["behaviour"].append({"case": "xoá trò chuyện thì xoá hẳn tin nhắn", "ok": cur.fetchone()[0] == 0, "detail": conv})

# --- Gói: lúc đầu mở đủ chức năng cho mọi tài khoản; trọn đời không kèm AI
def setting(name):
    cur.execute("SELECT value FROM settings WHERE name=%s", (name,))
    row = cur.fetchone()
    return json.loads(row[0]) if row else None


def plan_values(code):
    cur.execute("SELECT pe.entitlement_code, pe.value FROM plan_entitlements pe JOIN plans p ON p.id = pe.plan_id "
                "WHERE p.code=%s", (code,))
    return {c: json.loads(v) for c, v in cur.fetchall()}


def ai_daily(user_plan):
    """Quy tắc G4 sẽ làm trong code: công tắc bật thì lấy giá trị tốt hơn giữa gói của người dùng và gói mở."""
    own = plan_values(user_plan)["ai_daily"]
    return max(own, plan_values(setting("plans.open_access_plan"))["ai_daily"]) if setting("plans.open_access") is True else own


open_plan = setting("plans.open_access_plan")
full = plan_values(open_plan)
cur.execute("SELECT COUNT(*) FROM entitlements"); n_ent = cur.fetchone()[0]
results["behaviour"].append({
    "case": "công tắc mở đủ chức năng đang bật, trỏ tới gói có đủ 8 quyền lợi (7 chế độ, AI, offline)",
    "ok": setting("plans.open_access") is True and len(full) == n_ent == 8 and len(full["modes"]) == 7
          and full["ai_daily"] == 50 and full["pron_daily"] == 20 and full["offline"] is True,
    "detail": open_plan})
life = plan_values("lifetime")
results["behaviour"].append({
    "case": "gói trọn đời không kèm AI và chấm phát âm, quyền khác như Premium",
    "ok": life["ai_daily"] == 0 and life["pron_daily"] == 0
          and all(life[k] == full[k] for k in full if k not in ("ai_daily", "pron_daily")),
    "detail": [life["ai_daily"], life["pron_daily"]]})
on = [ai_daily("free"), ai_daily("lifetime")]
cur.execute("UPDATE settings SET value='false' WHERE name='plans.open_access'")
off = [ai_daily("free"), ai_daily("premium_12m"), ai_daily("lifetime")]
cur.execute("UPDATE settings SET value='true' WHERE name='plans.open_access'")
results["behaviour"].append({
    "case": "bật công tắc: Free và trọn đời được 50 lượt AI; tắt: Free 3, Premium giữ 50, trọn đời 0",
    "ok": on == [50, 50] and off == [3, 50, 0], "detail": {"bật": on, "tắt": off}})
expect_dup("không thêm được công tắc thứ hai cùng tên",
           "INSERT INTO settings (name, value, updated_at) VALUES ('plans.open_access','false',%s)", (NOW,))
expect_dup("một gói không có hai giá trị cho cùng quyền lợi",
           "INSERT INTO plan_entitlements (plan_id, entitlement_code, value) "
           "SELECT id, 'ai_daily', '5' FROM plans WHERE code='free'", ())
try:
    cur.execute("UPDATE settings SET value='bật' WHERE name='plans.open_access'")
    results["behaviour"].append({"case": "giá trị cấu hình không phải JSON bị chặn", "ok": False, "detail": "đã nhận"})
except pymysql.err.MySQLError as e:
    results["behaviour"].append({"case": "giá trị cấu hình không phải JSON bị chặn", "ok": True, "detail": e.args[0]})

for b in results["behaviour"]:
    if not b["ok"] and b["case"] not in failures:
        failures.append(b["case"])

results["failures"] = failures
print(json.dumps(results, ensure_ascii=False, indent=1, default=str))
sys.exit(1 if failures else 0)
