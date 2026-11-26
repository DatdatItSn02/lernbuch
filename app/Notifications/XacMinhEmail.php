<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Email xác minh địa chỉ, gửi qua hàng đợi để trang đăng ký không phải chờ máy chủ thư. Chữ lấy từ lang/<ngôn ngữ>.json. */
class XacMinhEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
