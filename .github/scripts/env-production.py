"""In ra .env cho alwaysdata từ .env.production.example, điền bí mật lấy từ biến môi trường (GitHub Secrets).

DB_PASSWORD giữ cách điền cũ (nguyên văn). MAIL_PASSWORD bọc nháy để ký tự đặc biệt (#, $, khoảng trắng) không làm hỏng .env.
Chạy: DB_PASSWORD=... MAIL_PASSWORD=... python3 .github/scripts/env-production.py > /tmp/ten.env
"""
import os
import sys
from pathlib import Path


def dotenv_value(value):
    if value == '':
        return ''
    if "'" not in value:
        return "'" + value + "'"
    return '"' + value.replace('\\', '\\\\').replace('"', '\\"').replace('$', '\\$') + '"'


def render(template, env):
    out = template.replace('__DB_PASSWORD__', env.get('DB_PASSWORD', ''))
    return out.replace('__MAIL_PASSWORD__', dotenv_value(env.get('MAIL_PASSWORD', '')))


if __name__ == '__main__':
    root = Path(__file__).resolve().parents[2]
    sys.stdout.write(render((root / '.env.production.example').read_text(encoding='utf-8'), os.environ))
