<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Email đặt lại mật khẩu, gửi qua hàng đợi. Chữ lấy từ lang/<ngôn ngữ>.json. */
class DatLaiMatKhau extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
