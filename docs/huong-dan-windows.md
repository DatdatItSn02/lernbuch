# Chạy Lernbuch trên Windows

Cách chạy giống LoWo: công cụ dùng chung trong `F:\dev` (PHP 8.5, Composer, Node.js 26, MySQL 9.7 cổng 3307), không dùng XAMPP. XAMPP (PHP 7.4 của các dự án cũ) vẫn chạy song song như cũ. Mọi thao tác là bấm đúp file `.bat` trong `setup\`; kết quả ghi ra `setup\ket-qua-*.txt`.

## Lần đầu

1. Lấy code vào `F:\CongViec\lernbuch\app`:
   `git clone https://github.com/DatdatItSn02/lernbuch.git F:\CongViec\lernbuch\app`
   (Bản zip của G0 chỉ để lưu trữ; chạy hằng ngày cần bản `git clone` để `cap-nhat.bat` lấy được code mới.)
2. Bấm đúp `setup\cai-dat.bat`. Máy chưa có thì script tải PHP 8.5, Composer, Node.js 26, MySQL 9.7 và Git bản gọn vào `F:\dev`; máy đã cài LoWo thì dùng lại, chỉ tạo thêm database `lernbuch` và `lernbuch_test`. Kết quả: `setup\ket-qua-cai-dat.txt`.
3. Bấm đúp `setup\cap-nhat.bat`: lấy code nhánh `main`, sửa `.env`, cài thư viện, migrate, seed, build giao diện, chạy toàn bộ test. Kết quả: `setup\ket-qua-cap-nhat.txt`.
4. Bấm đúp `setup\tao-admin.bat` để tạo tài khoản quản trị (hỏi email, tên, mật khẩu từ 12 ký tự).
5. Bấm đúp `setup\chay-web.bat`; trình duyệt mở http://127.0.0.1:8001.

## Hằng ngày

| Việc | Cách làm |
| --- | --- |
| Nhận code mới | `setup\cap-nhat.bat` |
| Mở web | `setup\chay-web.bat`, rồi vào http://127.0.0.1:8001 |
| Chạy lại toàn bộ test | `setup\chay-test.bat`, kết quả ở `setup\ket-qua-test.txt` |
| Cửa sổ lệnh có sẵn PHP 8.5, Node.js 26 | `setup\moi-truong.bat` |
| Bật, tắt MySQL | `setup\mysql-bat.bat`, `setup\mysql-tat.bat` (MySQL dùng chung: tắt thì LoWo cũng mất database) |
| Gửi thông tin máy khi có lỗi | `setup\kiem-tra.bat`, rồi gửi `setup\ket-qua-kiem-tra.txt` (file này không chứa nội dung `.env`) |
| Nhập nội dung Lernbuch cũ | `setup\nhap-du-lieu.bat`: đọc `nguon-cu\lernweb.zip`, chạy thử và in báo cáo, hỏi rồi mới ghi; kết quả ở `setup\ket-qua-nhap-du-lieu.txt` (chi tiết: `nhap-lernbuch-cu.md`) |

## Xem các trang

| Địa chỉ | Trang |
| --- | --- |
| http://127.0.0.1:8001 | trang chào |
| http://127.0.0.1:8001/kiem-chu | trang kiểm chữ: 20 mẫu chữ tiếng Việt, tiếng Đức theo từng font |
| http://127.0.0.1:8001/app/hom-nay | app học, màn hình Hôm nay: thẻ đến hạn, từ mới, xu, chuỗi ngày, mục tiêu ngày |
| http://127.0.0.1:8001/app/hoc | phiên học theo chế độ chọn ở Hôm nay (Flashcard, Quiz, Listening, Typing, Ghép cặp, Hỗn hợp, Đặt câu); phím tắt ở mục dưới |
| http://127.0.0.1:8001/app/chu-de | Chủ đề, Cấp độ, Giao tiếp; bấm vào chủ đề để xem thẻ, bấm vào từ để mở thẻ từ |
| http://127.0.0.1:8001/app/ngu-phap | ngữ pháp theo cấp và chương, tìm bài; mỗi bài có mục lục và nút luyện tập bài đó |
| http://127.0.0.1:8001/app/bai-tap | bài tập: luyện nhanh, Điền câu, Chia động từ, Số nhiều, Ôn câu sai; bài theo cấp, tab Ôn tập; chấm ngay từng câu hoặc làm cả bộ rồi chấm |
| http://127.0.0.1:8001/app/dong-tu | bảng động từ: tìm, lọc theo cấp, động từ tách; bấm một dòng để xem bảng chia |
| http://127.0.0.1:8001/app/tra-tu | tra từ: gõ tiếng Đức, Việt, Anh hoặc Trung, không cần dấu |
| http://127.0.0.1:8001/app/so-tay | sổ tay: thẻ có sao và ghi chú riêng |
| http://127.0.0.1:8001/app/cai-dat | cài đặt: giao diện tối, cỡ chữ, mùa, thứ tiếng của nghĩa |
| http://127.0.0.1:8001/admin | trang quản trị (đăng nhập bằng tài khoản vừa tạo) |

Mọi trang đọc dữ liệu thật: cần nhập Lernbuch cũ trước (`setup\nhap-du-lieu.bat`), chưa nhập thì các trang báo chưa có chủ đề. Tiến độ học, sao và ghi chú lưu theo tài khoản trên máy chủ; mất mạng giữa phiên vẫn học tiếp, lượt chấm chờ trên máy rồi tự gửi khi có mạng. Ngưỡng chấm quá nhanh (không cộng xu) sửa ở trang quản trị, mục Hệ thống › Cài đặt học.

Nút nghe phát file audio tạo sẵn bằng Piper (cách tạo: `tao-audio.md`); từ chưa có file thì đọc bằng giọng tiếng Đức của trình duyệt. Máy chưa có giọng tiếng Đức thì app báo ngay dưới nút nghe: thêm giọng Deutsch trong Cài đặt Windows, mục Giọng nói.

## Phím tắt khi học

| Chế độ | Phím |
| --- | --- |
| Flashcard | Space lật thẻ; 1 Quên, 2 Khó, 3 Tốt, 4 Dễ; P nghe, S nghe chậm, E nghe câu ví dụ; ← xem lại thẻ vừa chấm, → về thẻ đang học |
| Quiz | 1–4 chọn đáp án; Enter hoặc Space sang câu sau; P, S nghe từ |
| Listening | từ tự đọc khi câu hiện; đang gõ thì Alt+P nghe lại, Alt+S nghe chậm; Enter kiểm tra, Enter hoặc Space sang câu sau |
| Typing | Enter kiểm tra; trả lời rồi mới nghe được từ (P, S), để khỏi lộ đáp án |
| Ghép cặp | bấm từ rồi bấm nghĩa (hoặc Tab tới ô rồi Enter); P, S nghe từ vừa chọn |
| Hỗn hợp | theo dạng câu đang hỏi (Quiz, Listening, Typing hay Đặt câu) |
| Đặt câu | bấm các chữ theo thứ tự; Backspace bỏ chữ cuối; Enter kiểm tra; P, S nghe câu |
| Bài tập | 1–6 chọn đáp án; Enter kiểm tra rồi sang câu sau; làm cả bộ: Enter sang ô sau, Ctrl+Enter chấm |
| Mọi màn học | Esc thoát |

## Thử lịch và hàng đợi (mục 13 phần tự kiểm G0)

1. Mở `setup\moi-truong.bat` ba lần để có ba cửa sổ lệnh, đều ở thư mục `app`.
2. Cửa sổ 1: `php artisan schedule:work` (chạy lịch mỗi phút). Cửa sổ 2: `php artisan queue:work` (chạy job trong hàng đợi).
3. Sau 10 phút, cửa sổ 3: `php artisan app:kiem-nhip`. Đạt khi báo ít nhất 9/10 nhịp.
4. Cũng ở cửa sổ 3: `php artisan app:thu-hang-doi`, chờ vài giây rồi chạy lệnh `--kiem=<mã>` mà nó in ra. Đạt khi job thử chạy xong và job cố ý lỗi vào `failed_jobs` sau 3 lần thử.
5. Đóng cửa sổ 1 và 2 (Ctrl+C) khi xong.

## Khi có lỗi

- Bước nào hỏng, file `setup\ket-qua-*.txt` của bước đó ghi lỗi và cách sửa; gửi file đó cho Claude.
- Web không mở: kiểm MySQL đã bật (`setup\mysql-bat.bat`), rồi chạy lại `setup\chay-web.bat`.
- Cổng 8001 đang bận: tắt cửa sổ `chay-web` cũ rồi mở lại.
