# Tạo audio đọc từ tiếng Đức (Piper)

App đọc từ bằng file MP3 tạo sẵn bằng Piper (gói Python `piper-tts`). Thẻ chưa có file, file lỗi, hay trình duyệt không phát được thì app đọc bằng giọng của trình duyệt. Câu ví dụ luôn đọc bằng giọng của trình duyệt (G1 chỉ tạo audio cho từ). Nghe chậm phát cùng file đó ở tốc độ 0,65, không có file riêng.

## Giọng được dùng

Chỉ dùng giọng mà bộ dữ liệu gốc cho phép dùng để bán. Danh sách nằm ở `tools/audio/giong.json`:

| Giọng | Bộ dữ liệu, giấy phép |
| --- | --- |
| thorsten (low, medium, high) | Thorsten-Voice, CC0 1.0 |
| kerstin (low) | Kerstin, CC0 1.0 |
| eva_k (x_low), karlsson (low), ramona (low) | M-AILABS, giấy phép kiểu BSD |
| pavoque | bị chặn: CC BY-NC-SA 4.0, cấm dùng thương mại |

Giọng không có trong danh sách thì workflow dừng ngay, trước khi tải model. Thêm giọng mới: đọc giấy phép bộ dữ liệu ở model card trước, rồi thêm vào `cho_phep`.

Giọng đang dùng của web: `AUDIO_GIONG` (mặc định `de_DE-thorsten-medium`). Đổi giọng thì tạo hết audio bằng giọng mới trước, rồi mới đổi `AUDIO_GIONG`.

## Chạy

Ở GitHub: Actions → audio → Run workflow.

- **nghe-thu**: đọc 20 chữ mẫu (`tools/audio/mau-nghe-thu.txt`) bằng các giọng ghi ở ô giọng (cách nhau bằng dấu cách). File nghe thử và số đo (dung lượng mỗi từ, thời gian đọc) nằm ở nhánh `ci-results`, thư mục `audio-thu/`.
- **tao-het**: một giọng. Workflow hỏi máy chủ những chữ chưa có audio (`php artisan app:audio-can-tao`), đọc bằng Piper, chép file vào `~/lernbuch/shared/audio` trên alwaysdata, rồi ghi vào bảng `media_assets` (`php artisan app:audio-dang-ky`). Chạy lại chỉ tạo phần còn thiếu, nên thêm thẻ mới thì chạy lại là đủ. Kết quả ở `latest-audio-tao-het.txt` trên nhánh `ci-results`.

## File và địa chỉ

- Tên file: `<giọng>/<2 ký tự đầu của hash>/<hash>.mp3`, hash = sha1(`giọng|normal|chữ`), chữ đã chuẩn hoá (Unicode NFC, gộp khoảng trắng). Python (`tools/audio/giong.py`) và PHP (`app/Support/AmThanh.php`) tính như nhau, cùng kiểm bằng `tests/chung/audio-hash.json`.
- Chữ đem đi đọc là mặt trước của thẻ, bỏ phần trong ngoặc, "/" đọc thành dấu phẩy (`der/die Angestellte` → `der, die Angestellte`).
- MP3 32 kbps một kênh: khoảng 4 KB mỗi từ, 8 KB mỗi câu (đo bằng thorsten medium ngày 27/11/2026).
- Thư mục `shared/audio` dùng chung cho mọi bản phát hành; mỗi lần deploy nối nó vào `public/audio`, nên web đọc file thẳng ở `/audio/...`. Chuyển sang kho file khác (vd R2) sau này: chép thư mục audio sang đó và đổi `AUDIO_URL`.
