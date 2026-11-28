<?php

namespace App\Support\PhatAm;

use RuntimeException;

/**
 * Bộ chấm giả cho test tự động, test trình duyệt và máy dev: trả theo kịch bản xếp hàng, hết hàng thì trả mẫu (90 điểm, mọi
 * từ đúng). Đăng ký là singleton: lấy bằng app(BoChamGia::class). Không bao giờ chạy ở production.
 */
final class BoChamGia implements BoCham
{
    /** @var list<KetQuaCham> */
    private array $hang = [];

    /** @var list<array{wav: string, cauMau: string}> */
    public array $daNhan = [];

    /** @param list<KetQuaCham> $kichBan */
    public function kich(array $kichBan): self
    {
        array_push($this->hang, ...$kichBan);

        return $this;
    }

    public function cham(string $wav, string $cauMau): KetQuaCham
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Bộ chấm giả không chạy ở production');
        }

        $this->daNhan[] = ['wav' => $wav, 'cauMau' => $cauMau];

        return array_shift($this->hang) ?? self::mau($cauMau);
    }

    public static function mau(string $cauMau): KetQuaCham
    {
        $tu = [];

        foreach (preg_split('/\s+/u', trim($cauMau)) ?: [] as $t) {
            $t = trim($t, " \t.,!?;:\"'()«»„“”");

            if ($t !== '') {
                $tu[] = ['word' => $t, 'score' => 90, 'error' => 'none'];
            }
        }

        return new KetQuaCham(KetQuaCham::OK, null, 90, 92, 88, 100, $tu);
    }
}
