# Product

<!-- impeccable:product-schema 1 -->
<!-- BẢN NHÁP 2026-11-25: viết từ tài liệu "lernbuch-tom-tat-du-an.md" và yêu cầu của chủ dự án. Chạy /impeccable init để xác nhận trước khi thiết kế. Dòng ghi "Chưa chốt" hoặc "Đề xuất" là quyết định còn mở. -->

## Platform

web

## Stack

Chủ dự án chọn PHP + MySQL. Đề xuất, chờ duyệt: Laravel 13 trên PHP 8.4, MariaDB 11.4 ở alwaysdata (vẫn chạy được trên MySQL 8.4), cache và hàng đợi bằng database, Redis khi lên máy chủ lớn; app học React + TypeScript (Vite) gọi API `/api/v1`; trang công khai bằng Blade; trang quản trị Filament 5. AI dùng khoá chung của hệ thống (Gemini; chấm phát âm bằng Azure), chỉ mở cho người từ 18 tuổi. Audio miễn phí bằng Piper và giọng đọc của trình duyệt. Chạy dạng PWA trước, app di động sau.

## Users

Người Việt học tiếng Đức, phần lớn ở trình độ A1–B1, mục tiêu sang Đức (du học, học nghề, đi làm) hoặc thi chứng chỉ. Giáo trình hay dùng: Passwort Deutsch (走遍德国). Học chủ yếu trên điện thoại, nhiều lượt ngắn trong ngày; một phần học trên máy tính bằng bàn phím. Giai đoạn sau: giáo viên và trung tâm tiếng Đức quản lý lớp, giao bài.

## Product Purpose

Giúp người học nhớ lâu từ vựng và ngữ pháp tiếng Đức bằng lặp lại ngắt quãng theo từng chủ đề, có giải thích bằng tiếng Việt và AI chấm câu. Thành công là người học quay lại mỗi ngày, mức thuộc tăng đều, đạt trình độ mục tiêu, và người trả phí gia hạn.

## Positioning

Kho nội dung tiếng Đức giải thích bằng tiếng Việt: 12.227 thẻ trong 2.466 chủ đề nhỏ, 218 bài ngữ pháp A1–B2, 6.887 câu bài tập, 889 động từ có bảng chia; mỗi nghĩa có tiếng Việt, Anh, Trung. Học theo từng chủ đề nhỏ, không trộn. AI chấm câu và giải thích lỗi người Việt hay mắc.

## Operating Context

- Học thẻ có 7 chế độ: Flashcard, Quiz, Listening, Typing, Ghép cặp, Hỗn hợp, Đặt câu. Phím tắt: Space, 1–4, P, S, E, ←, →, Esc.
- Lặp lại ngắt quãng 6 mức (Mới, Đang học, Quen, Khá, Thuộc, Thành thạo), khoảng ôn 1, 3, 7, 14, 30 ngày.
- Xu thưởng theo chế độ, chuỗi ngày học, biểu đồ 14 ngày.
- Ngữ pháp, bài tập 4 dạng, chia động từ, tra từ gõ 4 thứ tiếng không cần dấu.

## Capabilities and Constraints

- Giữ toàn bộ chức năng Lernbuch; thêm tài khoản, gói Free/Premium, thanh toán trong nước (VietQR, MoMo, ZaloPay) và PayPal, trang quản trị, soạn và duyệt nội dung, phân quyền.
- Mục Từ vựng chỉ có từ đơn; mỗi thẻ thuộc đúng 1 chủ đề nhỏ; không có chủ đề chung; hình chỉ gán khi đúng nghĩa.
- Học từng chủ đề một, học mới và ôn cuốn chiếu; không giới hạn số từ mới mỗi ngày; không có cột Thuộc/Không thuộc.
- Chữ trên giao diện và trong học liệu ngắn gọn, tiếng Việt; bản dịch Anh/Trung/Việt tự nhiên và ẩn được.
- Chưa chốt: tên thương hiệu, giá và phần miễn phí, cổng thanh toán đầu tiên, hình thức kinh doanh, nguồn nội dung được giữ khi bán.

## Brand Commitments

- Tên tạm "Lernbuch"; tên thương mại chưa chốt.
- Thế giới thị giác hiện có: khu vườn vẽ tay đổi theo mùa (xuân hoa anh đào và thỏ, thu cây cam và nhím…), khung nội dung như tờ giấy nằm giữa màn hình, ô chủ đề pastel có băng dính, sáng/tối riêng cho từng mùa.
- Font đang dùng, tự lưu trữ: Baloo 2 (tiêu đề), Nunito (chữ), Patrick Hand (chữ viết tay).
- Hình là SVG (OpenMoji và hình tự vẽ), không dùng emoji của hệ điều hành.

## Evidence on Hand

- Dữ liệu Lernbuch bản x14 (25/11/2026), số liệu như mục Positioning; 2.056/8.688 từ vựng có hình riêng.
- Chưa có người dùng trả phí, nhận xét khách hàng hay số liệu tăng trưởng. Không bịa lời khen, số người dùng, giải thưởng.
- Bản quyền nội dung chưa kiểm; nhiều nguồn là sách thương mại.

## Product Principles

Đề xuất, chờ xác nhận:

1. Nội dung phải chính xác: sai nghĩa, sai hình tệ hơn thiếu.
2. Một chủ đề một lúc: học sâu, không trộn.
3. Ít chữ, nhiều hành động: mỗi màn hình học chỉ một việc.
4. Ôn đều quan trọng hơn học nhiều.
5. Người học trả phí vì giá trị nhận được; không chặn giữa phiên học để mời nâng cấp.

## Accessibility & Inclusion

Giữ các tuỳ chọn của Lernbuch: cỡ chữ Nhỏ/Vừa/Lớn, sáng/tối, phím tắt. Đề xuất: đạt WCAG 2.2 AA; chữ tiếng Đức đánh dấu `lang="de"` để trình đọc màn hình đọc đúng và trình duyệt ngắt từ ghép dài.
