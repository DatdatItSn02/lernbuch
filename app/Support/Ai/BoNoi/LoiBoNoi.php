<?php

namespace App\Support\Ai\BoNoi;

use RuntimeException;

/**
 * Lỗi khi gọi nhà cung cấp. TAM_THOI: thử lại được (quá tải, đứt mạng, hết giờ). CAU_HINH: khoá sai, model sai, thiếu khoá.
 * BI_CHAN: nhà cung cấp từ chối nội dung. Thông điệp chỉ có mã HTTP và mã trạng thái, không chép thân trả lời, không có khoá.
 */
final class LoiBoNoi extends RuntimeException
{
    public const TAM_THOI = 'tam_thoi';

    public const CAU_HINH = 'cau_hinh';

    public const BI_CHAN = 'bi_chan';

    public function __construct(public readonly string $loai, string $thongDiep = '')
    {
        parent::__construct($thongDiep);
    }

    /** Lỗi HTTP: 429 và 5xx là tạm thời, còn lại (400, 401, 403, 404…) là cấu hình. */
    public static function theoMa(string $ten, int $ma, mixed $trangThai): self
    {
        $trangThai = is_string($trangThai) ? substr((string) preg_replace('/[^A-Z_]/', '', $trangThai), 0, 40) : '';

        return new self($ma === 429 || $ma >= 500 ? self::TAM_THOI : self::CAU_HINH, trim("{$ten}: HTTP {$ma} {$trangThai}"));
    }
}
