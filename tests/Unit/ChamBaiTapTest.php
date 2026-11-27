<?php

use App\Support\LuyenTap\ChamBaiTap;

// Chấm câu bài tập như Lernbuch. Bảng ca thử chung tests/chung/cham-bai-tap.json: Vitest chạy đúng bảng này với bản
// TypeScript (baiTap.test.ts), để app chấm ngay trên máy và máy chủ chấm lại ra cùng một điểm.

/** @return array<string, mixed> */
function bangChamBaiTap(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__).'/chung/cham-bai-tap.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('chấm đúng như bảng ca thử chung', function (array $ca) {
    expect(ChamBaiTap::cham($ca['cau'], $ca['tra_loi']))->toBe($ca['diem']);
})->with(fn () => collect(bangChamBaiTap()['ca'])->mapWithKeys(fn (array $ca) => [$ca['ten'] => [$ca]])->all());

it('bảng ca thử có đủ 4 dạng câu và đủ 3 mức điểm', function () {
    $ca = collect(bangChamBaiTap()['ca']);

    expect($ca->pluck('cau.kind')->unique()->sort()->values()->all())->toBe(['fill', 'fix', 'mc', 'order'])
        ->and($ca->pluck('diem')->unique()->sort()->values()->all())->toBe([0, 1, 2])
        ->and($ca->pluck('ten')->duplicates())->toBeEmpty();
});

it('chữ để so: viết thường, ä ö ü ß thành ae oe ue ss, bỏ dấu tiếng Việt, dấu câu thành khoảng trắng', function () {
    expect(ChamBaiTap::fold('Äpfel, Straße!'))->toBe('aepfel strasse')
        ->and(ChamBaiTap::fold('  Tiếng   Việt — đẹp '))->toBe('tieng viet đep')
        ->and(ChamBaiTap::fold('?!'))->toBe('')
        ->and(ChamBaiTap::strip("  Hallo \u{A0} Welt!! "))->toBe('Hallo Welt')
        ->and(ChamBaiTap::strip('Hallo .'))->toBe('Hallo ');
});
