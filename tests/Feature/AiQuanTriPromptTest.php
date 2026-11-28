<?php

use App\Filament\Resources\AiPrompts\Pages\ViewAiPrompt;
use App\Models\AiPrompt;
use App\Models\User;
use App\Support\Ai\CaiDatAi;
use App\Support\QuanTri\PromptAi;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Mục tự kiểm 17: trang Prompt (phiên bản, tạo bản mới, chạy thử, dùng, quay lại), đổi model, thiếu quyền ai.manage.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
    $this->word = AiPrompt::dangDung('word');
});

/** @return array<string, mixed> */
function banMoi(array $doi = []): array
{
    return $doi + [
        'system_prompt' => 'Bản thử, trình độ {level}. Chỉ trả về JSON đúng mẫu: {"meaning":"","usage":"","examples":[{"de":"","vi":""}],"common_mistakes":[""]}',
        'user_template' => 'Giải thích ngắn từ {word} ({meaning}).',
        'max_tokens' => 600,
        'temperature' => 0.3,
    ];
}

it('chỉ người có ai.manage vào được ba trang; người học, content_editor bị 403', function () {
    foreach (['/admin/ai-prompt', '/admin/ai-prompt/'.$this->word->id, '/admin/thong-ke-ai', '/admin/nhat-ky-ai'] as $trang) {
        $this->actingAs(User::factory()->create())->get($trang)->assertForbidden();
        $this->actingAs(nhanVien('content_editor'))->get($trang)->assertForbidden();
        $this->actingAs(nhanVien('admin'))->get($trang)->assertOk();
    }
});

it('tạo bản mới: version + 1, chưa dùng, model mặc định lúc tạo, có nhật ký; bản đang dùng không đổi', function () {
    $this->actingAs(nhanVien('admin'));
    CaiDatAi::luu(['default_model' => 'gemini-2.5-flash-lite'], null);

    Livewire::test(ViewAiPrompt::class, ['record' => $this->word->id])->callAction('tao-ban-moi', data: banMoi())->assertHasNoActionErrors();

    $moi = AiPrompt::query()->where('kind', 'word')->orderByDesc('version')->first();
    expect($moi->version)->toBe($this->word->version + 1)
        ->and($moi->is_active)->toBeFalse()
        ->and($moi->model)->toBe('gemini-2.5-flash-lite')
        ->and($moi->user_template)->toBe('Giải thích ngắn từ {word} ({meaning}).')
        ->and(AiPrompt::dangDung('word')->id)->toBe($this->word->id)
        ->and(json_decode(dongNhatKy('ai_prompt.tao')['changes'], true)['after']['version'])->toBe($moi->version);
});

it('luật mẫu: sáu ca sai báo lỗi, không lưu', function (string $loai, array $doi, string $o) {
    $this->actingAs(nhanVien('admin'));
    $p = AiPrompt::dangDung($loai);
    $truoc = AiPrompt::query()->count();

    Livewire::test(ViewAiPrompt::class, ['record' => $p->id])->callAction('tao-ban-moi', data: banMoi($doi))->assertHasActionErrors([$o]);

    expect(AiPrompt::query()->count())->toBe($truoc);
})->with([
    'thiếu {level}' => ['word', ['system_prompt' => 'Không có trình độ. JSON'], 'system_prompt'],
    'thiếu tham số' => ['word', ['user_template' => 'Giải thích {word}.'], 'user_template'],
    'tham số lạ' => ['word', ['user_template' => '{word} {meaning} {email}'], 'user_template'],
    'hai cặp thẻ' => ['ask', ['user_template' => '<input>{text}</input> <input>x</input>'], 'user_template'],
    '{text} ngoài thẻ' => ['grammar', ['user_template' => 'Sửa: {text} <input>đoạn</input>'], 'user_template'],
    'token ngoài khoảng' => ['word', ['max_tokens' => 50], 'max_tokens'],
]);

it('luật mẫu ở lớp xử lý: đúng thì null; prompt seed của cả 10 loại đều đúng', function () {
    foreach (AiPrompt::query()->where('is_active', 1)->get() as $p) {
        expect(PromptAi::loiHeThong($p->system_prompt))->toBeNull($p->kind)
            ->and(PromptAi::loiMauCau($p->kind, $p->user_template))->toBeNull($p->kind);
    }

    expect(PromptAi::loiMauCau('chat', 'Tình huống {scenario} <input>{history}</input> {text}'))->not->toBeNull()
        ->and(PromptAi::loiMauCau('lesson', '{level} <input>{topic} {words}</input>'))->toBeNull()
        ->and(fn () => PromptAi::taoBanMoi($this->word, banMoi(['temperature' => 1.5])))->toThrow(InvalidArgumentException::class);
});

it('chạy thử: hiện kết quả, ghi nhật ký AI không có người học, không đổi bản đang dùng', function () {
    $this->actingAs(nhanVien('admin'));
    $moi = PromptAi::taoBanMoi($this->word, banMoi());

    Livewire::test(ViewAiPrompt::class, ['record' => $moi->id])
        ->callAction('chay-thu', data: ['tham_so' => json_encode(['word' => 'essen', 'meaning' => 'ăn', 'level' => 'A1'])])
        ->assertNotified();

    $dong = DB::table('ai_requests')->first();
    expect(aiGia()->daNhan[0]->nguoiDung)->toBe('Giải thích ngắn từ essen (ăn).')
        ->and($dong->user_id)->toBeNull()
        ->and($dong->context_type)->toBe('prompt_test')
        ->and((int) $dong->prompt_id)->toBe($moi->id)
        ->and($dong->status)->toBe('ok')
        ->and((int) $dong->cost_micro_usd)->toBe(1125)
        ->and(AiPrompt::dangDung('word')->id)->toBe($this->word->id);
});

it('chạy thử bị chặn khi chạm trần hệ thống: không gọi AI, nhật ký blocked', function () {
    batAi(['rpm_cap' => 1]);
    dongAi(null, 1);

    $kq = PromptAi::chayThu($this->word, ['word' => 'essen', 'meaning' => 'ăn', 'level' => 'A1']);

    expect($kq['trang_thai'])->toBe('blocked')
        ->and(aiGia()->daNhan)->toBe([])
        ->and(DB::table('ai_requests')->where('context_type', 'prompt_test')->value('status'))->toBe('blocked');
});

it('Dùng bản này: lượt hỏi kế tiếp dùng chữ mới và prompt_id mới, không dùng cache của bản cũ; mỗi loại chỉ một bản đang dùng', function () {
    $hoc = nguoiHocAi();
    $this->actingAs($hoc);
    giaiThich(theMau('das Brötchen'))->assertOk();

    // Đăng nhập lại ở guard web (lượt API trước đã chuyển guard mặc định sang sanctum)
    $this->actingAs(nhanVien('admin'), 'web');
    $moi = PromptAi::taoBanMoi($this->word, banMoi());
    Livewire::test(ViewAiPrompt::class, ['record' => $moi->id])->callAction('dung');

    $this->actingAs($hoc);
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', false);

    expect(aiGia()->daNhan)->toHaveCount(2)
        ->and(aiGia()->daNhan[1]->nguoiDung)->toStartWith('Giải thích ngắn từ das Brötchen')
        ->and((int) DB::table('ai_requests')->orderByDesc('id')->value('prompt_id'))->toBe($moi->id)
        ->and(AiPrompt::query()->where('kind', 'word')->where('is_active', 1)->count())->toBe(1)
        ->and(json_decode(dongNhatKy('ai_prompt.dung')['changes'], true))->toBe(['before' => ['version' => 1], 'after' => ['version' => 2]]);
});

it('Quay lại bản trước: trả về bản cũ, có nhật ký; bản đầu tiên không có nút quay lại', function () {
    $this->actingAs(nhanVien('admin'));
    Livewire::test(ViewAiPrompt::class, ['record' => $this->word->id])->assertActionHidden('quay-lai')->assertActionHidden('dung');

    $moi = PromptAi::taoBanMoi($this->word, banMoi());
    PromptAi::dung($moi);
    Livewire::test(ViewAiPrompt::class, ['record' => $moi->id])->assertActionVisible('quay-lai')->callAction('quay-lai');

    expect(AiPrompt::dangDung('word')->id)->toBe($this->word->id)
        ->and(json_decode(dongNhatKy('ai_prompt.quay_lai')['changes'], true))->toBe(['before' => ['version' => 2], 'after' => ['version' => 1]]);
});

it('đổi model ở Cài đặt AI thì lượt kế tiếp gửi model mới', function () {
    $this->actingAs(nguoiHocAi());
    CaiDatAi::luu(['default_model' => 'gemini-2.5-flash-lite'], null);
    giaiThich(theMau('das Brot'))->assertOk();

    expect(aiGia()->daNhan[0]->model)->toBe('gemini-2.5-flash-lite')
        ->and(DB::table('ai_requests')->value('model'))->toBe('gemini-2.5-flash-lite');
});

it('content_editor gọi thẳng hành động trên trang cũng bị từ chối', function () {
    $this->actingAs(nhanVien('content_editor'));

    Livewire::test(ViewAiPrompt::class, ['record' => $this->word->id])->assertForbidden();
});
