<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email xác minh địa chỉ, gửi qua hàng đợi để trang đăng ký không phải chờ máy chủ thư.
 * Chữ lấy từ lang/<ngôn ngữ giao diện của người nhận>/thu.php.
 */
class XacMinhEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('thu.xac_minh.tieu_de'))
            ->line(__('thu.xac_minh.loi_dau'))
            ->action(__('thu.xac_minh.nut'), $url)
            ->line(__('thu.xac_minh.het_han', ['phut' => config('auth.verification.expire', 60)]))
            ->line(__('thu.xac_minh.loi_cuoi'));
    }
}
