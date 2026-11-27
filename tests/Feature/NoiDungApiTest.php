<?php

use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;

// API nội dung: loại thẻ, cây chủ đề (Chủ đề, Cấp độ, Giao tiếp), trang chủ đề, thẻ từ; mục tự kiểm 8 phần thẻ riêng.

beforeEach(function () {
    $this->seed();
});

/** @return list<string> mặt trước của thẻ trong kết quả */
function matTruoc(array $the): array
{
    return array_column($the, 'front');
}

it('chưa đăng nhập thì 401, chưa xác minh email thì 403, không lộ nội dung', function () {
    (new NoiDungThuSeeder)->tao();

    $this->getJson('/api/v1/chu-de')->assertStatus(401)->assertJson(['code' => 'chua_dang_nhap']);
    $this->getJson('/api/v1/the/1')->assertStatus(401);
    $this->actingAs(User::factory()->unverified()->create())->getJson('/api/v1/chu-de/banh-mi-tu-vung')
        ->assertStatus(403)->assertJson(['code' => 'chua_xac_minh'])->assertJsonMissing(['front' => 'das Brötchen']);
});

it('loại thẻ: chỉ loại có thẻ đã xuất bản, theo thứ tự, kèm số thẻ', function () {
    (new NoiDungThuSeeder)->tao();

    $this->actingAs(User::factory()->create())->getJson('/api/v1/bo-the')->assertOk()
        ->assertExactJson(['data' => [
            ['code' => 'vocab', 'name' => 'Từ vựng', 'card_count' => 30],
            ['code' => 'comm', 'name' => 'Giao tiếp', 'card_count' => 1],
        ]]);
});

it('cây chủ đề Từ vựng: nhóm › chủ đề vừa › chủ đề nhỏ theo thứ tự; bỏ chủ đề đã ẩn, thẻ nháp, thẻ riêng của người khác', function () {
    $chu = User::factory()->create();
    (new NoiDungThuSeeder)->tao(chuTheRieng: $chu->id);

    $r = $this->actingAs(User::factory()->create())->getJson('/api/v1/chu-de')->assertOk();

    $r->assertJsonPath('data.collection', ['code' => 'vocab', 'name' => 'Từ vựng'])
        ->assertJsonPath('data.topic_count', 2)
        ->assertJsonPath('data.card_count', 30)
        ->assertJsonPath('data.levels', [['level' => 1, 'count' => 27], ['level' => 2, 'count' => 2], ['level' => 0, 'count' => 1]])
        ->assertJsonPath('data.pos', [['pos' => 'other', 'count' => 22], ['pos' => 'noun', 'count' => 4], ['pos' => 'verb', 'count' => 3], ['pos' => 'adj', 'count' => 1]])
        ->assertJsonPath('data.groups', [
            ['slug' => 'con-so', 'name' => 'Con số', 'topic_count' => 1, 'card_count' => 25, 'parents' => [
                ['slug' => 'dem-so', 'name' => 'Đếm số', 'topics' => [['slug' => 'so-dem-tu-vung', 'name' => 'Số đếm', 'icon' => '1F35E', 'card_count' => 25]]],
            ]],
            ['slug' => 'an-uong', 'name' => 'Ăn uống', 'topic_count' => 1, 'card_count' => 5, 'parents' => [
                ['slug' => 'banh-va-tiem-banh', 'name' => 'Bánh và tiệm bánh', 'topics' => [['slug' => 'banh-mi-tu-vung', 'name' => 'Bánh mì · bánh ngọt', 'icon' => '1F35E', 'card_count' => 5]]],
            ]],
        ]);
    expect(json_encode($r->json()))->not->toContain('chu-de-da-an')->not->toContain('Hefezopf')->not->toContain('Teig');

    // Chủ của thẻ riêng thấy thêm thẻ của mình
    $this->actingAs($chu)->getJson('/api/v1/chu-de')->assertOk()->assertJsonPath('data.card_count', 31);
});

it('lọc theo cấp (khoảng cấp, 0 = chưa xếp) và loại từ: chỉ còn chủ đề có thẻ khớp, số đếm theo bộ lọc', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());
    $chuDe = fn (array $r) => collect($r['data']['groups'])->flatMap(fn ($g) => collect($g['parents'])->flatMap(fn ($p) => $p['topics']))
        ->mapWithKeys(fn ($t) => [$t['slug'] => $t['card_count']])->all();

    $r = $this->getJson('/api/v1/chu-de?cap=2')->assertOk()->json();
    expect($chuDe($r))->toBe(['banh-mi-tu-vung' => 2])
        ->and($r['data']['levels'])->toBe([['level' => 1, 'count' => 27], ['level' => 2, 'count' => 2], ['level' => 0, 'count' => 1]])
        ->and($r['data']['pos'])->toBe([['pos' => 'noun', 'count' => 1], ['pos' => 'verb', 'count' => 1]]);

    expect($chuDe($this->getJson('/api/v1/chu-de?cap=0')->json()))->toBe(['banh-mi-tu-vung' => 1])
        ->and($chuDe($this->getJson('/api/v1/chu-de?cap=1,2')->json()))->toBe(['so-dem-tu-vung' => 25, 'banh-mi-tu-vung' => 4]);

    $r = $this->getJson('/api/v1/chu-de?loai_tu=verb')->assertOk()->json();
    expect($chuDe($r))->toBe(['so-dem-tu-vung' => 2, 'banh-mi-tu-vung' => 1])
        ->and($r['data']['levels'])->toBe([['level' => 1, 'count' => 2], ['level' => 2, 'count' => 1]]);

    // Cấp chưa có thẻ nào: danh sách rỗng, không lỗi
    $this->getJson('/api/v1/chu-de?cap=5')->assertOk()->assertJsonPath('data.groups', [])->assertJsonPath('data.card_count', 0);
});

it('Giao tiếp và loại thẻ khác dùng cùng cây chủ đề; loại chưa có thẻ thì rỗng', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    $this->getJson('/api/v1/chu-de?bo=comm')->assertOk()
        ->assertJsonPath('data.collection.name', 'Giao tiếp')
        ->assertJsonPath('data.groups.0.parents.0.topics', [['slug' => 'o-tiem-banh-giao-tiep', 'name' => 'Ở tiệm bánh', 'icon' => '1F35E', 'card_count' => 1]]);
    $this->getJson('/api/v1/chu-de?bo=phrase')->assertOk()->assertJsonPath('data.groups', [])->assertJsonPath('data.topic_count', 0);
});

it('bộ lọc sai thì 422 đúng ô', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    $this->getJson('/api/v1/chu-de?bo=khong-co')->assertStatus(422)->assertJsonValidationErrors('bo');
    $this->getJson('/api/v1/chu-de?cap=7')->assertStatus(422)->assertJsonValidationErrors('cap');
    $this->getJson('/api/v1/chu-de?cap=1,a')->assertStatus(422)->assertJsonValidationErrors('cap');
    $this->getJson('/api/v1/chu-de?loai_tu=Noun%27')->assertStatus(422)->assertJsonValidationErrors('loai_tu');
    $this->getJson('/api/v1/chu-de/so-dem-tu-vung?trang=0')->assertStatus(422)->assertJsonValidationErrors('trang');
    $this->getJson('/api/v1/chu-de/so-dem-tu-vung?tim='.str_repeat('a', 101))->assertStatus(422)->assertJsonValidationErrors('tim');
});

it('trang chủ đề: thông tin, vị trí, số thẻ theo cấp và loại từ, 20 thẻ mỗi trang theo thứ tự cấp', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    $r = $this->getJson('/api/v1/chu-de/banh-mi-tu-vung')->assertOk();
    $r->assertJsonPath('data.topic', [
        'slug' => 'banh-mi-tu-vung', 'name' => 'Bánh mì · bánh ngọt', 'name_en' => 'Bread · cake', 'icon' => '1F35E',
        'collection' => ['code' => 'vocab', 'name' => 'Từ vựng'], 'card_count' => 5,
    ])
        ->assertJsonPath('data.parent', ['slug' => 'banh-va-tiem-banh', 'name' => 'Bánh và tiệm bánh'])
        ->assertJsonPath('data.group', ['slug' => 'an-uong', 'name' => 'Ăn uống'])
        ->assertJsonPath('data.levels', [['level' => 1, 'count' => 2], ['level' => 2, 'count' => 2], ['level' => 0, 'count' => 1]])
        ->assertJsonPath('data.pos', [['pos' => 'noun', 'count' => 3], ['pos' => 'adj', 'count' => 1], ['pos' => 'verb', 'count' => 1]])
        ->assertJsonPath('meta', ['page' => 1, 'per_page' => 20, 'total' => 5, 'pages' => 1]);
    // Có cấp trước, cấp thấp trước; thẻ chưa xếp cấp cuối
    expect(matTruoc($r->json('data.cards')))->toBe(['das Brötchen', 'das Brot', 'das Mehl', 'backen', 'süß']);

    $dau = $r->json('data.cards.0');
    expect($dau)->toMatchArray([
        'collection' => 'vocab', 'front' => 'das Brötchen', 'article' => 'das', 'word' => 'Brötchen', 'gender' => 'n', 'pos' => 'noun',
        'forms' => 'die Brötchen', 'level' => 'A1', 'image' => '1F956', 'starred' => false,
    ])->and($dau['meanings'])->toEqual(['vi' => 'bánh mì nhỏ (ổ tròn)', 'en' => 'bread roll', 'zh' => '小面包'])
        ->and($r->json('data.cards.2.forms'))->toBe('–')
        ->and($r->json('data.cards.4.level'))->toBeNull();

    $so = $this->getJson('/api/v1/chu-de/so-dem-tu-vung')->assertOk();
    $so->assertJsonPath('meta', ['page' => 1, 'per_page' => 20, 'total' => 25, 'pages' => 2]);
    expect($so->json('data.cards'))->toHaveCount(20)
        ->and(array_slice(matTruoc($so->json('data.cards')), 0, 4))->toBe(['zahlen', 'zählen', 'die Zahl', 'null']);
    expect(matTruoc($this->getJson('/api/v1/chu-de/so-dem-tu-vung?trang=2')->assertJsonPath('meta.page', 2)->json('data.cards')))
        ->toBe(['siebzehn', 'achtzehn', 'neunzehn', 'zwanzig', 'dreißig']);
    // Trang quá số trang thì về trang cuối
    $this->getJson('/api/v1/chu-de/so-dem-tu-vung?trang=99')->assertOk()->assertJsonPath('meta.page', 2)->assertJsonCount(5, 'data.cards');
});

it('trang chủ đề: lọc theo chữ (không dấu, ae/oe/ue, tiếng Việt không dấu), loại từ, cấp', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());
    $loc = fn (string $q) => matTruoc($this->getJson('/api/v1/chu-de/banh-mi-tu-vung?'.$q)->assertOk()->json('data.cards'));

    expect($loc('tim=brotchen'))->toBe(['das Brötchen'])
        ->and($loc('tim=broetchen'))->toBe(['das Brötchen'])
        ->and($loc('tim=BRÖTCHEN'))->toBe(['das Brötchen'])
        ->and($loc('tim=banh%20mi'))->toBe(['das Brötchen', 'das Brot'])
        ->and($loc('tim=flour'))->toBe(['das Mehl'])
        ->and($loc('tim=%25'))->toBe([])
        ->and($loc('loai_tu=verb'))->toBe(['backen'])
        ->and($loc('cap=0'))->toBe(['süß'])
        ->and($loc('cap=2&loai_tu=noun'))->toBe(['das Mehl']);

    $this->getJson('/api/v1/chu-de/banh-mi-tu-vung?tim=khong-co-tu-nay')->assertOk()
        ->assertJsonPath('data.cards', [])->assertJsonPath('meta', ['page' => 1, 'per_page' => 20, 'total' => 0, 'pages' => 1]);
});

it('chủ đề không có, đã ẩn, hay không phải chủ đề nhỏ thì 404 khong_thay', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    foreach (['khong-co', 'chu-de-da-an-tu-vung', 'banh-va-tiem-banh', 'an-uong', 'Banh_Mi'] as $slug) {
        $this->getJson('/api/v1/chu-de/'.$slug)->assertStatus(404)->assertExactJson(['code' => 'khong_thay', 'message' => 'Không tìm thấy.']);
    }
});

it('thẻ từ: nghĩa, câu ví dụ, mẹo, vị trí trong chủ đề, sổ tay của chính mình', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    $r = $this->getJson('/api/v1/the/'.$m->the['das Brötchen'])->assertOk();
    $r->assertJsonPath('data.front', 'das Brötchen')
        ->assertJsonPath('data.word', 'Brötchen')
        ->assertJsonPath('data.collection_name', 'Từ vựng')
        ->assertJsonPath('data.tip', 'Số nhiều giống số ít: ein Brötchen, zwei Brötchen.')
        ->assertJsonPath('data.examples.0.sentence', 'Ich möchte zwei Brötchen, bitte.')
        ->assertJsonPath('data.examples.0.translations.vi', 'Cho tôi hai cái bánh mì nhỏ.')
        ->assertJsonPath('data.examples.1.sentence', 'Die Brötchen sind frisch.')
        ->assertJsonPath('data.verb', null)
        ->assertJsonPath('data.topic', ['slug' => 'banh-mi-tu-vung', 'name' => 'Bánh mì · bánh ngọt'])
        ->assertJsonPath('data.nav', ['index' => 1, 'total' => 5, 'prev' => null, 'next' => ['id' => $m->the['das Brot'], 'front' => 'das Brot']])
        ->assertJsonPath('data.bookmark', ['starred' => false, 'note' => null]);

    $this->getJson('/api/v1/the/'.$m->the['backen'])->assertOk()
        ->assertJsonPath('data.verb.infinitive', 'backen')
        ->assertJsonPath('data.verb.auxiliary', 'haben')
        ->assertJsonPath('data.verb.preterite', 'backte')
        ->assertJsonPath('data.verb.conjugation.pres', ['backe', 'backst', 'backt', 'backen', 'backt', 'backen'])
        ->assertJsonPath('data.nav.index', 4)
        ->assertJsonPath('data.nav.prev.front', 'das Mehl')
        ->assertJsonPath('data.nav.next.front', 'süß')
        ->assertJsonPath('data.article', null)
        ->assertJsonPath('data.word', 'backen');
});

it('thẻ nháp, thẻ không có, mã sai thì 404; thẻ riêng của người khác cũng 404 (mục tự kiểm 8)', function () {
    $chu = User::factory()->create();
    $m = (new NoiDungThuSeeder)->tao(chuTheRieng: $chu->id);
    $rieng = $m->the['der Hefezopf'];

    $this->actingAs(User::factory()->create());
    foreach ([$m->the['der Teig'], $rieng, 999999] as $id) {
        $this->getJson('/api/v1/the/'.$id)->assertStatus(404)->assertExactJson(['code' => 'khong_thay', 'message' => 'Không tìm thấy.']);
    }
    $this->getJson('/api/v1/the/abc')->assertStatus(404)->assertJson(['code' => 'khong_thay']);
    $this->getJson('/api/v1/chu-de/banh-mi-tu-vung')->assertOk()->assertJsonPath('data.topic.card_count', 5)->assertJsonMissing(['front' => 'der Hefezopf']);

    // Chủ thẻ thì xem được
    $this->actingAs($chu)->getJson('/api/v1/the/'.$rieng)->assertOk()->assertJsonPath('data.front', 'der Hefezopf');
});
