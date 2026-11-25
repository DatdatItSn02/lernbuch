# Lernbuch Online

Web học tiếng Đức cho người Việt, nâng cấp từ app Lernbuch. Laravel 13 + Filament 5 (trang quản trị) + React/TypeScript (app học), MariaDB.

Tài liệu: [phân tích và lộ trình](https://claude.ai/artifact/PF9Ncza5NRjJmkLfzSsamK) · [kế hoạch G0](https://claude.ai/artifact/T6qL5q6xLTpS3KpBMbgiNm)

## Chạy trên Windows bằng XAMPP

Cần: [XAMPP 8.4](https://www.apachefriends.org/download.html) (PHP 8.4, MariaDB 10.11), [Composer](https://getcomposer.org/download/) (khi cài, chọn `C:\xampp\php\php.exe`), [Node.js 22 LTS](https://nodejs.org/), [Git](https://git-scm.com/download/win).

1. Trong `C:\xampp\php\php.ini`, bỏ dấu `;` ở `extension=intl`, `extension=zip`, `extension=gd`. Bật MySQL trong XAMPP Control Panel.
2. Tạo database (XAMPP Control Panel → Shell → `mysql -u root`):
   ```sql
   CREATE DATABASE lernbuch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE lernbuch_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'lernbuch'@'localhost' IDENTIFIED BY 'doi-mat-khau-nay';
   GRANT ALL PRIVILEGES ON lernbuch.* TO 'lernbuch'@'localhost';
   GRANT ALL PRIVILEGES ON lernbuch_test.* TO 'lernbuch'@'localhost';
   FLUSH PRIVILEGES;
   ```
3. Lấy code và cài (PowerShell):
   ```powershell
   git clone https://github.com/DatdatItSn02/lernbuch.git F:\CongViec\lernbuch\app
   cd F:\CongViec\lernbuch\app
   composer install
   copy .env.example .env      # rồi sửa DB_USERNAME=lernbuch, DB_PASSWORD=<mật khẩu ở bước 2>
   php artisan key:generate
   php artisan migrate --seed  # tạo 91 bảng và dữ liệu khởi tạo
   npm ci
   npm run build
   php artisan app:create-admin
   php artisan serve
   ```
4. Mở http://127.0.0.1:8000 (trang chào), http://127.0.0.1:8000/app (app học), http://127.0.0.1:8000/admin (quản trị).

Chạy test: `php artisan test` (dùng database `lernbuch_test`), `npm run typecheck`, `npm run lint`, `npm test`.

## Cấu trúc

| Thư mục | Nội dung |
| --- | --- |
| `app/Modules/` | 10 module: Identity, Catalog, Learning, Practice, Ai, Billing, Engagement, Classroom, Support, Admin |
| `resources/js/app/` | app học React + TypeScript, khung PWA |
| `database/sql/` | `schema.sql` (nguồn gốc cấu trúc 91 bảng), `seed.sql` (dữ liệu khởi tạo, chạy lại không nhân đôi) |
| `database/migrations/` | 9 migration sinh từ `schema.sql` bằng `tools/schema/gen_migrations.py`; không sửa tay |
| `tools/schema/` | so khớp migration với `schema.sql`, kiểm quy ước, `test_schema.py` |
| `import/` | định dạng và trình kiểm file nhập nội dung |
| `deploy/`, `docs/huong-dan-alwaysdata.md` | deploy lên alwaysdata (đang tắt; bật bằng biến `DEPLOY_ENABLED`) |

## Quy ước database

Không khoá ngoại; thời gian lưu Unix timestamp (INT), trừ 10 bảng của Laravel; bảng có trang quản lý đủ cột `created_*`, `updated_*`, `deleted_*`. Đổi cấu trúc bằng migration mới, mỗi lần deploy chỉ thêm, không xoá cột.

## CI

GitHub Actions chạy mỗi lần đẩy code: Pint, Larastan, migrate lên và xuống, so khớp với `schema.sql`, seeder chạy hai lần, `test_schema.py`, Pest, TypeScript, ESLint, Vitest, build. Mỗi đêm chạy lại trên MariaDB 11.4, 11.8 và MySQL 8.4. Tóm tắt kết quả ghi ở nhánh `ci-results`.
