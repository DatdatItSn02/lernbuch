<?php

namespace App\Support\PhatAm;

/**
 * Kết quả một lượt chấm phát âm.
 *
 * trangThai: ok (chấm được), empty (không nghe thấy tiếng hoặc nhiều tiếng ồn), error (nhà cung cấp lỗi).
 * lyDo: silence, noise, http, timeout, config, hoặc null.
 * tu: mỗi từ ['word' => string, 'score' => int|null, 'error' => none|mispronounced|omitted|inserted, 'phonemes'? => [['p', 'score']]].
 * daGui: âm thanh đã thật sự gửi tới nhà cung cấp chưa (để tính giây của tháng).
 */
final class KetQuaCham
{
    public const OK = 'ok';

    public const RONG = 'empty';

    public const LOI = 'error';

    /** @param list<array<string, mixed>> $tu */
    public function __construct(
        public readonly string $trangThai,
        public readonly ?string $lyDo = null,
        public readonly ?int $tong = null,
        public readonly ?int $chinhXac = null,
        public readonly ?int $troiChay = null,
        public readonly ?int $dayDu = null,
        public readonly array $tu = [],
        public readonly bool $daGui = true,
        public readonly int $ms = 0,
    ) {}

    public static function rong(string $lyDo, bool $daGui = true, int $ms = 0): self
    {
        return new self(self::RONG, $lyDo, daGui: $daGui, ms: $ms);
    }

    public static function loi(string $lyDo, bool $daGui = true, int $ms = 0): self
    {
        return new self(self::LOI, $lyDo, daGui: $daGui, ms: $ms);
    }

    public function voiMs(int $ms): self
    {
        return new self($this->trangThai, $this->lyDo, $this->tong, $this->chinhXac, $this->troiChay, $this->dayDu, $this->tu, $this->daGui, $ms);
    }
}
