<?php

use App\Models\User;
use App\Support\Ai\BoNoi\LoiBoNoi;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\DanhSachThu;
use Database\Seeders\NoiDungThuSeeder;

// Mục tự kiểm 7: công tắc tắt khẩn cấp và danh sách thử. Tắt AI không ảnh hưởng phần học.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
});

it('công tắc tắt: ai_tam_tat cho mọi người, khối ai là hidden', function () {
    CaiDatAi::luu(['enabled' => false], null);
    $this->actingAs(nguoiHocAi());

    giaiThich(theMau('das Brötchen'))->assertStatus(503)->assertJson(['code' => 'ai_tam_tat', 'message' => 'AI đang tạm tắt. Phần học vẫn dùng bình thường.']);
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai', ['state' => 'hidden']);
    expect(aiGia()->daNhan)->toBe([]);
});

it('giai đoạn thử: ngoài danh sách thì ai_chua_mo và hidden; trong danh sách thì dùng được', function () {
    $this->actingAs(nguoiHocAi([], false));
    giaiThich(theMau('das Brötchen'))->assertForbidden()->assertJson(['code' => 'ai_chua_mo']);
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai', ['state' => 'hidden']);

    $this->actingAs(nguoiHocAi());
    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('mở cho mọi người từ 18 tuổi: người ngoài danh sách cũng dùng được', function () {
    CaiDatAi::luu(['audience' => 'adults'], null);
    $this->actingAs(nguoiHocAi([], false));

    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('tắt giữa hai lượt gọi thì lượt sau bị chặn; bật lại thì chạy; bỏ khỏi danh sách cũng có hiệu lực ngay', function () {
    $this->actingAs($user = nguoiHocAi());
    giaiThich(theMau('das Brötchen'))->assertOk();

    CaiDatAi::luu(['enabled' => false], null);
    giaiThich(theMau('das Mehl'))->assertStatus(503)->assertJson(['code' => 'ai_tam_tat']);

    CaiDatAi::luu(['enabled' => true], null);
    giaiThich(theMau('das Mehl'))->assertOk();

    DanhSachThu::dat($user, false);
    giaiThich(theMau('backen'))->assertForbidden()->assertJson(['code' => 'ai_chua_mo']);
});

it('AI tắt hay nhà cung cấp lỗi thì phần học vẫn chạy', function () {
    $this->actingAs(nguoiHocAi());
    aiGia()->kich([new LoiBoNoi(LoiBoNoi::CAU_HINH)]);
    giaiThich(theMau('das Brötchen'))->assertStatus(503);
    $this->getJson('/api/v1/hom-nay')->assertOk();

    CaiDatAi::luu(['enabled' => false], null);
    $this->getJson('/api/v1/hom-nay')->assertOk();
    $this->getJson('/api/v1/the/'.theMau('das Brötchen'))->assertOk();
    $this->getJson('/api/v1/toi')->assertOk();
});

it('tài khoản mới mặc định không nằm trong danh sách thử', function () {
    expect(DanhSachThu::co(User::factory()->create()))->toBeFalse();
});
