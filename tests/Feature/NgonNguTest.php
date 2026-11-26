<?php

use App\Models\User;
use App\Notifications\XacMinhEmail;
use App\Support\NgonNgu;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

// Chuẩn bị đa ngôn ngữ (mục tự kiểm 19 của G1): ngôn ngữ giao diện theo tài khoản; thiếu bản dịch thì hiện tiếng Việt, không hiện mã khoá.

it('trang app học đặt <html lang> theo ngôn ngữ giao diện và có mã CSRF cho nút đăng xuất', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/app/hom-nay')->assertOk()
        ->assertSee('<html lang="vi">', false)
        ->assertSee('<meta name="csrf-token" content="', false);
});

it('chọn ngôn ngữ chưa có bản dịch (tiếng Anh khi G1 chỉ có tiếng Việt): trang, API và email đều tiếng Việt', function () {
    $user = User::factory()->create(['ui_locale' => 'en']);

    expect(NgonNgu::giaoDien())->toBe(['vi'])
        ->and($user->preferredLocale())->toBe('vi');
    $this->actingAs($user)->get('/app/hom-nay')->assertSee('<html lang="vi">', false);
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ui_locale', 'vi');
    $this->putJson('/api/v1/toi/ho-so', ['goal' => 'work'])->assertStatus(422)
        ->assertJsonPath('errors.goal.0', 'Chưa có khoá học nào để chọn mục tiêu và trình độ.');
});

it('ngôn ngữ đã bật nhưng còn thiếu bản dịch: chữ thiếu hiện tiếng Việt, không bao giờ hiện mã khoá', function () {
    config(['app.ngon_ngu_giao_dien' => ['vi', 'en']]);
    $user = User::factory()->unverified()->create(['ui_locale' => 'en']);

    $this->actingAs($user)->get('/xac-minh-email')->assertOk()->assertSee('<html lang="en">', false);
    $this->getJson('/api/v1/toi')->assertStatus(403);
    $user->forceFill(['email_verified_at' => now()])->save();
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ui_locale', 'en');
    $loi = $this->putJson('/api/v1/toi/ho-so', ['goal' => 'work'])->assertStatus(422)->json('errors.goal.0');
    expect($loi)->toBe('Chưa có khoá học nào để chọn mục tiêu và trình độ.');

    App::setLocale($user->preferredLocale());
    $thu = (new XacMinhEmail)->toMail($user);
    expect(App::getLocale())->toBe('en')
        ->and($thu->subject)->toBe('Xác minh email Lernbuch')
        ->and($thu->actionText)->not->toContain('thu.');
});

it('bài ngữ pháp và bộ bài tập có ngôn ngữ giải thích, mặc định tiếng Việt; cùng slug được có bản giải thích khác', function () {
    $bay = now()->getTimestamp();
    $bai = fn (array $doi = []) => DB::table('grammar_lessons')->insert($doi + [
        'lang' => 'de', 'slug' => 'perfekt', 'title' => 'Perfekt', 'blocks' => '[]', 'created_at' => $bay,
    ]);

    $bai();
    expect(DB::table('grammar_lessons')->value('base_lang'))->toBe('vi');
    $bai(['base_lang' => 'en', 'title' => 'Perfect tense']);
    expect(DB::table('grammar_lessons')->where('slug', 'perfekt')->count())->toBe(2);
    expect(fn () => $bai())->toThrow(UniqueConstraintViolationException::class);

    DB::table('exercise_sets')->insert(['lang' => 'de', 'slug' => 'so-nhieu-1', 'title' => 'Số nhiều 1', 'section' => 'plural', 'created_at' => $bay]);
    expect(DB::table('exercise_sets')->value('base_lang'))->toBe('vi');
});
