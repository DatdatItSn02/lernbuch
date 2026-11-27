<?php

use App\Models\User;
use App\Support\AmThanh;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Audio Piper tạo sẵn: tên file giống hệt công cụ Python (tests/chung/audio-hash.json), danh sách chữ cần tạo,
// ghi file vào media_assets, API trả địa chỉ audio của thẻ (không có thì app đọc bằng giọng trình duyệt).

const GIONG_THU = 'de_DE-thorsten-medium';

/** @param  list<string>  $chu  @return string đường dẫn manifest.jsonl tạm */
function manifestThu(array $chu, array $doi = []): string
{
    $dong = array_map(function (string $c) use ($doi) {
        $h = AmThanh::hash(GIONG_THU, $c);

        return json_encode([
            'hash' => $h, 'path' => AmThanh::duongDan(GIONG_THU, $h), 'text' => $c, 'voice' => GIONG_THU, 'speed' => 'normal', 'bytes' => 4000, 'duration_ms' => 900, ...$doi,
        ], JSON_UNESCAPED_UNICODE);
    }, $chu);
    $f = tempnam(sys_get_temp_dir(), 'manifest');
    file_put_contents($f, implode("\n", $dong)."\n");

    return $f;
}

/** @return list<string> chữ in ra bởi app:audio-can-tao */
function canTao(array $tuyChon = []): array
{
    Artisan::call('app:audio-can-tao', $tuyChon);

    return array_map(fn (string $d) => json_decode($d, true)['text'], array_values(array_filter(explode("\n", Artisan::output()))));
}

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao();
    config(['app.audio.giong' => GIONG_THU, 'app.audio.url' => '/audio']);
});

it('tên file khớp công cụ Python: chuẩn hoá NFC và khoảng trắng, sha1(giọng|normal|chữ), thư mục theo 2 ký tự đầu', function () {
    $ca = json_decode((string) file_get_contents(base_path('tests/chung/audio-hash.json')), true)['ca'];
    expect(count($ca))->toBeGreaterThanOrEqual(6);

    foreach ($ca as $c) {
        expect(AmThanh::chuanHoa($c['chu']))->toBe($c['chu_chuan'], $c['ten'])
            ->and(AmThanh::hash($c['giong'], $c['chu']))->toBe($c['hash'], $c['ten'])
            ->and(AmThanh::duongDan($c['giong'], $c['hash']))->toBe($c['path'], $c['ten']);
    }
});

it('chữ đem đi đọc: bỏ phần trong ngoặc, "/" đọc thành dấu phẩy, gộp khoảng trắng', function (string $front, string $doc) {
    expect(AmThanh::chuCanDoc($front))->toBe($doc);
})->with([
    'danh từ' => ['das Brötchen', 'das Brötchen'],
    'ghi chú trong ngoặc' => ['die Eltern (Pl.)', 'die Eltern'],
    'hai mạo từ' => ['der/die Angestellte', 'der, die Angestellte'],
    'hai cách viết' => ['Tschüss / Tschüs', 'Tschüss, Tschüs'],
    'khoảng trắng thừa' => ['  Guten   Morgen! ', 'Guten Morgen!'],
]);

it('danh sách cần tạo: mọi thẻ hệ thống đang hiện, mỗi chữ một lần; bỏ thẻ nháp, thẻ riêng, chữ đã có file', function () {
    $m = new NoiDungThuSeeder;
    $m->chuDe = $this->m->chuDe;
    $m->the = $this->m->the;
    $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: User::factory()->create()->id);

    $chu = canTao();
    expect($chu)->toHaveCount(31)
        ->toContain('das Brötchen')->toContain('zählen')->toContain('Ich hätte gern zwei Brötchen.')
        ->not->toContain('der Teig')->not->toContain('der Hefezopf')
        ->and(array_unique($chu))->toHaveCount(31);

    $this->artisan('app:audio-dang-ky', ['manifest' => manifestThu(['das Brötchen', 'zählen'])])->assertSuccessful();
    expect(canTao())->toHaveCount(29)->not->toContain('das Brötchen')
        ->and(canTao(['--gioi-han' => 3]))->toHaveCount(3)
        // Giọng khác: chưa có file nào
        ->and(canTao(['--giong' => 'de_DE-kerstin-low']))->toHaveCount(31);
    $this->artisan('app:audio-can-tao', ['--giong' => 'thorsten'])->assertFailed();
});

it('ghi file vào media_assets: chạy lại không nhân đôi; dòng sai thì bỏ, báo lỗi, dòng đúng vẫn ghi', function () {
    $f = manifestThu(['das Brötchen', 'das Brot']);
    $this->artisan('app:audio-dang-ky', ['manifest' => $f])->expectsOutputToContain('Đã ghi 2 file audio (2 mới), bỏ 0 dòng lỗi.')->assertSuccessful();
    $this->artisan('app:audio-dang-ky', ['manifest' => $f])->expectsOutputToContain('(0 mới)')->assertSuccessful();

    $dong = DB::table('media_assets')->where('kind', 'audio')->where('text_content', 'das Brötchen')->first();
    expect(DB::table('media_assets')->count())->toBe(2)
        ->and($dong)->toMatchObject(['disk' => 'shared', 'mime' => 'audio/mpeg', 'voice' => GIONG_THU, 'speed' => 'normal', 'bytes' => 4000, 'duration_ms' => 900])
        ->and($dong->path)->toBe(AmThanh::duongDan(GIONG_THU, AmThanh::hash(GIONG_THU, 'das Brötchen')));

    $sai = tempnam(sys_get_temp_dir(), 'manifest');
    $tot = json_decode((string) file_get_contents(manifestThu(['das Mehl'])), true);
    file_put_contents($sai, implode("\n", [
        'không phải json',
        json_encode(['hash' => str_repeat('a', 40), 'path' => 'x.mp3', 'text' => 'das Mehl', 'voice' => GIONG_THU]),
        json_encode([...$tot, 'voice' => 'thorsten']),
        json_encode(['text' => 'thiếu hash']),
        json_encode($tot, JSON_UNESCAPED_UNICODE),
    ]));
    $this->artisan('app:audio-dang-ky', ['manifest' => $sai])->expectsOutputToContain('Đã ghi 1 file audio (1 mới), bỏ 4 dòng lỗi.')->assertFailed();
    expect(DB::table('media_assets')->count())->toBe(3);

    $this->artisan('app:audio-dang-ky', ['manifest' => '/khong/co/file.jsonl'])->assertFailed();
});

it('API: thẻ có file thì kèm địa chỉ audio, chưa có thì null; đổi AUDIO_URL thì đổi địa chỉ', function () {
    $this->artisan('app:audio-dang-ky', ['manifest' => manifestThu(['das Brötchen'])])->assertSuccessful();
    $this->actingAs(User::factory()->create());
    $duong = '/audio/'.AmThanh::duongDan(GIONG_THU, AmThanh::hash(GIONG_THU, 'das Brötchen'));

    $the = collect($this->getJson('/api/v1/chu-de/banh-mi-tu-vung')->assertOk()->json('data.cards'))->keyBy('front');
    expect($the['das Brötchen']['audio'])->toBe($duong)
        ->and($the['das Brot']['audio'])->toBeNull()
        ->and($this->getJson('/api/v1/the/'.$this->m->the['das Brötchen'])->json('data.audio'))->toBe($duong)
        ->and(collect($this->postJson('/api/v1/hoc/phien', ['topic' => 'banh-mi-tu-vung'])->json('data.cards'))->firstWhere('front', 'das Brötchen')['audio'])->toBe($duong);

    config(['app.audio.url' => 'https://kho.example.com/audio/']);
    expect($this->getJson('/api/v1/the/'.$this->m->the['das Brötchen'])->json('data.audio'))->toStartWith('https://kho.example.com/audio/de_DE-thorsten-medium/');

    // Đổi giọng mà chưa tạo audio cho giọng mới: mọi thẻ đọc bằng giọng trình duyệt
    config(['app.audio.giong' => 'de_DE-kerstin-low']);
    expect($this->getJson('/api/v1/the/'.$this->m->the['das Brötchen'])->json('data.audio'))->toBeNull();
});
