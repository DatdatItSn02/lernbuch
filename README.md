# Lernbuch Online

Web học tiếng Đức cho người Việt, nâng cấp từ app Lernbuch. Laravel 13 + Filament 5 (trang quản trị) + React/TypeScript (app học), MariaDB.

Tài liệu: [phân tích và lộ trình](https://claude.ai/artifact/PF9Ncza5NRjJmkLfzSsamK) · [kế hoạch G0](https://claude.ai/artifact/T6qL5q6xLTpS3KpBMbgiNm) · [kế hoạch G1](https://claude.ai/artifact/BVK1a1zmGTNdQmrxNT7TYF) · báo cáo [G0](docs/bao-cao-g0.md), [G1](docs/bao-cao-g1.md)

## Chạy trên máy (Windows), giống LoWo

Không dùng XAMPP (XAMPP trên máy đang là PHP 7.4, Lernbuch cần PHP 8.4 trở lên). Công cụ dùng chung với LoWo trong `F:\dev`: PHP 8.5, Composer, Node.js 26, MySQL 9.7 cổng 3307. XAMPP vẫn chạy song song như cũ.

Lần đầu: lấy code vào `F:\CongViec\lernbuch\app` (`git clone https://github.com/DatdatItSn02/lernbuch.git F:\CongViec\lernbuch\app`), bấm đúp `setup\cai-dat.bat` (máy đã cài LoWo thì chỉ tạo thêm database), rồi `setup\cap-nhat.bat`.

| Việc | Cách làm |
|---|---|
| Nhận code mới | Bấm đúp `setup\cap-nhat.bat`: lấy code nhánh `main` từ GitHub, sửa `.env`, cài thư viện, migrate, seed, build rồi chạy toàn bộ test |
| Tạo tài khoản quản trị | `setup\tao-admin.bat` (chạy `php artisan app:create-admin`, hỏi email, họ tên, mật khẩu) |
| Nhập nội dung Lernbuch cũ | Chép `lernweb.zip` vào `F:\CongViec\lernbuch\nguon-cu`, bấm đúp `setup\nhap-du-lieu.bat`: chạy thử, báo số liệu, rồi mới ghi; chạy lại không nhân đôi. Kết quả ở `setup\ket-qua-nhap-du-lieu.txt`; trên alwaysdata xem `docs/nhap-lernbuch-cu.md` |
| Tạo audio đọc từ | GitHub → Actions → audio → Run workflow: `nghe-thu` đọc 20 chữ mẫu để nghe thử, `tao-het` tạo phần còn thiếu trên alwaysdata (`docs/tao-audio.md`) |
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
| `database/migrations/` | 9 migration đầu sinh từ `schema.sql` bằng `tools/schema/gen_migrations.py` (không sửa tay); thay đổi sau đó là migration mới (G1 thêm 6) |
| `tools/schema/` | so khớp migration với `schema.sql`, kiểm quy ước, `test_schema.py` |
| `import/` | định dạng và trình kiểm file nhập nội dung |
| `setup/` | script chạy trên Windows (cài, cập nhật, test, mở web) và test của chúng trong `setup/tests/` |
| `deploy/`, `docs/huong-dan-alwaysdata.md` | deploy lên alwaysdata: đẩy lên `main` thì CI deploy (bật bằng biến `DEPLOY_ENABLED`), migration hỏng thì tự quay lại bản trước |
| `tools/tu-kiem/` | chấm 16 mục tự kiểm của G0 (`tu_kiem.py`) và 19 mục của G1 (`tu_kiem_g1.py`) từ nhánh `ci-results`: log CI, bảng kết quả từng test (`gon_ket_qua.py`), log máy chủ của từng lần deploy, quay lại, tạo audio; cộng kết quả thử tay (`docs/tu-kiem-g*-tay.json`). Kết quả ở `docs/tu-kiem-g0.json`, `docs/tu-kiem-g1.json`, báo cáo ở `docs/bao-cao-g0.md`, `docs/bao-cao-g1.md` |
| `docs/huong-dan-windows.md` | chạy trên Windows từng bước, kể cả thử lịch và hàng đợi |
| `app/Support/NhapLernbuch/`, `docs/nhap-lernbuch-cu.md` | lệnh `app:nhap-lernbuch` nhập nội dung Lernbuch cũ (chạy thử, báo cáo, rồi mới ghi), cách chạy trên máy và trên alwaysdata |
| `tools/audio/`, `docs/tao-audio.md` | audio đọc từ tạo sẵn bằng Piper (workflow `audio`), danh sách giọng được dùng để bán |

## App học: cài lên máy, học khi mất mạng (PWA)

- Cài: Chrome, Edge (máy tính, Android) có nút **Cài Lernbuch** trong Cài đặt; iPhone, iPad mở bằng Safari, bấm Chia sẻ rồi "Thêm vào MH chính". App mở từ màn hình chính như app thường.
- Học khi mất mạng: mở một chủ đề, bấm **Tải để học khi không có mạng** (tải thẻ và audio của chủ đề). Mất mạng thì app vẫn mở, có dòng báo; mở phiên học thì phiên lập ngay trên máy từ chủ đề đã tải (phiên đang dở hôm nay thì học tiếp). Lượt chấm nằm trên máy và tự gửi khi có mạng, mỗi lượt máy chủ ghi đúng một lần.
- Service worker ở `/sw.js` (mẫu `resources/sw/sw.js`, phiên bản theo bản build): lưu khung app, file build, font, các trang API đã xem (bản lưu có đầu `X-Lernbuch-Ban-Luu`) và audio của chủ đề đã tải. Đăng xuất thì gửi nốt lượt chấm rồi xoá mọi dữ liệu đã lưu trên máy.

## Trang quản trị

`/admin` (Filament), cho nhân viên theo vai trò: Người học (tìm, xem tiến độ, khoá và mở khoá), Chủ đề, Thẻ, Ngữ pháp, Bài tập, Động từ, Nguồn, Nhật ký thao tác (giá trị trước và sau của mọi lần sửa), Job lỗi (chạy lại, bỏ), Vai trò, Nhân viên, Cài đặt học. Người học không vào được; tài khoản quản trị đầu tiên tạo bằng `php artisan app:create-admin`.

## Quy ước database

Không khoá ngoại; thời gian lưu Unix timestamp (INT), trừ 10 bảng của Laravel; bảng có trang quản lý đủ cột `created_*`, `updated_*`, `deleted_*`. Đổi cấu trúc bằng migration mới, mỗi lần deploy chỉ thêm, không xoá cột.

## CI

GitHub Actions chạy mỗi lần đẩy code: Pint, Larastan, migrate lên và xuống, so khớp với `schema.sql`, seeder chạy hai lần, `test_schema.py`, Pest, đo hiệu năng với dữ liệu cỡ thật, TypeScript, ESLint, Vitest, build, tương phản màu, Playwright (cỡ máy tính và điện thoại, sáng và tối, axe WCAG 2.2 AA). Commit có `[anh]` thì Playwright chụp thêm ảnh mọi màn và lưu DOM để chạy bộ dò Impeccable; lần chạy thường không chụp thêm, cho nhánh `ci-results` khỏi phình. Workflow `windows` chạy bộ `setup\` trên Windows PowerShell 5.1 y như máy người dùng (cài PHP 8.5, Node.js 26, MySQL 9.7, cập nhật, chạy test, mở web thử), chỉ khi đổi `setup\`, gói thư viện, migration, seeder và mỗi tuần một lần (máy Windows tính gấp đôi phút CI). Mỗi đêm chạy lại trên MariaDB 10.11, 11.4, 11.8 và MySQL 8.4, 9.7. Tóm tắt kết quả ghi ở nhánh `ci-results`: log từng job, bảng kết quả từng test ở `ket-qua/php`, `ket-qua/web`, ảnh ở `anh/`.

Chấm lại tự kiểm (16 mục G0, 19 mục G1):

```bash
git clone --depth 1 -b ci-results https://github.com/DatdatItSn02/lernbuch.git /tmp/ci-results
python3 tools/tu-kiem/lay_runs.py --out /tmp/runs.json
python3 tools/tu-kiem/tu_kiem.py --ci /tmp/ci-results --runs /tmp/runs.json --tay docs/tu-kiem-g0-tay.json --out docs/tu-kiem-g0.json
python3 tools/tu-kiem/tu_kiem_g1.py --ci /tmp/ci-results --tay docs/tu-kiem-g1-tay.json --out docs/tu-kiem-g1.json
```
