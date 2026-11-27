<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Dùng chung cho các test học (HocApiTest, CheDoHocTest): giờ theo múi giờ của người học, một lượt chấm như app gửi.

const MUI_GIO_HOC = 'Asia/Ho_Chi_Minh';

function lucHoc(string $gio): CarbonImmutable
{
    return CarbonImmutable::parse($gio, MUI_GIO_HOC);
}

function gioHoc(?int $luc): ?string
{
    return $luc === null ? null : CarbonImmutable::createFromTimestamp($luc, MUI_GIO_HOC)->format('Y-m-d H:i:s');
}

/** @return array<string, mixed> một lượt chấm như app gửi */
function luot(int $the, int $cham, CarbonImmutable $luc, ?int $phien = null, ?int $thoiGian = 3000, string $cheDo = 'flashcard', ?string $uid = null): array
{
    return [
        'uid' => $uid ?? (string) Str::ulid(),
        'card_id' => $the,
        'session_id' => $phien,
        'mode' => $cheDo,
        'rating' => $cham,
        'reviewed_at_ms' => (int) $luc->getTimestampMs(),
        'duration_ms' => $thoiGian,
    ];
}
