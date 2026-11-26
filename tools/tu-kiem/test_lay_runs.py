"""Test lay_runs.py với gh giả: lỗi tạm thì thử lại, lỗi mãi thì báo rõ, ghi đúng dạng JSON cho tu_kiem.py.

Chạy: python3 -m unittest tools/tu-kiem/test_lay_runs.py
"""
import contextlib
import io
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).parent))
import lay_runs  # noqa: E402


def done(code, out='', err=''):
    return subprocess.CompletedProcess(['gh'], code, out, err)


class LayRunsTest(unittest.TestCase):
    def test_gh_loi_tam_thi_thu_lai(self):
        with mock.patch.object(lay_runs.subprocess, 'run', side_effect=[done(1, err='HTTP 502'), done(1, err='HTTP 502'), done(0, '{"jobs": []}')]) as run, \
                mock.patch.object(lay_runs.time, 'sleep') as sleep:
            self.assertEqual(lay_runs.gh_json(['run', 'view', '1', '--json', 'jobs']), {'jobs': []})
        self.assertEqual(run.call_count, 3)
        self.assertEqual(sleep.call_count, 2)

    def test_gh_loi_mai_thi_bao_ro(self):
        with mock.patch.object(lay_runs.subprocess, 'run', return_value=done(1, err='HTTP 404: Not Found')), \
                mock.patch.object(lay_runs.time, 'sleep'):
            with self.assertRaises(RuntimeError) as ctx:
                lay_runs.gh_json(['run', 'view', '9', '--json', 'jobs'])
        self.assertIn('lỗi sau 3 lần thử', str(ctx.exception))
        self.assertIn('HTTP 404', str(ctx.exception))

    def test_ghi_json_bo_qua_lan_dang_chay(self):
        runs = [
            {'databaseId': 2, 'workflowName': 'ci', 'headBranch': 'main', 'headSha': 'b' * 40, 'displayTitle': 'đang chạy',
             'event': 'push', 'status': 'in_progress', 'conclusion': '', 'createdAt': '2026-11-27T01:00:00Z'},
            {'databaseId': 1, 'workflowName': 'ci', 'headBranch': 'main', 'headSha': 'a' * 40, 'displayTitle': 'xong',
             'event': 'push', 'status': 'completed', 'conclusion': 'success', 'createdAt': '2026-11-27T00:00:00Z'},
        ]
        jobs = {'jobs': [{'name': 'deploy lên lernbuch.alwaysdata.net', 'conclusion': 'success',
                          'startedAt': '2026-11-27T00:01:00Z', 'completedAt': '2026-11-27T00:03:00Z'}]}

        def fake(args, tries=3):
            return runs if args[:2] == ['run', 'list'] else jobs

        with tempfile.TemporaryDirectory() as d, mock.patch.object(lay_runs, 'gh_json', side_effect=fake), \
                contextlib.redirect_stderr(io.StringIO()):
            out = Path(d, 'runs.json')
            lay_runs.main(['--out', str(out)])
            data = json.loads(out.read_text(encoding='utf-8'))
        self.assertEqual([r['id'] for r in data], [1])
        self.assertEqual(data[0]['jobs'][0], {'name': 'deploy lên lernbuch.alwaysdata.net', 'conclusion': 'success',
                                              'started': '2026-11-27T00:01:00Z', 'completed': '2026-11-27T00:03:00Z'})


if __name__ == '__main__':
    unittest.main()
