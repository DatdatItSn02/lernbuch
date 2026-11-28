<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chế độ xem thử (tạm thời, bật bằng XEM_THU=true): app học và API mở không cần đăng nhập. Ai chưa đăng nhập thì
 * được coi là một tài khoản khách dùng chung, chỉ trong request đó: không tạo phiên đăng nhập, không ghi login_events.
 * Tài khoản khách không có mật khẩu, không phải nhân viên: không đăng nhập được, không vào được trang quản trị.
 * Người đã đăng nhập thật (ví dụ nhân viên) vẫn là chính họ. Tắt: đặt XEM_THU=false rồi deploy.
 */
class KhachXemThu
{
    public const EMAIL = 'khach-xem-thu@lernbuch.invalid';

    public const TEN = 'Khách xem thử';

    public static function bat(): bool
    {
        return (bool) config('app.xem_thu', false);
    }

    public static function la(mixed $user): bool
    {
        return $user instanceof User && $user->email === self::EMAIL;
    }

    /** Tài khoản khách dùng chung; chưa có thì tạo (đã xác minh email, đang hoạt động). */
    public static function khach(): User
    {
        $khach = User::query()->where('email', self::EMAIL)->first();

        if ($khach instanceof User) {
            return $khach;
        }

        try {
            $khach = new User;
            $khach->forceFill(['name' => self::TEN, 'email' => self::EMAIL, 'email_verified_at' => now(), 'status' => 'active'])->save();

            // Đọc lại để có giá trị mặc định của database (múi giờ, ngôn ngữ)
            return $khach->refresh();
        } catch (UniqueConstraintViolationException) {
            // Hai request đầu tiên tới cùng lúc: request kia đã tạo
            return User::query()->where('email', self::EMAIL)->firstOrFail();
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (self::bat() && Auth::guard('web')->guest()) {
            $khach = self::khach();

            // Khách bị khoá trong trang quản trị thì coi như chưa đăng nhập (trang đăng nhập đang tắt sẽ đưa về trang chủ)
            if ($khach->status === 'active') {
                Auth::guard('web')->setUser($khach);
            }
        }

        return $next($request);
    }
}
