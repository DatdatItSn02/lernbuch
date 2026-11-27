<?php

use App\Models\User;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;

it('trả 200 ở /up', function () {
    $this->get('/up')->assertOk();
});

it('trang chào hiện đúng chữ tiếng Việt và tiếng Đức', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Học tiếng Đức cho người Việt')
        ->assertSee('Straße')
        ->assertSee('ẞ');
});

it('mở được khung app học, cả đường dẫn con, khi đã đăng nhập và xác minh email', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/app')->assertOk()->assertSee('id="app"', false);
    $this->get('/app/hom-nay')->assertOk()->assertSee('id="app"', false);
    // Giao diện tối áp trước khi vẽ, đọc đúng khoá cài đặt của app React
    $this->get('/app/hoc')->assertOk()->assertSee("localStorage.getItem('lernbuch.caidat.v1')", false);
});

it('chưa đăng nhập thì app học về trang đăng nhập', function () {
    $this->get('/app/hom-nay')->assertRedirect('/dang-nhap');
});

it('không lộ file .env và log', function () {
    $this->get('/.env')->assertNotFound();

    // /storage/... đi qua route file của Laravel: chưa ký thì bị chặn (403), không trả nội dung.
    $log = $this->get('/storage/logs/laravel.log');
    expect($log->status())->toBeIn([403, 404])
        ->and($log->getContent())->not->toContain('local.ERROR');
});

it('có manifest đủ điều kiện cài app: tên, trang mở đầu, mở như app, biểu tượng 192 và 512 có trên máy chủ', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['start_url'])->toBe('/app')
        ->and($manifest['id'])->toBe('/app')
        ->and($manifest['name'])->toBe('Lernbuch')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(3)
        ->and(array_column($manifest['icons'], 'sizes'))->toContain('192x192', '512x512')
        ->and(array_column($manifest['shortcuts'], 'url'))->toBe(['/app/hoc', '/app/tra-tu']);

    foreach ($manifest['icons'] as $icon) {
        [$rong, $cao] = getimagesize(public_path(ltrim($icon['src'], '/')));
        expect("{$rong}x{$cao}")->toBe($icon['sizes']);
    }
});

it('trang kiểm chữ có đủ vai trò chữ và không cho máy tìm kiếm lập chỉ mục', function () {
    $html = $this->get('/kiem-chu')->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('GROẞE GRÜẞE')
        ->assertSee('ỳ ý ỷ ỹ ỵ')
        ->getContent();

    foreach (['Be Vietnam Pro', 'Patrick Hand'] as $font) {
        expect($html)->toContain('data-font="'.$font.'"');
    }
    expect(substr_count($html, 'data-font-sample'))->toBe(20);
    expect($html)->not->toContain('data-font="Nunito"');
});

it('/up trả 500 khi một kiểm tra sức khoẻ lỗi (để workflow deploy tự quay lại)', function () {
    config(['app.debug' => false]);
    Event::listen(DiagnosingHealth::class, fn () => throw new RuntimeException('database không trả lời'));

    $this->get('/up')->assertStatus(500);
});

it('/up không lỗi trên máy dev dù có kiểm tra cố ý hỏng chỉ dành cho bản phát hành', function () {
    expect(config('app.release'))->toBe('dev');
    $this->get('/up')->assertOk();
});
