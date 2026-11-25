<?php

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

it('mở được khung app học, cả đường dẫn con', function () {
    $this->get('/app')->assertOk()->assertSee('id="app"', false);
    $this->get('/app/hom-nay')->assertOk()->assertSee('id="app"', false);
});

it('không lộ file .env và log', function () {
    $this->get('/.env')->assertNotFound();

    // /storage/... đi qua route file của Laravel: chưa ký thì bị chặn (403), không trả nội dung.
    $log = $this->get('/storage/logs/laravel.log');
    expect($log->status())->toBeIn([403, 404])
        ->and($log->getContent())->not->toContain('local.ERROR');
});

it('có manifest để cài app', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['start_url'])->toBe('/app')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(3);
});
