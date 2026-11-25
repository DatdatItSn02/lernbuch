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

## Xem các trang

| Địa chỉ | Trang |
| --- | --- |
| http://127.0.0.1:8001 | trang chào |
| http://127.0.0.1:8001/kiem-chu | trang kiểm chữ: 20 mẫu chữ tiếng Việt, tiếng Đức theo từng font |
| http://127.0.0.1:8001/app/hom-nay | app học, màn hình Hôm nay (dữ liệu mẫu) |
| http://127.0.0.1:8001/app/hoc | phiên học: Space lật thẻ, phím 1–4 chấm, P nghe, ←/→ xem lại, Esc thoát |
| http://127.0.0.1:8001/app/tu/broetchen | thẻ từ |
| http://127.0.0.1:8001/app/chu-de/tiem-banh | trang chủ đề: che cột, lọc theo mức, phím / để tìm |
| http://127.0.0.1:8001/app/cai-dat | cài đặt: giao diện tối, cỡ chữ, mùa |
| http://127.0.0.1:8001/admin | trang quản trị (đăng nhập bằng tài khoản vừa tạo) |

App học G0 chạy bằng dữ liệu mẫu: tiến độ và ghi chú mất khi tải lại trang, cài đặt thì được nhớ.

## Thử lịch và hàng đợi (mục 13 của phần tự kiểm)

1. Mở `setup\moi-truong.bat` ba lần để có ba cửa sổ lệnh, đều ở thư mục `app`.
2. Cửa sổ 1: `php artisan schedule:work` (chạy lịch mỗi phút). Cửa sổ 2: `php artisan queue:work` (chạy job trong hàng đợi).
3. Sau 10 phút, cửa sổ 3: `php artisan app:kiem-nhip`. Đạt khi báo ít nhất 9/10 nhịp.
4. Cũng ở cửa sổ 3: `php artisan app:thu-hang-doi`, chờ vài giây rồi chạy lệnh `--kiem=<mã>` mà nó in ra. Đạt khi job thử chạy xong và job cố ý lỗi vào `failed_jobs` sau 3 lần thử.
5. Đóng cửa sổ 1 và 2 (Ctrl+C) khi xong.

## Khi có lỗi

- Bước nào hỏng, file `setup\ket-qua-*.txt` của bước đó ghi lỗi và cách sửa; gửi file đó cho Claude.
- Web không mở: kiểm MySQL đã bật (`setup\mysql-bat.bat`), rồi chạy lại `setup\chay-web.bat`.
- Cổng 8001 đang bận: tắt cửa sổ `chay-web` cũ rồi mở lại.
