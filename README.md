# Lernbuch Online

Web học tiếng Đức cho người Việt, nâng cấp từ app Lernbuch. Laravel 13 + Filament 5 (trang quản trị) + React/TypeScript (app học), MariaDB.

Tài liệu: [phân tích và lộ trình](https://claude.ai/artifact/PF9Ncza5NRjJmkLfzSsamK) · [kế hoạch G0](https://claude.ai/artifact/T6qL5q6xLTpS3KpBMbgiNm)

## Chạy trên máy (Windows), giống LoWo

Không dùng XAMPP (XAMPP trên máy đang là PHP 7.4, Lernbuch cần PHP 8.4 trở lên). Công cụ dùng chung với LoWo trong `F:\dev`: PHP 8.5, Composer, Node.js 26, MySQL 9.7 cổng 3307. XAMPP vẫn chạy song song như cũ.

Lần đầu: lấy code vào `F:\CongViec\lernbuch\app` (`git clone https://github.com/DatdatItSn02/lernbuch.git F:\CongViec\lernbuch\app`), bấm đúp `setup\cai-dat.bat` (máy đã cài LoWo thì chỉ tạo thêm database), rồi `setup\cap-nhat.bat`.

| Việc | Cách làm |
|---|---|
| Nhận code mới | Bấm đúp `setup\cap-nhat.bat`: lấy code nhánh `main` từ GitHub, sửa `.env`, cài thư viện, migrate, seed, build rồi chạy toàn bộ test |
| Tạo tài khoản quản trị | `setup\tao-admin.bat` (chạy `php artisan app:create-admin`, hỏi email, họ tên, mật khẩu) |
| Mở web | `setup\chay-web.bat`, rồi vào http://127.0.0.1:8001 (trang chào), `/app` (app học), `/admin` (quản trị) |
| Chạy toàn bộ test | `setup\chay-test.bat`; kết quả ghi ở `setup\ket-qua-test.txt` |
| Cửa sổ lệnh dùng PHP 8.5 | `setup\moi-truong.bat` |
| Bật/tắt MySQL | `setup\mysql-bat.bat`, `setup\mysql-tat.bat` (MySQL dùng chung, tắt thì LoWo cũng không vào được database) |
| Gửi thông tin máy cho Claude | `setup\kiem-tra.bat`, ghi ở `setup\ket-qua-kiem-tra.txt` (không ghi nội dung `.env`) |

Web chạy ở `127.0.0.1:8001`, không dùng `localhost:8000` để không trùng cổng và cookie với LoWo khi mở cả hai. Database `lernbuch` (dữ liệu) và `lernbuch_test` (test), tài khoản `root` không mật khẩu, chỉ nghe trên máy này. Bản chạy thật trên alwaysdata dùng MariaDB; CI kiểm cả MariaDB và MySQL.

## Cấu trúc

| Thư mục | Nội dung |
| --- | --- |
| `app/Modules/` | 10 module: Identity, Catalog, Learning, Practice, Ai, Billing, Engagement, Classroom, Support, Admin |
| `resources/js/app/` | app học React + TypeScript, khung PWA |
| `database/sql/` | `schema.sql` (nguồn gốc cấu trúc 91 bảng), `seed.sql` (dữ liệu khởi tạo, chạy lại không nhân đôi) |
| `database/migrations/` | 9 migration sinh từ `schema.sql` bằng `tools/schema/gen_migrations.py`; không sửa tay |
| `tools/schema/` | so khớp migration với `schema.sql`, kiểm quy ước, `test_schema.py` |
| `import/` | định dạng và trình kiểm file nhập nội dung |
| `setup/` | script chạy trên Windows (cài, cập nhật, test, mở web) và test của chúng trong `setup/tests/` |
| `deploy/`, `docs/huong-dan-alwaysdata.md` | deploy lên alwaysdata (đang tắt; bật bằng biến `DEPLOY_ENABLED`) |
| `tools/tu-kiem/` | chấm 16 mục tự kiểm của G0 từ log CI và log máy chủ của từng lần deploy, quay lại (nhánh `ci-results`), cộng kết quả đo tay trên máy chủ (`docs/tu-kiem-g0-tay.json`); kết quả ở `docs/tu-kiem-g0.json`, báo cáo ở `docs/bao-cao-g0.md` |
| `docs/huong-dan-windows.md` | chạy trên Windows từng bước, kể cả thử lịch và hàng đợi |
| `app/Support/NhapLernbuch/`, `docs/nhap-lernbuch-cu.md` | lệnh `app:nhap-lernbuch` nhập nội dung Lernbuch cũ (chạy thử, báo cáo, rồi mới ghi), cách chạy trên máy và trên alwaysdata |

## Quy ước database

Không khoá ngoại; thời gian lưu Unix timestamp (INT), trừ 10 bảng của Laravel; bảng có trang quản lý đủ cột `created_*`, `updated_*`, `deleted_*`. Đổi cấu trúc bằng migration mới, mỗi lần deploy chỉ thêm, không xoá cột.

## CI

GitHub Actions chạy mỗi lần đẩy code: Pint, Larastan, migrate lên và xuống, so khớp với `schema.sql`, seeder chạy hai lần, `test_schema.py`, Pest, TypeScript, ESLint, Vitest, build. Workflow `windows` chạy bộ `setup\` trên Windows PowerShell 5.1 y như máy người dùng (cài PHP 8.5, Node.js 26, MySQL 9.7, cập nhật, chạy test, mở web thử), chỉ khi đổi `setup\`, gói thư viện, migration, seeder và mỗi tuần một lần (máy Windows tính gấp đôi phút CI). Mỗi đêm chạy lại trên MariaDB 10.11, 11.4, 11.8 và MySQL 8.4, 9.7. Tóm tắt kết quả ghi ở nhánh `ci-results` (ảnh trang chào và trang quản trị ở `anh/`).

Chấm lại 16 mục tự kiểm:

```bash
git clone --depth 1 -b ci-results https://github.com/DatdatItSn02/lernbuch.git /tmp/ci-results
python3 tools/tu-kiem/lay_runs.py --out /tmp/runs.json
python3 tools/tu-kiem/tu_kiem.py --ci /tmp/ci-results --runs /tmp/runs.json --tay docs/tu-kiem-g0-tay.json --out docs/tu-kiem-g0.json
```
