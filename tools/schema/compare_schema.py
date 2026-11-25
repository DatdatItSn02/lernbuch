#!/usr/bin/env python3
"""So khớp cấu trúc hai database trên cùng một máy chủ (thường: dựng từ schema.sql
và dựng bằng php artisan migrate). Bỏ qua bảng `migrations` của Laravel.

Dùng:
    python3 tools/schema/compare_schema.py --ref lernbuch_ref --db lernbuch [--json kq.json]
Thoát mã 1 khi có khác biệt.
"""
import argparse
import json
import os
import sys

import pymysql

IGNORE = {"migrations"}
COL_FIELDS = ("ORDINAL_POSITION", "COLUMN_TYPE", "IS_NULLABLE", "COLUMN_DEFAULT", "EXTRA",
              "CHARACTER_SET_NAME", "COLLATION_NAME", "COLUMN_COMMENT")


def connect():
    kw = dict(user=os.environ.get("DB_USER", "root"), password=os.environ.get("DB_PASSWORD", ""),
              charset="utf8mb4")
    if os.environ.get("DB_SOCKET"):
        kw["unix_socket"] = os.environ["DB_SOCKET"]
    else:
        kw.update(host=os.environ.get("DB_HOST", "127.0.0.1"), port=int(os.environ.get("DB_PORT", "3306")))
    return pymysql.connect(**kw)


def snapshot(cur, db):
    snap = {}
    cur.execute("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_COMMENT FROM information_schema.TABLES "
                "WHERE TABLE_SCHEMA=%s AND TABLE_TYPE='BASE TABLE'", (db,))
    for name, engine, coll, comment in cur.fetchall():
        if name in IGNORE:
            continue
        snap[name] = {"table": {"ENGINE": engine, "TABLE_COLLATION": coll, "TABLE_COMMENT": comment},
                      "columns": {}, "indexes": {}, "checks": {}, "partitions": [], "fks": []}
    cur.execute("SELECT TABLE_NAME, COLUMN_NAME, " + ", ".join(COL_FIELDS) +
                " FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s", (db,))
    for row in cur.fetchall():
        if row[0] in snap:
            snap[row[0]]["columns"][row[1]] = dict(zip(COL_FIELDS, row[2:]))
    cur.execute("SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, INDEX_TYPE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART "
                "FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
                (db,))
    for t, iname, non_unique, itype, _seq, col, sub in cur.fetchall():
        if t in snap:
            idx = snap[t]["indexes"].setdefault(iname, {"unique": not non_unique, "type": itype, "columns": []})
            idx["columns"].append(f"{col}({sub})" if sub else col)
    try:  # MariaDB có cột TABLE_NAME trong CHECK_CONSTRAINTS
        cur.execute("SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS "
                    "WHERE CONSTRAINT_SCHEMA=%s", (db,))
    except pymysql.err.OperationalError:  # MySQL: lấy tên bảng từ TABLE_CONSTRAINTS
        cur.execute("SELECT tc.TABLE_NAME, cc.CONSTRAINT_NAME, cc.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS cc "
                    "JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA "
                    "AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME AND tc.CONSTRAINT_TYPE = 'CHECK' "
                    "WHERE cc.CONSTRAINT_SCHEMA=%s", (db,))
    for t, cname, clause in cur.fetchall():
        if t in snap:
            snap[t]["checks"][cname] = clause
    cur.execute("SELECT TABLE_NAME, PARTITION_NAME, PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_DESCRIPTION "
                "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA=%s AND PARTITION_NAME IS NOT NULL "
                "ORDER BY TABLE_NAME, PARTITION_ORDINAL_POSITION", (db,))
    for t, pname, method, expr, desc in cur.fetchall():
        if t in snap:
            snap[t]["partitions"].append([pname, method, expr, desc])
    cur.execute("SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS "
                "WHERE CONSTRAINT_SCHEMA=%s", (db,))
    for t, cname in cur.fetchall():
        if t in snap:
            snap[t]["fks"].append(cname)
    return snap


def diff(ref, mig):
    out = []

    def add(table, kind, name, field, a, b):
        out.append({"table": table, "kind": kind, "name": name, "field": field, "ref": a, "db": b})

    for t in sorted(set(ref) | set(mig)):
        if t not in mig:
            add(t, "table", t, "exists", True, False)
            continue
        if t not in ref:
            add(t, "table", t, "exists", False, True)
            continue
        r, m = ref[t], mig[t]
        for f, v in r["table"].items():
            if m["table"][f] != v:
                add(t, "table", t, f, v, m["table"][f])
        for c in sorted(set(r["columns"]) | set(m["columns"])):
            if c not in m["columns"] or c not in r["columns"]:
                add(t, "column", c, "exists", c in r["columns"], c in m["columns"])
                continue
            for f in COL_FIELDS:
                if r["columns"][c][f] != m["columns"][c][f]:
                    add(t, "column", c, f, r["columns"][c][f], m["columns"][c][f])
        for i in sorted(set(r["indexes"]) | set(m["indexes"])):
            if r["indexes"].get(i) != m["indexes"].get(i):
                add(t, "index", i, "definition", r["indexes"].get(i), m["indexes"].get(i))
        if r["checks"] != m["checks"]:
            add(t, "check", "*", "definition", r["checks"], m["checks"])
        if r["partitions"] != m["partitions"]:
            add(t, "partition", "*", "definition", r["partitions"], m["partitions"])
        if r["fks"] or m["fks"]:
            add(t, "foreign_key", "*", "exists", r["fks"], m["fks"])
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--ref", required=True)
    ap.add_argument("--db", required=True)
    ap.add_argument("--json")
    args = ap.parse_args()
    cur = connect().cursor()
    ref, mig = snapshot(cur, args.ref), snapshot(cur, args.db)
    diffs = diff(ref, mig)
    result = {"ref": args.ref, "db": args.db, "tables": len(ref), "differences": diffs}
    if args.json:
        with open(args.json, "w", encoding="utf-8") as fh:
            json.dump(result, fh, ensure_ascii=False, indent=1, default=str)
    print(f"{len(ref)} bảng tham chiếu, {len(mig)} bảng so sánh, {len(diffs)} khác biệt")
    for d in diffs[:50]:
        print(json.dumps(d, ensure_ascii=False, default=str))
    sys.exit(1 if diffs else 0)


if __name__ == "__main__":
    main()
