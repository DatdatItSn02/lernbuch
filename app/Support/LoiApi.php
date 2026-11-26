<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Lỗi của API dạng {"code": "...", "message": "..."}: app đọc code (không đổi theo ngôn ngữ),
 * message lấy từ lang/<ngôn ngữ>/api.php. Lỗi nhập liệu (422) giữ dạng của Laravel: message và errors theo từng ô.
 */
final class LoiApi
{
    private const MA_THEO_TRANG_THAI = [
        401 => 'chua_dang_nhap',
        403 => 'khong_co_quyen',
        404 => 'khong_thay',
        405 => 'khong_thay',
        419 => 'het_phien',
        429 => 'qua_nhieu_lan',
    ];

    /** @param  array<string, string>  $headers */
    public static function json(string $ma, int $trangThai, array $headers = []): JsonResponse
    {
        return new JsonResponse(['code' => $ma, 'message' => __('api.'.$ma)], $trangThai, $headers);
    }

    /** Gắn vào withExceptions: chỉ đổi lỗi của đường dẫn api/*, còn lại để Laravel xử lý như cũ. */
    public static function tuNgoaiLe(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($e instanceof AuthenticationException) {
            return self::json('chua_dang_nhap', 401);
        }

        if ($e instanceof HttpExceptionInterface && isset(self::MA_THEO_TRANG_THAI[$e->getStatusCode()])) {
            /** @var array<string, string> $headers */
            $headers = $e->getHeaders();

            return self::json(self::MA_THEO_TRANG_THAI[$e->getStatusCode()], $e->getStatusCode(), $headers);
        }

        return null;
    }
}
