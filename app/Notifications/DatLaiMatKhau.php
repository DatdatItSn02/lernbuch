<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Email đặt lại mật khẩu, gửi qua hàng đợi. Chữ lấy từ lang/<ngôn ngữ giao diện của người nhận>/thu.php. */
class DatLaiMatKhau extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        $kho = config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject(__('thu.dat_lai.tieu_de'))
            ->line(__('thu.dat_lai.loi_dau'))
            ->action(__('thu.dat_lai.nut'), $url)
            ->line(__('thu.dat_lai.het_han', ['phut' => config("auth.passwords.{$kho}.expire")]))
            ->line(__('thu.dat_lai.loi_cuoi'));
    }
}
