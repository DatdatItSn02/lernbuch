<?php

use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Sổ tay: đánh dấu sao, ghi chú riêng trên thẻ. Mục tự kiểm 8: người A không đọc, không sửa được sổ tay của người B.

beforeEach(function () {
    $this->seed();
});

it('đánh dấu sao rồi ghi chú: lưu theo tài khoản, thẻ từ và danh sách thẻ đều thấy', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $brot = $m->the['das Brot'];
    $this->actingAs($user = User::factory()->create());

    $this->putJson("/api/v1/the/{$brot}/so-tay", ['starred' => true])->assertOk()->assertExactJson(['data' => ['starred' => true, 'note' => null]]);
    $this->putJson("/api/v1/the/{$brot}/so-tay", ['note' => "  Brot là bánh mì nguyên ổ.\nBrötchen là ổ nhỏ.  "])->assertOk()
        ->assertExactJson(['data' => ['starred' => true, 'note' => "Brot là bánh mì nguyên ổ.\nBrötchen là ổ nhỏ."]]);

    $this->getJson("/api/v1/the/{$brot}")->assertOk()->assertJsonPath('data.bookmark', ['starred' => true, 'note' => "Brot là bánh mì nguyên ổ.\nBrötchen là ổ nhỏ."])
        ->assertJsonPath('data.starred', true);
    $this->getJson('/api/v1/chu-de/banh-mi-tu-vung')->assertJsonPath('data.cards.1.front', 'das Brot')->assertJsonPath('data.cards.1.starred', true)
        ->assertJsonPath('data.cards.0.starred', false);

    $dong = DB::table('user_card_notes')->where('user_id', $user->id)->get();
    expect($dong)->toHaveCount(1)->and($dong[0]->created_at)->toBeGreaterThan(0)->and($dong[0]->updated_at)->toBeGreaterThanOrEqual($dong[0]->created_at);
});

it('gửi ô nào đổi ô đó; bỏ sao và xoá ghi chú thì xoá hẳn dòng', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $brot = $m->the['das Brot'];
    $this->actingAs($user = User::factory()->create());

    $this->putJson("/api/v1/the/{$brot}/so-tay", ['note' => 'nhớ số nhiều Brote'])->assertOk()->assertJsonPath('data', ['starred' => false, 'note' => 'nhớ số nhiều Brote']);
    $this->putJson("/api/v1/the/{$brot}/so-tay", ['starred' => true])->assertOk()->assertJsonPath('data.note', 'nhớ số nhiều Brote');
    $this->putJson("/api/v1/the/{$brot}/so-tay", ['note' => ''])->assertOk()->assertJsonPath('data', ['starred' => true, 'note' => null]);
    expect(DB::table('user_card_notes')->where('user_id', $user->id)->count())->toBe(1);

    $this->putJson("/api/v1/the/{$brot}/so-tay", ['starred' => false])->assertOk()->assertJsonPath('data', ['starred' => false, 'note' => null]);
    expect(DB::table('user_card_notes')->where('user_id', $user->id)->count())->toBe(0);

    // Không gửi ô nào: không đổi gì
    $this->putJson("/api/v1/the/{$brot}/so-tay", [])->assertOk()->assertJsonPath('data', ['starred' => false, 'note' => null]);
    expect(DB::table('user_card_notes')->count())->toBe(0);
});

it('dữ liệu sai thì 422 đúng ô, không ghi gì', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $brot = $m->the['das Brot'];
    $this->actingAs(User::factory()->create());

    $this->putJson("/api/v1/the/{$brot}/so-tay", ['note' => str_repeat('ă', 501)])->assertStatus(422)->assertJsonValidationErrors('note');
    $this->putJson("/api/v1/the/{$brot}/so-tay", ['starred' => 'co'])->assertStatus(422)->assertJsonValidationErrors('starred');
    $this->putJson("/api/v1/the/{$brot}/so-tay", ['note' => ['a']])->assertStatus(422)->assertJsonValidationErrors('note');
    $this->putJson("/api/v1/the/{$brot}/so-tay", ['note' => str_repeat('ă', 500)])->assertOk();
    expect(DB::table('user_card_notes')->count())->toBe(1);
});

it('danh sách sổ tay: lọc sao, có ghi chú, tìm chữ; sửa gần nhất trước; số đếm từng loại', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $this->actingAs(User::factory()->create());

    $this->putJson('/api/v1/the/'.$m->the['das Brot'].'/so-tay', ['starred' => true]);
    $this->putJson('/api/v1/the/'.$m->the['zahlen'].'/so-tay', ['note' => 'khác zählen (đếm)']);
    $this->putJson('/api/v1/the/'.$m->the['das Mehl'].'/so-tay', ['starred' => true, 'note' => 'không có số nhiều']);
    DB::table('user_card_notes')->where('card_id', $m->the['das Brot'])->update(['updated_at' => time() - 300]);
    DB::table('user_card_notes')->where('card_id', $m->the['zahlen'])->update(['updated_at' => time() - 200]);

    $r = $this->getJson('/api/v1/so-tay')->assertOk();
    expect(array_column($r->json('data'), 'front'))->toBe(['das Mehl', 'zahlen', 'das Brot']);
    $r->assertJsonPath('data.0.bookmark.note', 'không có số nhiều')->assertJsonPath('data.0.bookmark.starred', true)
        ->assertJsonPath('data.0.meanings.vi', 'bột mì')
        ->assertJsonPath('meta.counts', ['all' => 3, 'starred' => 2, 'noted' => 2])
        ->assertJsonPath('meta.total', 3);

    expect(array_column($this->getJson('/api/v1/so-tay?loc=sao')->json('data'), 'front'))->toBe(['das Mehl', 'das Brot'])
        ->and(array_column($this->getJson('/api/v1/so-tay?loc=ghi-chu')->json('data'), 'front'))->toBe(['das Mehl', 'zahlen'])
        ->and(array_column($this->getJson('/api/v1/so-tay?tim=tra%20tien')->json('data'), 'front'))->toBe(['zahlen']);
    $this->getJson('/api/v1/so-tay?loc=tat')->assertStatus(422)->assertJsonValidationErrors('loc');
});

it('người A không đọc, không sửa được sổ tay của người B; thẻ không xem được thì 404 (mục tự kiểm 8)', function () {
    $b = User::factory()->create();
    $m = (new NoiDungThuSeeder)->tao(chuTheRieng: $b->id);
    $brot = $m->the['das Brot'];

    $this->actingAs($b)->putJson("/api/v1/the/{$brot}/so-tay", ['starred' => true, 'note' => 'ghi chú riêng của B'])->assertOk();
    $this->putJson('/api/v1/the/'.$m->the['der Hefezopf'].'/so-tay', ['starred' => true])->assertOk();

    $a = User::factory()->create();
    $this->actingAs($a)->getJson('/api/v1/so-tay')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.counts', ['all' => 0, 'starred' => 0, 'noted' => 0])
        ->assertJsonMissing(['note' => 'ghi chú riêng của B']);
    $this->getJson("/api/v1/the/{$brot}")->assertOk()->assertJsonPath('data.bookmark', ['starred' => false, 'note' => null])->assertJsonPath('data.starred', false);
    // Thẻ riêng của B: A không đánh dấu được, không biết là có
    $this->putJson('/api/v1/the/'.$m->the['der Hefezopf'].'/so-tay', ['starred' => true])->assertStatus(404)->assertJson(['code' => 'khong_thay']);
    // Thẻ nháp, thẻ không có
    $this->putJson('/api/v1/the/'.$m->the['der Teig'].'/so-tay', ['starred' => true])->assertStatus(404);
    $this->putJson('/api/v1/the/999999/so-tay', ['starred' => true])->assertStatus(404);

    // A đánh dấu cùng thẻ: dòng của B giữ nguyên
    $this->putJson("/api/v1/the/{$brot}/so-tay", ['note' => 'của A'])->assertOk();
    expect(DB::table('user_card_notes')->where('user_id', $b->id)->where('card_id', $brot)->value('note'))->toBe('ghi chú riêng của B')
        ->and(DB::table('user_card_notes')->where('user_id', $a->id)->count())->toBe(1);

    $this->actingAs($b)->getJson('/api/v1/so-tay')->assertJsonPath('meta.counts.all', 2);
});
