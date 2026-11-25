# Báo cáo G0: Nền móng (Lernbuch Online)

Ngày 26/11/2026. Kết quả chi tiết từng mục: `docs/tu-kiem-g0.json` (chấm lại bằng `tools/tu-kiem/tu_kiem.py`, cách chạy trong README).

## Tóm tắt

- Xong mọi phần làm được mà không cần tài khoản alwaysdata: repo, CI, 91 bảng thành migration, chạy trên Windows bằng `setup\*.bat`, PRODUCT.md và DESIGN.md bản 1, 4 màn hình app học chạy được với dữ liệu mẫu.
- Tự kiểm 16 mục: **10 đạt, 2 đạt một phần, 4 chưa chạy, 0 hỏng**. Bốn mục chưa chạy (7–10) và phần còn lại của mục 13, 16 đều chờ bật deploy lên alwaysdata.
- Lần chạy CI cuối trên code này: php 15/15 bước (32 test Pest), web 7/7 bước (103 test Vitest, 60 test Playwright), nightly đạt trên MariaDB 10.11, 11.4, 11.8 và MySQL 8.4, 9.7, workflow windows đạt.

## 16 mục tự kiểm

| # | Mục | Kết quả | Bằng chứng, ghi chú |
| --- | --- | --- | --- |
| 1 | migration lên, xuống | đạt | lên, xuống hết (chỉ còn bảng `migrations`), lên lại trên MariaDB 10.11, 11.4, 11.8, MySQL 8.4, 9.7 |
| 2 | khớp với schema.sql | đạt | 0 khác biệt; test sửa cố ý kiểu một cột thì công cụ chỉ đúng cột đó |
| 3 | quy ước LoWo | đạt | không khoá ngoại, thời gian số nguyên; test thêm cố ý khoá ngoại thì bị bắt |
| 4 | seeder chạy lại | đạt | hai lần cùng số dòng: 5 gói, 40 quyền lợi, 10 prompt |
| 5 | kiểm thử database cũ | đạt | `test_schema.py`: 16/16 kiểm tra cấu trúc, 22/22 tình huống, 91 bảng |
| 6 | CI chặn lỗi | đạt | lần đẩy test cố ý hỏng (nhánh `work/ci-red`): job php đỏ, deploy không chạy |
| 7 | deploy thật | chưa chạy | chờ bật deploy |
| 8 | migration lỗi khi deploy | chưa chạy | chờ bật deploy, rồi đẩy một migration cố ý hỏng |
| 9 | bản mới lỗi sau khi đổi | chưa chạy | chờ bật deploy, rồi đẩy bản cố ý làm `/up` lỗi |
| 10 | quay lại bằng tay | chưa chạy | workflow `rollback` đã có; chạy sau lần deploy đầu |
| 11 | bảo mật cơ bản | đạt | trang lỗi 500 chung (không lộ nội dung lỗi, đường dẫn); `/.env`, `/storage/logs` trả 404; `/admin` chưa đăng nhập về trang đăng nhập; sai mật khẩu 5 lần thì bị chặn, kể cả lần sau đúng |
| 12 | chữ có dấu qua database | đạt | ghi rồi đọc "Brötchen", "Straße", "tiếng Việt" giữ nguyên; kết nối sai bảng mã thì test bắt được |
| 13 | lịch và hàng đợi | đạt một phần | CI chạy lịch và hàng đợi thật (bảng `jobs`) qua 10 phút mô phỏng: đủ 10 nhịp; job thử chạy xong; job cố ý lỗi vào `failed_jobs` sau đúng 3 lần thử; thiếu nhịp thì lệnh kiểm báo lỗi. Còn chạy thật 10 phút trên alwaysdata |
| 14 | font và chữ | đạt | trang kiểm chữ đúng font ở máy tính và điện thoại; mất cố ý file font thì test báo, chữ vẫn đọc được |
| 15 | tương phản màu | đạt | 70/70 cặp màu đạt WCAG AA ở giao diện sáng và tối; 4 màn hình app học đạt axe WCAG 2.2 AA |
| 16 | dung lượng và phút CI | đạt một phần | ước 782 phút CI mỗi tháng (dưới 1.500). Còn đo dung lượng alwaysdata sau 5 lần deploy |

Mục 16 lúc đầu vượt mức: workflow windows chạy mỗi lần đẩy code thì ước khoảng 1.550 phút mỗi tháng (máy Windows tính gấp đôi). Nay workflow này chỉ chạy khi đổi `setup\`, gói thư viện, migration, seeder và mỗi tuần một lần.

## Đã giao

- Repo `DatdatItSn02/lernbuch`, nhánh `main` có CI xanh; README ghi cách chạy, deploy, quay lại.
- PRODUCT.md bản 1, DESIGN.md gộp theo code (kèm `.impeccable/design.json`), `design/tokens.css` bản 0.2.
- 4 màn hình app học chạy được với dữ liệu mẫu: Hôm nay, Học, Thẻ từ, Trang chủ đề; thêm Cài đặt, danh sách chủ đề, trang 404. Mở ở `/app/hom-nay` sau khi chạy `setup\chay-web.bat`.
- Chạy trên Windows: `docs/huong-dan-windows.md` (bộ `setup\*.bat` giống LoWo thay cho Herd như kế hoạch ban đầu, theo yêu cầu của bạn).
- Ảnh trang chào và trang đăng nhập quản trị ở cỡ máy tính và điện thoại: `docs/anh/`. Ảnh 4 màn hình app học có trong thư mục dự án trên máy.
- File zip mã nguồn `lernbuch-g0.zip` và bản sao tài liệu trong `F:\CongViec\lernbuch`.

## Chưa làm, cần bạn

1. **Bật deploy lên alwaysdata** theo `docs/huong-dan-alwaysdata.md` (khoảng 15 phút): PHP 8.4, site, database `lernbuch_app`, khoá SSH, hai Secrets `ALWAYSDATA_SSH_KEY` và `DB_PASSWORD`, rồi biến `DEPLOY_ENABLED=true`. Xong thì báo mình chạy mục 7–10, phần còn lại của mục 13 và 16.
2. **Sau lần deploy đầu**: tác vụ định kỳ `schedule:run`, dịch vụ `queue:work`, tạo tài khoản quản trị (mục 4 của hướng dẫn alwaysdata).
3. **Trước G1**: đánh dấu nguồn nội dung nào bạn có quyền dùng (8 nguồn đang ghi `unknown`); chốt tên thương mại, giá Premium và giới hạn gói Free, cổng thanh toán làm đầu tiên (đang ghi "Chưa chốt" trong PRODUCT.md).

## Cổng nghiệm thu G0

| Việc bạn thử | Trạng thái |
| --- | --- |
| Mở trang alwaysdata, xem trang chào trên máy tính và điện thoại | chờ deploy; trên máy bạn đã chạy được |
| Tạo tài khoản quản trị bằng `php artisan app:create-admin`, đăng nhập `/admin` | trên máy: `setup\tao-admin.bat`; trên alwaysdata chờ deploy |
| Tab Actions: ci và nightly đều xanh | đã xanh |
| Chạy workflow rollback | chờ deploy |
| Đọc báo cáo, PRODUCT.md, DESIGN.md, 4 màn hình | sẵn sàng |
