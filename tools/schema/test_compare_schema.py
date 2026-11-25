"""Kiểm công cụ so khớp: hai database giống nhau thì 0 khác biệt; làm hỏng cố ý
một cột hay một chỉ mục thì công cụ chỉ đúng chỗ đó.

Chạy: python3 -m unittest tools/schema/test_compare_schema.py (cần máy chủ MariaDB/MySQL).
"""
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(__file__))
import compare_schema as cs  # noqa: E402

SCHEMA = os.path.join(os.path.dirname(__file__), "..", "..", "database", "sql", "schema.sql")
A, B = "lernbuch_cmp_a", "lernbuch_cmp_b"


def load(cur, db):
    with open(SCHEMA, encoding="utf-8") as fh:
        sql = "".join(line for line in fh if not line.startswith(("CREATE DATABASE", "USE ")))
    cur.execute(f"DROP DATABASE IF EXISTS {db}")
    cur.execute(f"CREATE DATABASE {db} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
    cur.execute(f"USE {db}")
    for stmt in sql.split(";\n"):
        if stmt.strip() and not all(l.strip().startswith("--") or not l.strip() for l in stmt.splitlines()):
            cur.execute(stmt)


class CompareSchemaTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.conn = cs.connect()
        cls.cur = cls.conn.cursor()
        load(cls.cur, A)
        load(cls.cur, B)

    @classmethod
    def tearDownClass(cls):
        cls.cur.execute(f"DROP DATABASE IF EXISTS {A}")
        cls.cur.execute(f"DROP DATABASE IF EXISTS {B}")

    def test_1_identical_databases_have_no_difference(self):
        diffs = cs.diff(cs.snapshot(self.cur, A), cs.snapshot(self.cur, B))
        self.assertEqual(diffs, [])
        self.assertEqual(len(cs.snapshot(self.cur, A)), 91)

    def test_2_changed_column_type_is_reported_exactly(self):
        self.cur.execute(f"ALTER TABLE {B}.users MODIFY name VARCHAR(120) NOT NULL")
        diffs = cs.diff(cs.snapshot(self.cur, A), cs.snapshot(self.cur, B))
        self.assertEqual([(d["table"], d["kind"], d["name"], d["field"]) for d in diffs],
                         [("users", "column", "name", "COLUMN_TYPE")])

    def test_3_dropped_index_is_reported(self):
        self.cur.execute(f"ALTER TABLE {B}.users DROP INDEX uq_users_public_id")
        diffs = cs.diff(cs.snapshot(self.cur, A), cs.snapshot(self.cur, B))
        self.assertIn(("users", "index", "uq_users_public_id"), [(d["table"], d["kind"], d["name"]) for d in diffs])

    def test_4_foreign_key_is_reported(self):
        self.cur.execute(f"ALTER TABLE {B}.user_settings ADD CONSTRAINT fk_test FOREIGN KEY (user_id) "
                         f"REFERENCES {B}.users (id)")
        diffs = cs.diff(cs.snapshot(self.cur, A), cs.snapshot(self.cur, B))
        self.assertTrue(any(d["kind"] == "foreign_key" and d["table"] == "user_settings" for d in diffs))


if __name__ == "__main__":
    unittest.main()
