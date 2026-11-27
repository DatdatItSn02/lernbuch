<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Service worker của app học (resources/sw/sw.js) ở /sw.js, phạm vi cả trang. Phiên bản lấy theo manifest của bản build:
 * build mới thì nội dung file đổi, trình duyệt cài lại service worker, lưu khung app mới và xoá bản cũ.
 */
class ServiceWorkerController extends Controller
{
    public const MAU = 'sw/sw.js';

    public function __invoke(): Response
    {
        return response(str_replace('__PHIEN_BAN__', self::phienBan(), (string) file_get_contents(resource_path(self::MAU))), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            // Trình duyệt hỏi lại mỗi lần kiểm bản mới, không dùng bản trong bộ nhớ đệm
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /** 12 chữ đầu của mã băm manifest.json của Vite cộng file mẫu; chưa build thì "dev". */
    public static function phienBan(): string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return 'dev';
        }

        return substr(hash('sha256', (string) file_get_contents($manifest).(string) file_get_contents(resource_path(self::MAU))), 0, 12);
    }
}
