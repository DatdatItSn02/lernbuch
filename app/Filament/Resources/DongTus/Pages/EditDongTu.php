<?php

namespace App\Filament\Resources\DongTus\Pages;

use App\Filament\Resources\DongTus\DongTuResource;
use App\Models\Verb;
use Filament\Resources\Pages\EditRecord;

/**
 * Lưu động từ: nghĩa và bảng chia giữ các phần không có trên form (vd nghĩa tiếng Trung, bảng khác); bảng để trống cả thì bỏ.
 */
class EditDongTu extends EditRecord
{
    protected static string $resource = DongTuResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $dongTu = $this->getRecord();
        assert($dongTu instanceof Verb);

        $nghia = $dongTu->meanings ?? [];
        $nghiaNhap = is_array($data['meanings'] ?? null) ? $data['meanings'] : [];

        foreach (['vi', 'en'] as $l) {
            $chu = trim((string) ($nghiaNhap[$l] ?? ''));

            if ($chu === '') {
                unset($nghia[$l]);
            } else {
                $nghia[$l] = $chu;
            }
        }

        $data['meanings'] = $nghia;

        $bang = $dongTu->conjugation ?? [];
        $nhap = is_array($data['conjugation'] ?? null) ? $data['conjugation'] : [];

        foreach (Verb::BANG as $ma => $soO) {
            $dong = [];

            for ($i = 0; $i < $soO; $i++) {
                $dong[] = trim((string) ($nhap[$ma][$i] ?? ''));
            }

            if (implode('', $dong) === '') {
                unset($bang[$ma]);
            } else {
                $bang[$ma] = $dong;
            }
        }

        $data['conjugation'] = $bang === [] ? null : $bang;

        return $data;
    }
}
