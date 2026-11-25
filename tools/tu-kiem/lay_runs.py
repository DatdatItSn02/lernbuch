"""Lấy danh sách lần chạy GitHub Actions kèm job (thời gian, kết quả) bằng gh, ghi ra JSON cho tu_kiem.py.

Dùng: python3 tools/tu-kiem/lay_runs.py --out /tmp/runs.json [--limit 300]
Cần gh đã đăng nhập và có quyền đọc repo.
"""
import argparse
import json
import subprocess
import sys

FIELDS = 'databaseId,workflowName,headBranch,headSha,displayTitle,event,status,conclusion,createdAt'


def gh_json(args):
    out = subprocess.run(['gh', *args], check=True, capture_output=True, text=True).stdout
    return json.loads(out)


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', required=True)
    ap.add_argument('--limit', type=int, default=300)
    a = ap.parse_args(argv)
    runs = gh_json(['run', 'list', '--limit', str(a.limit), '--json', FIELDS])
    result = []
    for r in runs:
        if r.get('status') != 'completed':
            continue
        jobs = gh_json(['run', 'view', str(r['databaseId']), '--json', 'jobs'])['jobs']
        result.append({
            'id': r['databaseId'], 'workflow': r['workflowName'], 'branch': r['headBranch'],
            'sha': r['headSha'], 'title': r['displayTitle'], 'event': r['event'],
            'conclusion': r['conclusion'], 'created': r['createdAt'],
            'jobs': [{'name': j['name'], 'conclusion': j['conclusion'], 'started': j.get('startedAt'),
                      'completed': j.get('completedAt')} for j in jobs],
        })
    with open(a.out, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=1)
    print(f'{len(result)} lần chạy -> {a.out}', file=sys.stderr)


if __name__ == '__main__':
    main()
