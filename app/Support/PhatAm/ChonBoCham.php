<?php

namespace App\Support\PhatAm;

/** Chọn bộ chấm: PRON_DRIVER=fake (ngoài production) thì bộ chấm giả, còn lại Azure. Khoá chỉ dùng trên máy chủ. */
final class ChonBoCham
{
    public static function gia(): bool
    {
        return config('ai.phat_am.bo_cham') === 'fake' && ! app()->isProduction();
    }

    public static function coKhoa(): bool
    {
        return trim((string) config('ai.azure.khoa')) !== '' && self::diaChi() !== '';
    }

    public static function diaChi(): string
    {
        return BoChamAzure::diaChi((string) config('ai.azure.vung'), (string) config('ai.azure.goc'));
    }

    public static function boCham(): BoCham
    {
        if (self::gia()) {
            return app(BoChamGia::class);
        }

        return new BoChamAzure(trim((string) config('ai.azure.khoa')), self::diaChi(), max(1, (int) config('ai.phat_am.cho_toi_da', 15)));
    }
}
