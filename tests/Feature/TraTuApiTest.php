<?php

use App\Http\Controllers\Api\V1\TraTuController;
use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Tra từ (mục tự kiểm 16): gõ không dấu ở 4 thứ tiếng; "zahlen" và "zählen" ra đúng thẻ, từ khớp đúng chữ đứng đầu.
// Tra chữ bấm trong câu, lịch sử tra (mục tự kiểm 8: không đụng được lịch sử của người khác).

beforeEach(function () {
    $this->seed();
});

/** @return list<string> */
function ketQuaTra(string $q): array
{
    return array_column(test()->getJson('/api/v1/tra-tu?q='.rawurlencode($q))->assertOk()->json('data'), 'front');
}

it('"zahlen" và "zählen": từ đúng từng chữ đứng đầu, rồi dạng khác (số nhiều), rồi từ gần giống', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    $r = $this->getJson('/api/v1/tra-tu?q=zahlen')->assertOk();
    expect(array_column($r->json('data'), 'front'))->toBe(['zahlen', 'die Zahl', 'zählen'])
        ->and(array_column($r->json('data'), 'match'))->toBe(['exact', 'form', 'close'])
        ->and($r->json('meta'))->toBe(['total' => 3, 'limit' => 30]);

    expect(ketQuaTra('zählen'))->toBe(['zählen', 'zahlen', 'die Zahl'])
        ->and(ketQuaTra('Zählen'))->toBe(['zählen', 'zahlen', 'die Zahl'])
        ->and(ketQuaTra('die Zahl')[0])->toBe('die Zahl')
        ->and(ketQuaTra('zahl'))->toBe(['die Zahl', 'zahlen', 'zählen']);
});

it('gõ không dấu ở 4 thứ tiếng: Đức (ö → o hoặc oe), Việt, Anh, Trung; nghĩa trùng đứng trước nghĩa chứa', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    expect(ketQuaTra('brotchen'))->toBe(['das Brötchen', 'Ich hätte gern zwei Brötchen.'])
        ->and(ketQuaTra('broetchen'))->toBe(['das Brötchen', 'Ich hätte gern zwei Brötchen.'])
        ->and(ketQuaTra('Brötchen')[0])->toBe('das Brötchen')
        ->and(ketQuaTra('banh mi'))->toBe(['das Brot', 'das Brötchen', 'Ich hätte gern zwei Brötchen.'])
        ->and(ketQuaTra('bánh mì'))->toBe(['das Brot', 'das Brötchen', 'Ich hätte gern zwei Brötchen.'])
        ->and(ketQuaTra('dem'))->toBe(['zählen'])
        ->and(ketQuaTra('bread'))->toBe(['das Brot', 'das Brötchen', 'Ich hätte gern zwei Brötchen.'])
        ->and(ketQuaTra('pay'))->toBe(['zahlen'])
        ->and(ketQuaTra('面包'))->toBe(['das Brot', 'das Brötchen'])
        ->and(ketQuaTra('甜'))->toBe(['süß'])
        ->and(ketQuaTra('suss'))->toBe(['süß'])
        ->and(ketQuaTra('dreissig'))->toBe(['dreißig']);
});

it('chữ quá ngắn, ký tự đại diện, thẻ nháp và thẻ riêng của người khác không ra', function () {
    $chu = User::factory()->create();
    (new NoiDungThuSeeder)->tao(chuTheRieng: $chu->id);
    $this->actingAs(User::factory()->create());

    expect(ketQuaTra('a'))->toBe([])
        ->and(ketQuaTra(''))->toBe([])
        ->and(ketQuaTra('%'))->toBe([])
        ->and(ketQuaTra('_'))->toBe([])
        ->and(ketQuaTra('teig'))->toBe([])
        ->and(ketQuaTra('hefezopf'))->toBe([]);
    $this->getJson('/api/v1/tra-tu')->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/v1/tra-tu?q='.str_repeat('a', 121))->assertStatus(422)->assertJsonValidationErrors('q');

    $this->actingAs($chu);
    expect(ketQuaTra('hefezopf'))->toBe(['der Hefezopf']);
});

it('kết quả có nghĩa, mạo từ tách riêng, sao của chính mình; tối đa 30 thẻ, kèm tổng số', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());
    $this->putJson('/api/v1/the/'.$m->the['das Brot'].'/so-tay', ['starred' => true]);

    $this->getJson('/api/v1/tra-tu?q=brot')->assertOk()
        ->assertJsonPath('data.0.front', 'das Brot')->assertJsonPath('data.0.article', 'das')->assertJsonPath('data.0.word', 'Brot')
        ->assertJsonPath('data.0.meanings.vi', 'bánh mì')->assertJsonPath('data.0.starred', true)->assertJsonPath('data.0.match', 'exact');

    // 40 thẻ cùng khớp: trả 30 thẻ đầu, tổng số vẫn đủ 40
    foreach (range(1, 40) as $i) {
        $m->themThe('t-so.v', "Einzelwort{$i}", 'other', null, 1, 1, 1, ['vi' => "từ thử {$i}"], ['lemma' => ["einzelwort{$i}"]]);
    }
    $r = $this->getJson('/api/v1/tra-tu?q=einzelwort')->assertOk();
    expect($r->json('data'))->toHaveCount(30)->and($r->json('meta.total'))->toBe(40);
});

it('bấm một chữ trong câu: đúng dạng (số nhiều, dạng chia), viết hoa đầu câu, dấu câu; không có thì rỗng', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());
    $tra = fn (string $chu) => array_column($this->getJson('/api/v1/tra-chu?chu='.rawurlencode($chu))->assertOk()->json('data'), 'front');

    expect($tra('Brötchen,'))->toBe(['das Brötchen'])
        ->and($tra('Brote'))->toBe(['das Brot'])
        ->and($tra('backt'))->toBe(['backen'])
        ->and($tra('backst'))->toBe(['backen'])
        ->and($tra('Zahlen'))->toBe(['zahlen', 'die Zahl'])
        ->and($tra('süßer'))->toBe(['süß'])
        ->and($tra('Hund'))->toBe([])
        ->and($tra('...'))->toBe([]);
    $this->getJson('/api/v1/tra-chu')->assertStatus(422)->assertJsonValidationErrors('chu');
});

it('lịch sử tra: ghi khi mở thẻ, mới nhất trước, gộp lần tra lặp lại, chỉ giữ 100 mục', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $this->actingAs($user = User::factory()->create());

    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'zahlen', 'card_id' => $m->the['zahlen']])->assertCreated();
    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'brot'])->assertCreated();
    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'Brot'])->assertCreated();
    DB::table('search_history')->where('term', 'zahlen')->update(['created_at' => time() - 60]);

    $r = $this->getJson('/api/v1/tra-tu/lich-su')->assertOk();
    expect($r->json('data'))->toHaveCount(2)
        ->and($r->json('data.0'))->toMatchArray(['term' => 'Brot', 'card' => null])
        ->and($r->json('data.1'))->toMatchArray(['term' => 'zahlen', 'card' => ['id' => $m->the['zahlen'], 'front' => 'zahlen']]);

    foreach (range(1, TraTuController::LICH_SU_GIU + 5) as $i) {
        DB::table('search_history')->insert(['user_id' => $user->id, 'term' => "tu {$i}", 'created_at' => time() - 1000 + $i]);
    }
    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'mehl', 'card_id' => $m->the['das Mehl']])->assertCreated();
    expect(DB::table('search_history')->where('user_id', $user->id)->count())->toBe(TraTuController::LICH_SU_GIU)
        ->and($this->getJson('/api/v1/tra-tu/lich-su')->json('data'))->toHaveCount(TraTuController::LICH_SU_HIEN)
        ->and($this->getJson('/api/v1/tra-tu/lich-su')->json('data.0.term'))->toBe('mehl');
});

it('lịch sử tra sai dữ liệu thì 422; thẻ không xem được thì không ghi', function () {
    $chu = User::factory()->create();
    $m = (new NoiDungThuSeeder)->tao(chuTheRieng: $chu->id);
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/v1/tra-tu/lich-su', [])->assertStatus(422)->assertJsonValidationErrors('term');
    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => str_repeat('a', 121)])->assertStatus(422)->assertJsonValidationErrors('term');
    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'teig', 'card_id' => $m->the['der Teig']])->assertStatus(422)->assertJsonValidationErrors('card_id');
    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'hefe', 'card_id' => $m->the['der Hefezopf']])->assertStatus(422)->assertJsonValidationErrors('card_id');
    expect(DB::table('search_history')->count())->toBe(0);
});

it('xoá lịch sử: chỉ mục của mình; mục của người khác thì 404 và vẫn còn (mục tự kiểm 8)', function () {
    (new NoiDungThuSeeder)->tao();
    $b = User::factory()->create();
    $this->actingAs($b)->postJson('/api/v1/tra-tu/lich-su', ['term' => 'của B'])->assertCreated();
    $idB = (int) DB::table('search_history')->where('user_id', $b->id)->value('id');

    $a = User::factory()->create();
    $this->actingAs($a);
    $idA = $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'của A'])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/tra-tu/lich-su', ['term' => 'thứ hai'])->assertCreated();

    $this->getJson('/api/v1/tra-tu/lich-su')->assertJsonMissing(['term' => 'của B']);
    $this->deleteJson("/api/v1/tra-tu/lich-su/{$idB}")->assertStatus(404)->assertExactJson(['code' => 'khong_thay', 'message' => 'Không tìm thấy.']);
    $this->deleteJson("/api/v1/tra-tu/lich-su/{$idA}")->assertNoContent();
    $this->deleteJson("/api/v1/tra-tu/lich-su/{$idA}")->assertStatus(404);
    expect(DB::table('search_history')->where('user_id', $a->id)->pluck('term')->all())->toBe(['thứ hai']);

    $this->deleteJson('/api/v1/tra-tu/lich-su')->assertNoContent();
    expect(DB::table('search_history')->where('user_id', $a->id)->count())->toBe(0)
        ->and(DB::table('search_history')->where('id', $idB)->exists())->toBeTrue();
});
