# Chuẩn bị alwaysdata và GitHub cho deploy

Làm một lần, khoảng 15 phút. Sau đó mỗi lần code vào nhánh `main` và test xanh, GitHub Actions tự đưa lên `https://lernbuch.alwaysdata.net`.

Tên menu trong trang quản trị alwaysdata có thể hơi khác chữ dưới đây; ý chính giữ nguyên.

## 1. alwaysdata

1. **PHP 8.4 cho tài khoản.** Trong phần Environment (môi trường), chọn PHP 8.4. Lệnh `php` khi SSH dùng bản này; script deploy dừng nếu không phải 8.4.
2. **Site.** Web → Sites → sửa site `lernbuch.alwaysdata.net`: kiểu PHP, phiên bản 8.4, thư mục gốc (Root directory) `/lernbuch/current/public` (thư mục `lernbuch/current/public` trong home). Đặt ngay từ đầu dù thư mục chưa có: lần deploy đầu tạo ra nó. Để mặc định `/www/` thì mọi trang trả 404 và deploy tự quay lại bản trước.
   Cũng trong trang sửa site, ô php.ini, thêm hai dòng:
   ```
   opcache.revalidate_path = 1
   realpath_cache_ttl = 1
   ```
   Thiếu hai dòng này thì sau mỗi lần deploy, PHP vẫn chạy code của bản cũ nhiều phút (nó nhớ `current` trỏ vào bản nào), workflow thấy trang chủ chưa báo bản mới sau 240 giây nên tự quay lại.
3. **Database.** Databases → MySQL:
   - thêm database `lernbuch_app`;
   - thêm người dùng `lernbuch_app`, đặt mật khẩu mạnh, quyền đầy đủ trên `lernbuch_app`;
   - xem bản MariaDB ghi ở trang này và báo Claude (CI sẽ chạy đúng bản đó).
4. **SSH.** Remote access → SSH: sửa người dùng `lernbuch`, bật SSH và cho đăng nhập bằng mật khẩu (để gắn khoá ở bước 2).

## 2. Khoá SSH trên Windows (PowerShell)

```powershell
# Tạo khoá; khi hỏi passphrase thì bấm Enter hai lần (để trống)
ssh-keygen -t ed25519 -C lernbuch-deploy -f "$env:USERPROFILE\.ssh\lernbuch_deploy"

# Gắn khoá công khai lên alwaysdata (nhập mật khẩu alwaysdata một lần)
type "$env:USERPROFILE\.ssh\lernbuch_deploy.pub" | ssh lernbuch@ssh-lernbuch.alwaysdata.net "mkdir -p ~/.ssh; cat >> ~/.ssh/authorized_keys; chmod 600 ~/.ssh/authorized_keys"

# Thử đăng nhập bằng khoá: phải in ra PHP 8.4.x mà không hỏi mật khẩu
ssh -i "$env:USERPROFILE\.ssh\lernbuch_deploy" lernbuch@ssh-lernbuch.alwaysdata.net "php -v"

# Chép khoá bí mật vào clipboard để dán vào GitHub
Get-Content "$env:USERPROFILE\.ssh\lernbuch_deploy" -Raw | Set-Clipboard
```

## 3. GitHub Secrets

Repo `lernbuch` → Settings → Secrets and variables → Actions → New repository secret:

| Tên | Giá trị |
| --- | --- |
| `ALWAYSDATA_SSH_KEY` | khoá bí mật vừa chép (cả dòng BEGIN và END) |
| `DB_PASSWORD` | mật khẩu người dùng database `lernbuch_app` |

Claude không thấy hai giá trị này. Workflow deploy ghi chúng vào `~/lernbuch/shared/.env` trên alwaysdata; `APP_KEY` sinh một lần ở lần deploy đầu rồi giữ nguyên.

Cuối cùng bật deploy: cùng trang đó, tab **Variables** → New repository variable, tên `DEPLOY_ENABLED`, giá trị `true`. Chưa có biến này (hoặc giá trị khác `true`) thì job deploy luôn bỏ qua, CI vẫn chạy test như thường. Lần deploy đầu: Actions → ci → Run workflow trên nhánh `main`.

## 4. Sau lần deploy đầu

1. **Tác vụ định kỳ.** Scheduled tasks: lệnh `php /home/lernbuch/lernbuch/current/artisan schedule:run`, lịch `* * * * *` (mỗi phút).
2. **Hàng đợi.** Gói Free không cho tạo Services, nên dùng thêm một tác vụ định kỳ mỗi phút (`* * * * *`): lệnh `php /home/lernbuch/lernbuch/current/artisan queue:work --stop-when-empty --tries=3 --max-time=50 --memory=96`. Mỗi phút nó làm hết việc đang chờ rồi tự dừng trước phút kế. Lên gói trả phí thì thay bằng Advanced → Services với `queue:work --sleep=3 --tries=3 --max-time=3600 --memory=96`.
3. **Tài khoản quản trị.** SSH vào rồi chạy `cd ~/lernbuch/current && php artisan app:create-admin`, nhập email và mật khẩu (ít nhất 12 ký tự).
4. **Kiểm lịch và hàng đợi** (mục 13 của phần tự kiểm). Sau khi bật hai mục 1 và 2 được 10 phút, SSH vào `~/lernbuch/current` rồi chạy `php artisan app:kiem-nhip` (đạt khi có ít nhất 9/10 nhịp). Chạy `php artisan app:thu-hang-doi`, chờ một phút rồi chạy lệnh `--kiem=<mã>` nó in ra (đạt khi job thử xong và job cố ý lỗi vào `failed_jobs` sau 3 lần thử).

Mỗi lần deploy, log của job deploy (và nhánh `ci-results`) ghi thêm: số nhịp 10 phút qua trước khi deploy, dung lượng thư mục app, cả thư mục nhà và database sau khi dọn bản cũ. Gói Free có 1 GB tính chung file, mail và database.

## 5. Hộp thư gửi email (cho G1)

Web gửi email xác minh tài khoản và quên mật khẩu qua máy chủ thư của alwaysdata ([hướng dẫn tạo địa chỉ](https://help.alwaysdata.com/en/docs/emails/create-an-email-address/)). Bạn tạo một hộp thư chỉ để gửi:

1. admin.alwaysdata.com → **Emails → Addresses**, bấm nút thêm địa chỉ mới.
2. **Domain:** chọn `lernbuch.alwaysdata.net`. alwaysdata chỉ cho tạo hộp thư trên tên miền đã có trong tài khoản; ô này không có tên miền nào để chọn thì dừng lại và báo mình (khi đó dùng Brevo, hoặc mua tên miền riêng).
3. **Local part** (phần trước @): `noreply`, thành địa chỉ `noreply@lernbuch.alwaysdata.net`.
4. **Password:** một mật khẩu dài, chỉ dùng cho hộp thư này; lưu vào trình quản lý mật khẩu.
5. **Quota:** 50 MB, để thư trả về không chiếm 1 GB dùng chung với web. Các phần khác (Antispam, Redirect, Auto-reply) để mặc định, rồi lưu.
6. GitHub → repo `lernbuch` → Settings → Secrets and variables → Actions → New repository secret: tên `MAIL_PASSWORD`, giá trị là mật khẩu vừa đặt. Claude không thấy giá trị này.
7. Báo Claude địa chỉ vừa tạo (không gửi mật khẩu).

Web gửi qua `smtp-lernbuch.alwaysdata.net`, cổng 465 (SSL/TLS), tên đăng nhập là địa chỉ email đầy đủ ([thông số SMTP](https://help.alwaysdata.com/en/docs/emails/use-an-email-address/)). Thư trả về (địa chỉ sai, hộp thư đầy) đọc ở webmail Roundcube của alwaysdata, đăng nhập bằng địa chỉ và mật khẩu trên. Thư gửi từ `@lernbuch.alwaysdata.net` có thể vào thư rác, đủ cho giai đoạn thử; trước khi bán nên dùng tên miền riêng và bật SPF, DKIM, DMARC.

## Quay lại bản trước

GitHub → Actions → rollback → Run workflow. Workflow đổi `current` về bản tốt gần nhất trước bản đang chạy, chờ trang chủ báo đúng bản đó rồi kiểm `/up`; log ghi `Sau khi quay lại: web chạy Bản …, /up trả 200`. Database giữ nguyên. Muốn về bản mới: Actions → ci → Run workflow trên nhánh `main`.
