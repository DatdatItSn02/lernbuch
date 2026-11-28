<?php

use App\Support\PhatAm\KetQuaCham;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// Mục tự kiểm 14: không lưu ghi âm ở đâu: không file nào trên đĩa, không cột nào trong database, không dòng log nào.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
    $this->actingAs(nguoiHocAi());
});

/** @return list<string> mọi file trong storage/ (trừ file log, kiểm riêng) */
function fileTrongStorage(): array
{
    return array_values(array_filter(array_map(fn ($f) => $f->getPathname(), File::allFiles(storage_path())), fn ($p) => ! str_ends_with($p, '.log')));
}

it('sau 20 lượt chấm (ok, im lặng, lỗi) không có file mới, không cột nào chứa âm thanh, log không có chuỗi base64', function () {
    $truoc = fileTrongStorage();
    $daGhi = [];
    Log::listen(function ($e) use (&$daGhi) {
        $daGhi[] = $e->message.json_encode($e->context);
    });
    $audio = wav64(1.5);
    $dau = substr($audio, 0, 200);
    chamGia()->kich(array_fill(0, 5, KetQuaCham::loi('http')));

    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/v1/ai/phat-am', ['card_id' => theMau('das Brötchen'), 'audio' => $i % 4 === 3 ? wav64(1.0, 0) : $audio]);
    }

    expect(DB::table('pronunciation_attempts')->count())->toBe(20)
        ->and(DB::table('pronunciation_attempts')->distinct()->pluck('status')->sort()->values()->all())->toBe(['empty', 'error', 'ok'])
        ->and(array_values(array_diff(fileTrongStorage(), $truoc)))->toBe([]);

    foreach (['pronunciation_attempts', 'ai_requests'] as $bang) {
        $chu = json_encode(DB::table($bang)->get()->all());
        expect($chu)->not->toContain('RIFF')->not->toContain($dau)->not->toContain(substr($audio, 1000, 80));
    }

    expect(implode("\n", $daGhi))->not->toContain($dau)->not->toContain('RIFF');
});
