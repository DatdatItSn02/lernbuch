# Báo cáo G0: Nền móng (Lernbuch Online)

Ngày 26/11/2026, cập nhật 27/11/2026 sau khi deploy và thử hỏng cố ý trên alwaysdata. Kết quả chi tiết từng mục: `docs/tu-kiem-g0.json` (chấm lại bằng `tools/tu-kiem/tu_kiem.py`, cách chạy trong README). Công cụ đọc log CI và log máy chủ của từng lần deploy, quay lại trong nhánh `ci-results`; phần đo tay trên máy chủ ghi ở `docs/tu-kiem-g0-tay.json`.

## Tóm tắt

- Xong: repo, CI, 91 bảng thành migration, chạy trên Windows bằng `setup\*.bat`, PRODUCT.md, DESIGN.md, 4 màn hình app học chạy được với dữ liệu mẫu, deploy tự động lên https://lernbuch.alwaysdata.net (tự dừng khi migration hỏng, tự quay lại khi bản mới lỗi).
- Tự kiểm 16 mục: **14 đạt, 1 đạt một phần, 1 chưa chạy, 0 hỏng**. Còn hai việc cần bạn: đăng nhập `/admin` trên alwaysdata (mục 7) và chạy workflow rollback (mục 10).
- Lần chạy CI cuối (commit `e33c96d`): php 16/16 bước (37 test Pest), web 8/8 bước (101 test Vitest, 60 test Playwright), deploy đạt. Nightly đạt trên MariaDB 10.11, 11.4, 11.8 và MySQL 8.4, 9.7; workflow windows đạt.

## 16 mục tự kiểm

| # | Mục | Kết quả | Bằng chứng, ghi chú |
| --- | --- | --- | --- |
| 1 | migration lên, xuống | đạt | lên, xuống hết (chỉ còn bảng `migrations`), lên lại trên MariaDB 10.11, 11.4, 11.8, MySQL 8.4, 9.7 |
| 2 | khớp với schema.sql | đạt | 0 khác biệt; test sửa cố ý kiểu một cột thì công cụ chỉ đúng cột đó |
| 3 | quy ước LoWo | đạt | không khoá ngoại, thời gian số nguyên; test thêm cố ý khoá ngoại thì bị bắt |
| 4 | seeder chạy lại | đạt | hai lần cùng số dòng: 5 gói, 40 quyền lợi, 10 prompt |
| 5 | kiểm thử database cũ | đạt | `test_schema.py`: 16/16 kiểm tra cấu trúc, 22/22 tình huống, 91 bảng |
| 6 | CI chặn lỗi | đạt | lần đẩy test cố ý hỏng (nhánh `work/ci-red`): job php đỏ, deploy không chạy; trên `main` cũng đã gặp thật (run 45714047969, Pint đỏ): deploy không chạy |
| 7 | deploy thật | đạt một phần | 10 lần deploy đạt; lần cuối (run 45873504248) trang chào hiện đúng bản mới, `/up` trả 200. Còn đăng nhập `/admin` trên alwaysdata |
| 8 | migration lỗi khi deploy | đạt | run 45711622826: migration cố ý hỏng, deploy dừng ở bước Migration, giữ bản `8d88488`; lần deploy sau thấy vẫn chạy `8d88488`, `/up` 200, bản dở dang đã bị xoá |
| 9 | bản mới lỗi sau khi đổi | đạt | run 45714460059: bản `9602d2f` cố ý làm `/up` lỗi (app trả 500 ngay trên máy chủ), workflow tự quay về `ca55655`, sau đó web chạy `ca55655`, `/up` 200. Lúc thử, web còn chạy bản cũ do site chưa có 2 dòng php.ini, nên lỗi được bắt bằng `/up` gọi ngay trên máy chủ |
| 10 | quay lại bằng tay | chưa chạy | workflow `rollback` sẵn sàng, nay chờ trang chủ báo đúng bản vừa quay về rồi mới kiểm `/up`; cần bạn bấm chạy |
| 11 | bảo mật cơ bản | đạt | trang lỗi 500 chung (không lộ nội dung lỗi, đường dẫn); `/.env`, `/storage/logs` trả 404; `/admin` chưa đăng nhập về trang đăng nhập; sai mật khẩu 5 lần thì bị chặn, kể cả lần sau đúng |
| 12 | chữ có dấu qua database | đạt | ghi rồi đọc "Brötchen", "Straße", "tiếng Việt" giữ nguyên; kết nối sai bảng mã thì test bắt được |
| 13 | lịch và hàng đợi | đạt | CI: 10 phút mô phỏng đủ 10 nhịp, job thử chạy xong, job cố ý lỗi vào `failed_jobs` sau đúng 3 lần thử, thiếu nhịp thì lệnh kiểm báo lỗi. Trên alwaysdata: 9/10 nhịp trước lần deploy cuối (mỗi lần deploy giờ đều ghi số nhịp); `app:thu-hang-doi` bạn chạy qua SSH: job thử xong, job lỗi vào `failed_jobs` sau 3 lần thử |
| 14 | font và chữ | đạt | trang kiểm chữ đúng font ở máy tính và điện thoại; mất cố ý file font thì test báo, chữ vẫn đọc được |
| 15 | tương phản màu | đạt | 94/94 cặp màu đạt WCAG AA ở giao diện sáng và tối (bảng màu mới); 4 màn hình app học đạt axe WCAG 2.2 AA |
| 16 | dung lượng và phút CI | đạt | sau 10 lần deploy: cả thư mục nhà 339 MB (thư mục app 337 MB, gồm 3 bản phát hành), database 6,2 MB, tổng 345 MB = 34% của 1 GB. Ước 1.006 phút CI mỗi tháng (dưới 1.500) |

Mục 16 lúc đầu vượt mức: workflow windows chạy mỗi lần đẩy code thì ước khoảng 1.550 phút mỗi tháng (máy Windows tính gấp đôi). Nay workflow này chỉ chạy khi đổi `setup\`, gói thư viện, migration, seeder và mỗi tuần một lần. Về đĩa: mỗi bản phát hành khoảng 107 MB. Lúc deploy, trước bước dọn có thể có 5 bản cùng lúc (3 bản gần nhất, bản tốt gần nhất, bản mới), khoảng 550 MB thư mục app, vẫn dưới 1 GB. Audio từ G1 không để trên alwaysdata nếu chọn kho kiểu S3.

Trong lúc thử mục 8, 9 tìm ra và đã sửa:

- Sau khi đổi `current`, web trên alwaysdata còn chạy bản cũ hơn 4 phút (PHP nhớ đường dẫn thật của `current`), nên `/up` 200 của bản cũ làm tưởng bản mới chạy tốt. Workflow nay chờ trang chủ báo đúng tên bản mới rồi mới kiểm `/up`; site thêm 2 dòng php.ini (`docs/huong-dan-alwaysdata.md`) thì web đổi bản ngay.
- Quay lại có thể chọn nhầm bản hỏng. Bản qua kiểm `/up` nay được đánh dấu; quay lại và dọn bản cũ đều ưu tiên giữ bản tốt gần nhất; deploy lỗi giữa chừng thì xoá bản dở dang.
- Job cố ý lỗi đếm sai số lần thử khi cache là database (như trên alwaysdata): đã sửa và thêm test.

## Đã giao

- Repo `DatdatItSn02/lernbuch`, nhánh `main` có CI xanh; README ghi cách chạy, deploy, quay lại.
- PRODUCT.md bản 1. Giao diện làm lại sau G0 (26/11) theo hướng "Vở ô li và hộp thẻ": DESIGN.md mới (bản đầy đủ ở `.impeccable/design-full.md`), `design/tokens.css` bản 0.3, font Be Vietnam Pro.
- Deploy tự động lên alwaysdata: mỗi lần đẩy vào `main` mà CI xanh thì đóng gói, migrate trên bản mới, đổi bản, kiểm trang chủ và `/up`, lỗi thì tự quay lại. Log từng lần (bản đang chạy, `/up`, số nhịp, dung lượng) ở nhánh `ci-results`.
- 4 màn hình app học chạy được với dữ liệu mẫu: Hôm nay, Học, Thẻ từ, Trang chủ đề; thêm Cài đặt, danh sách chủ đề, trang 404. Mở ở `/app/hom-nay` sau khi chạy `setup\chay-web.bat`.
- Chạy trên Windows: `docs/huong-dan-windows.md` (bộ `setup\*.bat` giống LoWo thay cho Herd như kế hoạch ban đầu, theo yêu cầu của bạn).
- Ảnh trang chào và trang đăng nhập quản trị ở cỡ máy tính và điện thoại (giao diện mới): `docs/anh/`. Ảnh 4 màn hình app học có trong thư mục dự án trên máy.
- File zip mã nguồn `lernbuch-g0.zip` và bản sao tài liệu trong `F:\CongViec\lernbuch`.

## Chưa làm, cần bạn

1. **Mục 7, đăng nhập `/admin`.** SSH vào alwaysdata, chạy `cd ~/lernbuch/current && php artisan app:create-admin`, nhập email và mật khẩu (ít nhất 12 ký tự), rồi đăng nhập ở https://lernbuch.alwaysdata.net/admin. Báo mình được hay không để ghi vào `docs/tu-kiem-g0-tay.json`.
2. **Mục 10, quay lại bằng tay.** GitHub → Actions → rollback → Run workflow. Đạt khi log ghi `✔ Web chạy bản trước (…), /up trả 200`. Xong thì Actions → ci → Run workflow trên `main` để về bản mới nhất. Mình đọc log này từ `ci-results`, không cần bạn chép lại.
3. **Trước G1**: trả lời 6 câu hỏi cuối kế hoạch G1 và chọn quyền dùng cho từng nguồn nội dung ngay trong doc kế hoạch; chốt tên thương mại, giá Premium và giới hạn gói Free, cổng thanh toán làm đầu tiên (đang ghi "Chưa chốt" trong PRODUCT.md).

## Cổng nghiệm thu G0

| Việc bạn thử | Trạng thái |
| --- | --- |
| Mở trang alwaysdata, xem trang chào trên máy tính và điện thoại | sẵn sàng: https://lernbuch.alwaysdata.net |
| Tạo tài khoản quản trị bằng `php artisan app:create-admin`, đăng nhập `/admin` | trên máy: `setup\tao-admin.bat`; trên alwaysdata chờ bạn (mục 7) |
| Tab Actions: ci và nightly đều xanh | đã xanh |
| Chạy workflow rollback | chờ bạn (mục 10) |
| Đọc báo cáo, PRODUCT.md, DESIGN.md, 4 màn hình | sẵn sàng |
