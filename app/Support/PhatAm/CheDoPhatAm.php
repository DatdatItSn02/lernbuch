<?php

namespace App\Support\PhatAm;

/**
 * Cách chấm phát âm đang dùng: off (tắt), azure (máy chủ gọi Azure, hoặc bộ chấm giả ngoài production), browser (bản dự phòng:
 * trình duyệt tự nhận giọng, máy chủ chỉ so chữ). Thiếu khoá Azure hoặc tháng này đã dùng tới ngưỡng thì tự về browser.
 */
final class CheDoPhatAm
{
    public const TAT = 'off';

    public const AZURE = 'azure';

    public const TRINH_DUYET = 'browser';

    public static function cua(): string
    {
        $cd = CaiDatPhatAm::tatCa();

        if ($cd['provider'] === self::TAT) {
            return self::TAT;
        }

        if ($cd['provider'] !== self::AZURE) {
            return self::TRINH_DUYET;
        }

        if (ChonBoCham::gia()) {
            return self::AZURE;
        }

        if (ChonBoCham::coKhoa() && HanMucPhatAm::giayThangNay() < $cd['switch_at_seconds']) {
            return self::AZURE;
        }

        return self::TRINH_DUYET;
    }
}
