"""Kiểm script quy ước: migration sạch thì 0 vi phạm; thêm cố ý khoá ngoại hay cột
TIMESTAMP thì bị bắt. Phần database cần máy chủ MariaDB/MySQL (DB_* như compare_schema).

Chạy: python3 -m unittest tools/schema/test_check_conventions.py
"""
import os
import shutil
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(__file__))
import check_conventions as cc  # noqa: E402
import compare_schema as cs  # noqa: E402

MIGRATIONS = os.path.join(os.path.dirname(__file__), "..", "..", "database", "migrations")


class ConventionsTest(unittest.TestCase):
    def test_generated_migrations_are_clean(self):
        self.assertEqual(cc.scan_migrations(MIGRATIONS), [])

    def test_foreign_key_in_migration_is_caught(self):
        tmp = tempfile.mkdtemp()
        try:
            with open(os.path.join(tmp, "2026_12_01_000000_add_bad.php"), "w", encoding="utf-8") as fh:
                fh.write("<?php\n$table->foreignId('user_id')->constrained();\n")
            problems = cc.scan_migrations(tmp)
            self.assertEqual(len(problems), 2)  # foreignId và constrained
            self.assertTrue(all("khoá ngoại" in p for p in problems))
        finally:
            shutil.rmtree(tmp)

    def test_timestamp_column_outside_framework_is_caught(self):
        tmp = tempfile.mkdtemp()
        try:
            with open(os.path.join(tmp, "2026_12_01_000001_add_dates.php"), "w", encoding="utf-8") as fh:
                fh.write("<?php\n$table->timestamps();\n$table->softDeletes();\n")
            with open(os.path.join(tmp, "2026_12_01_000002_framework_ok.php"), "w", encoding="utf-8") as fh:
                fh.write("<?php\n$table->timestamp('failed_at');\n")
            problems = cc.scan_migrations(tmp)
            self.assertEqual(len(problems), 2)
            self.assertTrue(all("add_dates" in p for p in problems))
        finally:
            shutil.rmtree(tmp)

    def test_database_with_foreign_key_and_datetime_is_caught(self):
        cur = cs.connect().cursor()
        cur.execute("DROP DATABASE IF EXISTS lernbuch_conv_test")
        cur.execute("CREATE DATABASE lernbuch_conv_test")
        try:
            cur.execute("CREATE TABLE lernbuch_conv_test.a (id INT PRIMARY KEY, created_at INT UNSIGNED)")
            cur.execute("CREATE TABLE lernbuch_conv_test.b (id INT PRIMARY KEY, a_id INT, seen_at DATETIME,"
                        " CONSTRAINT fk_b_a FOREIGN KEY (a_id) REFERENCES lernbuch_conv_test.a (id))")
            problems = cc.scan_database("lernbuch_conv_test")
            self.assertEqual(sorted(problems), ["b.seen_at: cột thời gian kiểu datetime, phải là số nguyên",
                                                "b: khoá ngoại fk_b_a"])
        finally:
            cur.execute("DROP DATABASE IF EXISTS lernbuch_conv_test")


if __name__ == "__main__":
    unittest.main()
