# Nhập nội dung Lernbuch cũ

Cập nhật: 27/11/2026 · G1 tuần 2.

Lệnh `php artisan app:nhap-lernbuch <nguồn>` đọc dữ liệu của Lernbuch cũ và nhập thẻ, cây chủ đề 3 tầng, bài ngữ pháp, bài tập, động từ. Nguồn là `lernweb.zip`, thư mục `lernweb` (hoặc `content`), hoặc thẳng file `.db`.

- **Chạy thử trước:** đọc, kiểm quy tắc dữ liệu (Từ vựng chỉ có từ đơn, mỗi thẻ đúng 1 chủ đề nhỏ, không có chủ đề chung), in báo cáo, chưa ghi gì. Thêm `--ghi` mới ghi, và chỉ ghi khi không có lỗi nào.
- **Chỉ nhập nguồn có quyền dùng để bán** (Quản trị → Nguồn nội dung). Phần của nguồn khác chỉ được đếm; nếu lần trước đã nhập thì bị xoá.
- **Chạy lại không nhân đôi:** giữ khoá của Lernbuch (khoá thẻ, mã chủ đề, sig câu bài tập), giữ id nên giữ tiến độ học. Thẻ không còn trong nguồn thì ẩn đi.
- Mã thoát: 0 đạt; 1 có lỗi dữ liệu (không ghi gì, báo đúng thẻ hoặc câu bài tập); 2 không đọc được nguồn; 3 database từ chối giữa lúc ghi (in gọn lỗi và tên bảng; phần đã ghi vẫn giữ, sửa xong chạy lại là đủ).

## Trên máy bạn

1. `lernweb.zip` để ở `F:\CongViec\lernbuch\nguon-cu\` (đã có).
2. Bấm đúp `app\setup\nhap-du-lieu.bat`. Script tìm `nguon-cu\lernweb.zip` trong thư mục `app` hoặc ngay cạnh nó, chạy thử, in báo cáo, rồi hỏi *Ghi vào database?*: gõ `c` rồi Enter để ghi.
3. Kết quả ở `app\setup\ket-qua-nhap-du-lieu.txt`. Muốn đọc file khác thì kéo file hoặc thư mục vào `nhap-du-lieu.bat`.

## Trên alwaysdata

Làm hai bước ở hai nơi khác nhau. Khoá SSH là khoá đã tạo ở mục 2 của `huong-dan-alwaysdata.md`.

**1. Tải file lên: chạy trong PowerShell trên Windows**, không phải trong cửa sổ máy chủ (cửa sổ có dấu nhắc kiểu `lernbuch@ssh2:~$`): ở đó không có ổ `F:`. File khoảng 10 MB; giữa `lernweb.zip` và `lernbuch@` có một dấu cách.

```powershell
scp -i "$env:USERPROFILE\.ssh\lernbuch_deploy" F:\CongViec\lernbuch\nguon-cu\lernweb.zip lernbuch@ssh-lernbuch.alwaysdata.net:lernbuch/shared/lernweb.zip
```

**2. Nhập: chạy trên máy chủ.** Dùng cửa sổ máy chủ đang mở, hoặc vào máy chủ từ PowerShell bằng `ssh -i "$env:USERPROFILE\.ssh\lernbuch_deploy" lernbuch@ssh-lernbuch.alwaysdata.net`, rồi gõ từng dòng:

```bash
cd ~/lernbuch/current
php artisan app:nhap-lernbuch ~/lernbuch/shared/lernweb.zip
php artisan app:nhap-lernbuch ~/lernbuch/shared/lernweb.zip --ghi
rm ~/lernbuch/shared/lernweb.zip
```

Dòng 2 là chạy thử: đọc báo cáo, đúng như bảng số liệu dưới đây và không có lỗi thì mới chạy dòng 3 để ghi. Dòng 4 xoá file đã tải lên cho nhẹ dung lượng (1 GB dùng chung). Gõ `exit` để rời máy chủ.

Lệnh đọc và ghi theo lô 500 thẻ nên vừa bộ nhớ của gói Free.

## Số liệu mong đợi (lernweb.zip bản x19, chép ngày 27/11)

| Phần | Số |
| --- | --- |
| Thẻ | 11.882: Từ vựng 8.691, Cụm câu 1.544, Giao tiếp 951, Động từ tách 207, Động từ + giới từ 187, Tên riêng 158, Mẫu câu 144 |
| Thẻ theo nguồn | Lernbuch xlsx 7.769, Collins 3000 1.879, Vocab Trainer 1.087, English for Everyday Activities 588, Cụm từ mẫu câu 351, German Slang 205, tự soạn 3 |
| Chủ đề | 7 nhóm, 27 chủ đề vừa, 620 chủ đề nhỏ (192 chủ đề gốc, mỗi loại thẻ một chủ đề nhỏ riêng) |
| Ngữ pháp | 219 bài, 33 chương |
| Bài tập | 364 bộ, 6.887 câu |
| Động từ | 889 |
| Bỏ qua | 700 thẻ đã tắt trong Lernbuch cũ; 1 thẻ có mặt trước rỗng (báo ở phần cảnh báo) |
| Ghi lần đầu (database trống) | mới: 654 chủ đề (7 + 27 + 620), 11.882 thẻ, 33 chương, 219 bài, 364 bộ, 6.887 câu, 889 động từ; kèm 35.646 nghĩa (thẻ nào cũng đủ vi, en, zh), 13.222 câu ví dụ, 16.733 dạng từ để tra |
| Chạy lại | mới 0, mọi dòng là cập nhật; nghĩa, câu ví dụ, dạng từ vẫn ra đúng 3 số trên (ghi lại cho thẻ đã nhập) |

Số này khác bản tóm tắt x14 ngày 25/11 (12.227 thẻ, 2.466 chủ đề nhỏ): bản x19 tắt thêm thẻ trùng và gom chủ đề nhỏ thành 192 chủ đề gốc.

Ngày 27/11 máy của bạn (MySQL 9.7) đã nhập đủ, tổng khớp đúng các số trên; 3 số nghĩa, câu ví dụ, dạng từ cũng khớp khi đếm lại độc lập từ `seed.db`.

## Thử trên máy dev khi chưa có dữ liệu

`php artisan db:seed --class=NoiDungThuSeeder` thêm nội dung mẫu nhỏ (4 chủ đề nhỏ, khoảng 30 thẻ) mà test trình duyệt dùng. Lệnh bỏ qua khi database đã có chủ đề, và không chạy trên máy chủ thật.
