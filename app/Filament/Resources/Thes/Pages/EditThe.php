<?php

namespace App\Filament\Resources\Thes\Pages;

use App\Filament\Resources\Thes\TheResource;
use App\Models\Card;
use App\Models\CardExample;
use App\Models\Topic;
use App\Support\NhatKy;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Sửa thẻ: cột của thẻ lưu như thường (nhật ký card.update); nghĩa (card_translations) và câu ví dụ (card_examples) lưu riêng,
 * mỗi phần đổi thì ghi một dòng nhật ký card.meanings, card.examples. Sau khi lưu tính lại chữ để tìm và số thẻ của chủ đề.
 */
class EditThe extends EditRecord
{
    protected static string $resource = TheResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $the = $this->the();
        $nghia = $the->nghia();

        foreach (array_keys(Card::THU_TIENG) as $l) {
            $data['nghia_'.$l] = $nghia[$l] ?? null;
        }

        $data['cau_vi_du'] = $the->examples()->get()->map(fn (CardExample $c) => [
            'sentence' => $c->sentence,
            'vi' => $c->translations['vi'] ?? null,
            'en' => $c->translations['en'] ?? null,
        ])->all();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Card);
        $nghia = [];

        foreach (array_keys(Card::THU_TIENG) as $l) {
            $nghia[$l] = trim((string) ($data['nghia_'.$l] ?? ''));
            unset($data['nghia_'.$l]);
        }

        /** @var array<int|string, array<string, mixed>> $cauNhap */
        $cauNhap = is_array($data['cau_vi_du'] ?? null) ? $data['cau_vi_du'] : [];
        unset($data['cau_vi_du']);
        $cau = array_values(array_map(fn (array $c) => [
            'sentence' => trim((string) ($c['sentence'] ?? '')),
            'vi' => trim((string) ($c['vi'] ?? '')),
            'en' => trim((string) ($c['en'] ?? '')),
        ], $cauNhap));

        DB::transaction(function () use ($record, $data, $nghia, $cau): void {
            $chuDeTruoc = $record->topic_id;
            $record->update($data);
            $this->luuNghia($record, $nghia);
            $this->luuCau($record, $cau);
            $record->tinhChuTim();
            Topic::demLaiThe($chuDeTruoc);
        });

        return $record;
    }

    /** @param  array<string, string>  $nghia  thứ tiếng → nghĩa (rỗng là xoá) */
    private function luuNghia(Card $the, array $nghia): void
    {
        $truoc = $the->nghia();
        $sau = array_filter($nghia, fn (string $v) => $v !== '');
        ksort($truoc);
        ksort($sau);

        if ($truoc === $sau) {
            return;
        }

        $bay = now()->getTimestamp();
        $nguoi = NhatKy::nguoiLam()?->id;

        foreach ($nghia as $l => $chu) {
            if ($chu === '') {
                DB::table('card_translations')->where('card_id', $the->id)->where('lang', $l)->delete();

                continue;
            }

            DB::table('card_translations')->upsert(
                [['card_id' => $the->id, 'lang' => $l, 'meaning' => $chu, 'updated_at' => $bay, 'updated_by' => $nguoi]],
                ['card_id', 'lang'],
                ['meaning', 'updated_at', 'updated_by'],
            );
        }

        NhatKy::ghi('card.meanings', $the, $truoc, $sau);
    }

    /** @param  list<array{sentence: string, vi: string, en: string}>  $cau */
    private function luuCau(Card $the, array $cau): void
    {
        $cu = $the->examples()->get();
        $truoc = $cu->map(fn (CardExample $c) => [
            'sentence' => $c->sentence,
            'vi' => (string) ($c->translations['vi'] ?? ''),
            'en' => (string) ($c->translations['en'] ?? ''),
        ])->all();

        if ($truoc === $cau) {
            return;
        }

        // Câu giữ nguyên chữ thì giữ các bản dịch khác (vd tiếng Trung), nguồn và cờ câu viết tay của câu cũ
        $cuTheoCau = $cu->keyBy('sentence');
        $bay = now()->getTimestamp();
        $nguoi = NhatKy::nguoiLam()?->id;
        $dong = [];

        foreach ($cau as $i => $c) {
            $goc = $cuTheoCau->get($c['sentence']);
            $dich = array_filter([...($goc?->translations ?? []), 'vi' => $c['vi'], 'en' => $c['en']], fn ($v) => $v !== '' && $v !== null);
            $dong[] = [
                'card_id' => $the->id,
                'sort' => $i,
                'sentence' => $c['sentence'],
                'translations' => $dich === [] ? null : json_encode($dich, JSON_UNESCAPED_UNICODE),
                'is_manual' => $goc !== null && $goc->getAttribute('is_manual') ? 1 : 0,
                'source_id' => $goc?->getAttribute('source_id'),
                'created_at' => $bay,
                'updated_at' => $bay,
                'updated_by' => $nguoi,
            ];
        }

        DB::table('card_examples')->where('card_id', $the->id)->delete();

        if ($dong !== []) {
            DB::table('card_examples')->insert($dong);
        }

        NhatKy::ghi('card.examples', $the, ['examples' => $truoc], ['examples' => $cau]);
    }

    private function the(): Card
    {
        $the = $this->getRecord();
        assert($the instanceof Card);

        return $the;
    }
}
