# Báo cáo G1: Bản học được (Lernbuch Online)

Ngày 28/11/2026. Kết quả chi tiết từng mục: `docs/tu-kiem-g1.json`, chấm bằng `tools/tu-kiem/tu_kiem_g1.py` (cách chạy trong README). Công cụ đọc nhánh `ci-results`: bảng kết quả từng test của CI (Pest, Vitest, Playwright, đủ tên), log CI, log máy chủ của các lần deploy và workflow audio. Phần thử tay ghi ở `docs/tu-kiem-g1-tay.json`, mỗi dòng ghi nguồn để truy lại.

## Tóm tắt

- Xong cả 5 tuần: tài khoản người học (đăng ký, xác minh email, quên mật khẩu, khoá tạm), nội dung nhập từ Lernbuch cũ, 7 chế độ học với lặp lại ngắt quãng tính trên máy chủ, tiến độ chung giữa nhiều máy, học tiếp khi mất mạng, audio Piper cho từ, cài được như app (PWA), trang quản trị (người học, nội dung, nguồn, nhật ký thao tác, job lỗi).
- Tự kiểm 19 mục: **19 đạt**. Chấm lại sau khi deploy tuần 5 (run 46070721339): máy chủ có đúng 11.882 thẻ, 620 chủ đề nhỏ, 219 bài ngữ pháp, 6.887 câu, 889 động từ, 10.875 file audio; dung lượng 480 MB = 47% của 1 GB.
- Lần chạy CI chấm tự kiểm (commit `71dcf83`, run 46067145126, bản code cuối; commit sau chỉ thêm tài liệu và sửa công cụ tự kiểm): php 19/19 bước (317 test Pest), web 9/9 bước (347 test Vitest, 208 test Playwright ở cỡ máy tính và điện thoại). Workflow windows đạt (commit `93d273e`, run 46066389166).
- Cả 5 tuần đã lên `main` và deploy (28/11, bản `96abc55`, `/up` trả 200); audio đã tạo xong (10.875 file, 68 MB).

## 19 mục tự kiểm

| # | Mục | Kết quả | Bằng chứng, ghi chú |
| --- | --- | --- | --- |
| 1 | nhập đủ và đúng | đạt | 4 test Pest (chạy thử, ghi, chạy lại không thêm dòng và giữ id, nguồn "Không" không có dòng nào). Máy bạn ngày 27/11 (MySQL 9.7) và máy chủ alwaysdata sau deploy 28/11 đều đúng số mong đợi: 11.882 thẻ, 620 chủ đề nhỏ, 219 bài ngữ pháp, 6.887 câu, 889 động từ |
| 2 | quy tắc dữ liệu | đạt | cụm từ nằm trong Từ vựng, thẻ không có chủ đề, sig bài tập trùng hay dài quá: báo đúng dòng, không ghi gì |
| 3 | lặp lại ngắt quãng | đạt | bảng ca thử chung chạy ở cả hai phía: 41 ca Pest, 51 test Vitest; đủ 6 mức × 4 cách chấm, ngày đổi giờ mùa hè (23 và 25 giờ), đổi múi giờ |
| 4 | lượt chấm gửi trùng | đạt | một lô gửi hai lần, trùng ngay trong lô: ghi một lần, xu cộng một lần |
| 5 | mất mạng giữa phiên | đạt | Playwright, cả hai cỡ: mất mạng sau thẻ 3, chấm tiếp 5 thẻ, tải lại trang, có mạng lại: máy chủ nhận đủ 8 lượt, mỗi lượt một lần |
| 6 | hai máy chấm cùng thẻ | đạt | lô tới lệch thứ tự: kết quả theo giờ chấm, như nhau dù lô nào tới trước |
| 7 | chấm quá nhanh | đạt | 20 thẻ trong 2 giây: tiến độ vẫn ghi, xu không cộng, nhật ký ghi lý do |
| 8 | dữ liệu người khác | đạt | phiên, thẻ riêng, sổ tay, lịch sử của người khác: 404, không ghi, không lộ |
| 9 | tài khoản | đạt | email trùng báo ngay ô email; link xác minh hết hạn hay sai email không dùng được; link đặt lại mật khẩu chỉ dùng một lần; chưa xác minh thì về trang gửi lại thư; sai mật khẩu lần thứ 6 bị chặn; quá 5 lần đăng ký mỗi giờ từ một IP bị chặn |
| 10 | quản trị | đạt | người học và nhân viên bị khoá vào `/admin` bị từ chối; mọi lần sửa nội dung ghi nhật ký trước, sau; 16 test Pest trang quản trị; Playwright mở 14 trang danh sách và bản ghi đầu của từng trang, sáng và tối, hai cỡ: không lỗi, không tràn ngang, đạt WCAG 2.2 AA |
| 11 | audio dự phòng | đạt | thiếu file, file 404, không có giọng Đức: chuyển sang giọng trình duyệt; không có giọng nào thì báo nhẹ (14 test Vitest) |
| 12 | giọng có giấy phép | đạt | workflow audio chọn pavoque thì dừng: "Giọng pavoque không được dùng để bán" (CC BY-NC-SA 4.0) |
| 13 | 7 chế độ và phím tắt | đạt | Playwright từng chế độ, cả hai cỡ: Space, 1–4, P, S, E, ←, →, Esc, Alt+P; chấm đúng, tổng kết đúng số |
| 14 | trợ năng và giao diện | đạt | 62/62 test Playwright có axe WCAG 2.2 AA đạt ở cả hai cỡ (mọi màn app học và quản trị, sáng và tối); 94/94 cặp màu đạt tương phản; nút màu Filament đạt 4.5:1 cả sáng, tối, khi di chuột; bộ dò Impeccable trên DOM 108 trang, cỡ 1280×800 và 390×844: 0 lỗi (chi tiết dưới bảng) |
| 15 | PWA | đạt | Chromium xác nhận đủ điều kiện cài; tải một chủ đề, cắt mạng thật (cả với service worker), tải lại trang vẫn mở app, học 3 thẻ, có mạng lại thì máy chủ nhận đủ |
| 16 | tra từ | đạt | gõ không dấu ở 4 thứ tiếng ra đúng thẻ; "zahlen" và "zählen" ra đúng chữ đứng đầu |
| 17 | hiệu năng | đạt | 11.882 thẻ, 5.000 dòng tiến độ: tạo phiên p95 cao nhất 93 ms (mức 150), 16 API khác p95 cao nhất 57,5 ms (mức 200) |
| 18 | dung lượng | đạt | sau deploy tuần 5 (đã có audio): thư mục nhà 440 MB (audio 68 MB), database 40,3 MB, tổng 480 MB = 47% của 1 GB (mức 60%) |
| 19 | đa ngôn ngữ | đạt | không còn chữ viết thẳng trong code giao diện; ngôn ngữ thiếu bản dịch hiện tiếng Việt, không hiện mã khoá; mọi khoá tiếng Anh của Filament đều có tiếng Việt |

Migration mới của G1 (6 cái) vẫn qua đủ 6 bước database của G0: lên, khớp `schema.sql`, quy ước LoWo, xuống hết, chỉ còn bảng `migrations`, lên lại.

Bộ dò Impeccable (`tools/tu-kiem/chay_impeccable.py`, tóm tắt ở `docs/impeccable-g1.json`) mở từng trang bằng trình duyệt thật, nên các quy tắc cần trang đã vẽ (tương phản, chữ nhỏ, khung lồng khung) đều chạy. Kết quả 0 lỗi; 2.063 lưu ý (không tính là lỗi) đều là màu, bo góc không có trong bảng token của DESIGN.md: màu giao diện tối của app chưa ghi vào DESIGN.md, màu và bo góc của Filament. Hai quy tắc được bỏ qua có ghi lý do, chỉ ở trang quản trị (`.impeccable/config.json`): ô nhập có viền nằm trong khung form của Filament (không phải thẻ lồng thẻ) và thanh bên đứng yên cạnh nội dung cuộn dài.

## Tuần 5 rà giao diện, tìm ra và đã sửa

- Bảng Người học mở trang người học bằng id nên ra 404 (trang dùng mã ULID). Đường dẫn nay dùng mã ULID, có test.
- Nút màu của trang quản trị ở giao diện tối chỉ đạt khoảng 4:1 (Filament tự sinh bảng màu với độ sáng cố định). Bảng màu mới theo app: nút chính đúng màu mực tím của app; test kiểm mọi cặp nền và chữ của nút.
- Filament 5.12 chưa dịch 127 khoá tiếng Việt, nên vài chỗ hiện mã khoá, cả trong tên đọc được của nút (trình đọc màn hình đọc "filament-panels::layout.navigation.label"). Đã bổ sung bản dịch và test bắt khoá mới khi nâng Filament.
- Nút Lọc của bảng có số bộ lọc nhưng tên đọc được không có số; nhãn quản trị viết hoa mọi chữ ("Nguồn Và Giấy Phép"); chữ gợi ý trong ô nhập và chữ thay ô trống dưới 4.5:1. Đã sửa cả ba.
- Khung lồng khung ở trang chủ đề, bài ngữ pháp, kết quả phiếu: nay là dải kẻ trên dưới như dòng kẻ vở; Mẹo, Nhắc lại, Lưu ý là ô nền lõm không viền. Ghi vào DESIGN.md (bản đầy đủ `.impeccable/design-full.md`), cùng dòng báo mất mạng, ô tải chủ đề, phần cài app, font tiếng Trung và font đơn cách.
- Trang đăng nhập quản trị chặn quá 5 lần mỗi phút, nên test trình duyệt nay đăng nhập quản trị một lần cho mọi test.

## Đã giao

- Nhánh `work/g1-tuan5` CI xanh; README cập nhật cách nhập dữ liệu, tạo audio, cài app và học khi mất mạng, trang quản trị, chấm tự kiểm.
- Web học được: Hôm nay, Học (7 chế độ), Chủ đề, Cấp độ, Giao tiếp, trang chủ đề, thẻ từ, Tra từ, Sổ tay, Ngữ pháp, Bài tập, Động từ, Cài đặt; trang tài khoản; trang quản trị 14 trang.
- Nội dung Lernbuch cũ: lệnh `app:nhap-lernbuch` (chạy thử, báo cáo, rồi mới ghi; chạy lại không nhân đôi), `setup\nhap-du-lieu.bat` trên máy bạn, `docs/nhap-lernbuch-cu.md`.
- Audio: workflow `audio` (nghe thử, tạo hết), chỉ giọng được dùng để bán (`docs/tao-audio.md`); 10.875 file thorsten-medium đã ở alwaysdata.
- Ảnh mọi màn ở cỡ máy tính và điện thoại, sáng và tối (trang tài khoản và trang chào chỉ có giao diện sáng): 274 ảnh trong `F:\CongViec\lernbuch\anh-g1` và ở nhánh `ci-results`, thư mục `anh/`. Tên ảnh: `<màn>-<light|dark>-<may-tinh|dien-thoai>.png`.
- File zip mã nguồn `lernbuch-g1.zip` và bản sao tài liệu, DESIGN.md, README trong `F:\CongViec\lernbuch`.

## Chưa làm, cần bạn

1. **Gửi email thật.** Thêm Secret `MAIL_PASSWORD` (mật khẩu hộp thư `lernbuch@alwaysdata.net`, mục 5 của `docs/huong-dan-alwaysdata.md`). Thiếu nó thì đăng ký trên alwaysdata không gửi được thư xác minh.
2. **Tài khoản quản trị trên alwaysdata.** SSH vào, chạy `cd ~/lernbuch/current && php artisan app:create-admin`, rồi đăng nhập https://lernbuch.alwaysdata.net/admin (cũng là mục 7 còn lại của G0).

## Cổng nghiệm thu G1

| Việc bạn thử | Trạng thái |
| --- | --- |
| Trên điện thoại, đăng ký bằng email thật, bấm link xác minh | chờ `MAIL_PASSWORD` (việc 1) |
| Chọn mục tiêu, trình độ; học một phiên Flashcard 10 từ mới; tổng kết và Hôm nay đổi đúng | sẵn sàng (CI đã chạy cùng việc này) |
| Thử hai chế độ khác, rồi Ngữ pháp, Bài tập, Động từ, Tra từ | sẵn sàng |
| Bật chế độ máy bay giữa phiên, học tiếp 5 thẻ, tắt: số lượt chờ gửi về 0 | sẵn sàng |
| Mở cùng tài khoản trên máy tính: tiến độ giống trên điện thoại | sẵn sàng |
| Cài app ra màn hình chính điện thoại, mở từ biểu tượng | sẵn sàng |
| Vào `/admin`, tìm tài khoản học vừa tạo, xem tiến độ | sau khi tạo tài khoản quản trị (việc 2) |
| Đọc báo cáo G1, ghi nhận xét vào doc kế hoạch | sẵn sàng |
