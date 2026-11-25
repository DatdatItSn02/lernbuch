#!/usr/bin/env python3
"""Sinh migration Laravel từ database dựng bằng schema.sql.

Đọc information_schema của database tham chiếu (đã nạp schema.sql), sinh 9 file
migration theo 9 nhóm bảng trong schema.sql. Phần Schema builder không có
(partition của review_logs) lấy nguyên văn từ schema.sql.

Dùng:
    python3 tools/schema/gen_migrations.py --schema database/sql/schema.sql \
        --db lernbuch_ref --out database/migrations
"""
import argparse
import os
import re
import sys

import pymysql

GROUPS = {
    1: "identity",
    2: "content",
    3: "learning",
    4: "ai",
    5: "billing",
    6: "engagement",
    7: "support_config",
    8: "classroom",
    9: "framework",
}
DATE_PREFIX = "2026_11_25_0000"


def parse_schema(path):
    """Trả về (danh sách (nhóm, bảng) theo thứ tự, dict bảng -> đoạn PARTITION BY)."""
    order, partitions = [], {}
    section, current, part_lines = None, None, None
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            m = re.match(r"^-- (\d)\. ", line)
            if m:
                section = int(m.group(1))
                continue
            m = re.match(r"^CREATE TABLE (\w+)", line)
            if m:
                if section is None:
                    sys.exit(f"bảng {m.group(1)} nằm ngoài nhóm nào")
                current = m.group(1)
                order.append((section, current))
                continue
            if line.startswith("PARTITION BY") and current:
                part_lines = [line.rstrip()]
                continue
            if part_lines is not None:
                part_lines.append(line.rstrip())
                if line.strip().endswith(");"):
                    partitions[current] = "\n".join(part_lines)[:-1]  # bỏ dấu ;
                    part_lines = None
    return order, partitions


def php_str(value):
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def php_default(col):
    raw = col["COLUMN_DEFAULT"]
    if raw is None or raw == "NULL":
        return None
    if raw.lower() in ("current_timestamp()", "current_timestamp"):
        return "useCurrent"
    if raw.startswith("'") and raw.endswith("'"):
        return php_str(raw[1:-1].replace("''", "'"))
    if re.fullmatch(r"-?\d+", raw):
        return raw
    if re.fullmatch(r"-?\d+\.\d+", raw):
        return raw
    sys.exit(f"không hiểu giá trị mặc định {raw!r} của {col['TABLE_NAME']}.{col['COLUMN_NAME']}")


def column_call(col, json_cols, composite_pk):
    name = col["COLUMN_NAME"]
    t = col["DATA_TYPE"]
    ct = col["COLUMN_TYPE"]
    unsigned = "unsigned" in ct
    auto = "auto_increment" in col["EXTRA"]
    q = php_str(name)

    def length():
        return int(col["CHARACTER_MAXIMUM_LENGTH"])

    if auto:
        if composite_pk:
            base = {"bigint": "unsignedBigInteger", "int": "unsignedInteger", "smallint": "unsignedSmallInteger"}[t]
            return f"$table->{base}({q}, true)"
        base = {"bigint": "bigIncrements", "int": "increments", "smallint": "smallIncrements",
                "mediumint": "mediumIncrements", "tinyint": "tinyIncrements"}[t]
        return f"$table->{base}({q})"
    if t in ("bigint", "int", "mediumint", "smallint"):
        base = {"bigint": "BigInteger", "int": "Integer", "mediumint": "MediumInteger", "smallint": "SmallInteger"}[t]
        return f"$table->{'unsigned' + base if unsigned else base[0].lower() + base[1:]}({q})"
    if t == "tinyint":
        if ct == "tinyint(1)":
            return f"$table->boolean({q})"
        return f"$table->{'unsignedTinyInteger' if unsigned else 'tinyInteger'}({q})"
    if t == "decimal":
        call = f"$table->decimal({q}, {col['NUMERIC_PRECISION']}, {col['NUMERIC_SCALE']})"
        return call + ("->unsigned()" if unsigned else "")
    if t == "float":
        return f"$table->float({q}, 24)" + ("->unsigned()" if unsigned else "")
    if t == "double":
        return f"$table->double({q})" + ("->unsigned()" if unsigned else "")
    if t == "char":
        return f"$table->char({q}, {length()})"
    if t == "varchar":
        return f"$table->string({q}, {length()})"
    if t == "varbinary":
        return f"$table->binary({q}, {int(col['CHARACTER_OCTET_LENGTH'])})"
    if t == "binary":
        return f"$table->binary({q}, {int(col['CHARACTER_OCTET_LENGTH'])}, fixed: true)"
    if t == "text":
        return f"$table->text({q})"
    if t == "mediumtext":
        return f"$table->mediumText({q})"
    if t == "longtext":
        return f"$table->{'json' if name in json_cols else 'longText'}({q})"
    if t == "json":
        return f"$table->json({q})"
    if t == "timestamp":
        return f"$table->timestamp({q})"
    sys.exit(f"chưa hỗ trợ kiểu {ct} ở {col['TABLE_NAME']}.{name}")


def table_code(cur, db, table, partition):
    cur.execute("SELECT ENGINE, TABLE_COLLATION, TABLE_COMMENT FROM information_schema.TABLES "
                "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s", (db, table))
    engine, tcoll, tcomment = cur.fetchone()
    tcharset = tcoll.split("_")[0]
    cur.execute("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s "
                "ORDER BY ORDINAL_POSITION", (db, table))
    names = [d[0] for d in cur.description]
    cols = [dict(zip(names, r)) for r in cur.fetchall()]
    cur.execute("SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS "
                "WHERE CONSTRAINT_SCHEMA=%s AND TABLE_NAME=%s", (db, table))
    json_cols = set()
    for cname, clause in cur.fetchall():
        m = re.fullmatch(r"json_valid\(`(\w+)`\)", clause)
        if not m:
            sys.exit(f"ràng buộc CHECK lạ ở {table}: {clause}")
        json_cols.add(m.group(1))
    cur.execute("SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS "
                "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s ORDER BY INDEX_NAME='PRIMARY' DESC, INDEX_NAME, SEQ_IN_INDEX",
                (db, table))
    indexes = {}
    for iname, non_unique, _seq, cname, sub in cur.fetchall():
        if sub is not None:
            sys.exit(f"chỉ mục tiền tố {table}.{iname} chưa hỗ trợ")
        indexes.setdefault(iname, {"unique": not non_unique, "cols": []})["cols"].append(cname)
    pk = indexes.pop("PRIMARY", None)
    auto_cols = [c["COLUMN_NAME"] for c in cols if "auto_increment" in c["EXTRA"]]
    composite_pk = bool(pk and len(pk["cols"]) > 1)

    lines = [f"        Schema::create({php_str(table)}, function (Blueprint $table) {{",
             f"            $table->engine = {php_str(engine)};",
             f"            $table->charset = {php_str(tcharset)};",
             f"            $table->collation = {php_str(tcoll)};"]
    for col in cols:
        call = column_call(col, json_cols, composite_pk)
        if col["IS_NULLABLE"] == "YES":
            call += "->nullable()"
        d = php_default(col)
        if d == "useCurrent":
            call += "->useCurrent()"
        elif d is not None:
            call += f"->default({d})"
        if col["CHARACTER_SET_NAME"] and col["COLUMN_NAME"] not in json_cols and (
                col["CHARACTER_SET_NAME"] != tcharset or col["COLLATION_NAME"] != tcoll):
            call += f"->charset({php_str(col['CHARACTER_SET_NAME'])})->collation({php_str(col['COLLATION_NAME'])})"
        if col["COLUMN_COMMENT"]:
            call += f"->comment({php_str(col['COLUMN_COMMENT'])})"
        lines.append(f"            {call};")
    if pk and not (len(pk["cols"]) == 1 and pk["cols"][0] in auto_cols):
        lines.append("            $table->primary([" + ", ".join(php_str(c) for c in pk["cols"]) + "]);")
    for iname, idx in indexes.items():
        method = "unique" if idx["unique"] else "index"
        lines.append(f"            $table->{method}([" + ", ".join(php_str(c) for c in idx["cols"]) +
                     f"], {php_str(iname)});")
    if tcomment:
        lines.append(f"            $table->comment({php_str(tcomment)});")
    lines.append("        });")
    if partition:
        lines.append(f"        DB::statement({php_str('ALTER TABLE ' + table + ' ' + partition)});")
    return "\n".join(lines)


TEMPLATE = """<?php

// File sinh tự động bởi tools/schema/gen_migrations.py từ database/sql/schema.sql.
// Không sửa tay: muốn đổi cấu trúc thì viết migration mới.

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
{db_import}use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{{
    public function up(): void
    {{
{up}
    }}

    public function down(): void
    {{
{down}
    }}
}};
"""


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--schema", required=True)
    ap.add_argument("--db", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--host", default=os.environ.get("DB_HOST", "127.0.0.1"))
    ap.add_argument("--port", type=int, default=int(os.environ.get("DB_PORT", "3306")))
    ap.add_argument("--user", default=os.environ.get("DB_USER", "root"))
    ap.add_argument("--password", default=os.environ.get("DB_PASSWORD", ""))
    args = ap.parse_args()

    order, partitions = parse_schema(args.schema)
    kw = dict(user=args.user, password=args.password, charset="utf8mb4")
    if args.host == "localhost" or os.environ.get("DB_SOCKET"):
        kw["unix_socket"] = os.environ.get("DB_SOCKET", "/run/mysqld/mysqld.sock")
    else:
        kw.update(host=args.host, port=args.port)
    conn = pymysql.connect(**kw)
    cur = conn.cursor()
    cur.execute("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s", (args.db,))
    in_db = {r[0] for r in cur.fetchall()}
    in_schema = {t for _, t in order}
    if in_db != in_schema:
        sys.exit(f"database và schema.sql lệch nhau: {sorted(in_db ^ in_schema)}")

    os.makedirs(args.out, exist_ok=True)
    written = []
    for num, slug in GROUPS.items():
        tables = [t for g, t in order if g == num]
        up = "\n\n".join(table_code(cur, args.db, t, partitions.get(t)) for t in tables)
        down = "\n".join(f"        Schema::dropIfExists({php_str(t)});" for t in reversed(tables))
        path = os.path.join(args.out, f"{DATE_PREFIX}{num:02d}_create_{slug}_tables.php")
        with open(path, "w", encoding="utf-8") as fh:
            db_import = "use Illuminate\\Support\\Facades\\DB;\n" if "DB::" in up else ""
            fh.write(TEMPLATE.format(up=up, down=down, db_import=db_import))
        written.append((path, len(tables)))
    for path, n in written:
        print(f"{n:3d} bảng  {path}")
    print(f"tổng {sum(n for _, n in written)} bảng")


if __name__ == "__main__":
    main()
