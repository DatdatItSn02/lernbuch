#!/usr/bin/env python3
"""Kiểm quy ước database của LoWo trên migration và trên database đã dựng.

1. Migration không dùng khoá ngoại (foreign, foreignId, constrained, references).
2. Ngoài nhóm bảng framework, migration không dùng cột TIMESTAMP/DATETIME
   (timestamps(), softDeletes(), timestamp(), dateTime(), date()): thời gian là số nguyên.
3. Database (khi có --db): không có khoá ngoại; mọi cột *_at ngoài bảng framework là INT/BIGINT.

Dùng: python3 tools/schema/check_conventions.py --migrations database/migrations [--db lernbuch]
Thoát mã 1 khi có vi phạm.
"""
import argparse
import glob
import os
import re
import sys

FRAMEWORK_TABLES = {"password_reset_tokens", "sessions", "cache", "cache_locks", "jobs", "job_batches",
                    "failed_jobs", "personal_access_tokens", "notifications", "features", "migrations"}
FK_PATTERNS = [r"->foreign\(", r"\bforeignId(For)?\(", r"->constrained\(", r"->references\(",
               r"->cascadeOnDelete\(", r"->restrictOnDelete\("]
TIME_PATTERNS = [r"->timestamps(Tz)?\(", r"->nullableTimestamps\(", r"->softDeletes(Tz)?\(",
                 r"->timestamp(Tz)?\(", r"->dateTime(Tz)?\(", r"->date\("]


def scan_migrations(folder):
    problems = []
    for path in sorted(glob.glob(os.path.join(folder, "*.php"))):
        text = open(path, encoding="utf-8").read()
        name = os.path.basename(path)
        for pat in FK_PATTERNS:
            for m in re.finditer(pat, text):
                line = text.count("\n", 0, m.start()) + 1
                problems.append(f"{name}:{line}: khoá ngoại ({m.group(0)})")
        if "framework" not in name:
            for pat in TIME_PATTERNS:
                for m in re.finditer(pat, text):
                    line = text.count("\n", 0, m.start()) + 1
                    problems.append(f"{name}:{line}: cột thời gian kiểu TIMESTAMP/DATE ({m.group(0)})")
    return problems


def scan_database(db):
    sys.path.insert(0, os.path.dirname(__file__))
    import compare_schema  # noqa: E402
    cur = compare_schema.connect().cursor()
    problems = []
    cur.execute("SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS "
                "WHERE CONSTRAINT_SCHEMA=%s", (db,))
    problems += [f"{t}: khoá ngoại {c}" for t, c in cur.fetchall()]
    cur.execute("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS "
                "WHERE TABLE_SCHEMA=%s AND COLUMN_NAME LIKE '%%\\_at'", (db,))
    for t, c, dtype in cur.fetchall():
        if t not in FRAMEWORK_TABLES and dtype not in ("int", "bigint"):
            problems.append(f"{t}.{c}: cột thời gian kiểu {dtype}, phải là số nguyên")
    return problems


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--migrations", required=True)
    ap.add_argument("--db")
    args = ap.parse_args()
    problems = scan_migrations(args.migrations)
    if args.db:
        problems += scan_database(args.db)
    for p in problems:
        print(p)
    print(f"{len(problems)} vi phạm quy ước")
    sys.exit(1 if problems else 0)


if __name__ == "__main__":
    main()
