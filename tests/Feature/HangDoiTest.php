<?php

use App\Jobs\GhiNhip;
use App\Jobs\ViecLoiThu;
use App\Jobs\ViecThu;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Hàng đợi thật bằng bảng jobs (như trên alwaysdata), không dùng sync.
beforeEach(fn () => config(['queue.default' => 'database']));

it('lịch có việc ghi nhịp chạy mỗi phút', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('ghi-nhip')->toContain('* * * * *');
});

it('job thử chạy xong qua hàng đợi database', function () {
    ViecThu::dispatch('abc');
    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

    expect(Cache::get('viec-thu:abc'))->toBe('xong')
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('job cố ý lỗi vào failed_jobs sau đúng 3 lần thử', function () {
    ViecLoiThu::dispatch('xyz');

    $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

    expect(Cache::get('viec-loi-thu:xyz'))->toBe(3)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->value('exception'))->toContain('Lỗi cố ý để thử hàng đợi (mã xyz)');
});

it('lệnh thử hàng đợi: đưa 2 job vào, worker chạy xong thì kiểm đạt', function () {
    Artisan::call('app:thu-hang-doi');
    preg_match('/mã ([a-z0-9]{8})/', Artisan::output(), $m);
    $ma = $m[1] ?? '';
    expect($ma)->toHaveLength(8)
        ->and(DB::table('jobs')->count())->toBe(2);

    // Chưa chạy worker thì chưa đạt
    $this->artisan('app:thu-hang-doi', ['--kiem' => $ma])->assertFailed();

    $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();
    $this->artisan('app:thu-hang-doi', ['--kiem' => $ma])
        ->expectsOutputToContain('đã thử 3 lần, đã vào failed_jobs')
        ->assertSuccessful();
});

it('10 phút lịch chạy mỗi phút qua hàng đợi thì đủ 10 nhịp', function () {
    $this->travelTo(now()->startOfMinute()->addSeconds(5));
    for ($i = 0; $i < 10; $i++) {
        $this->artisan('schedule:run')->assertSuccessful();
        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();
        if ($i < 9) {
            $this->travel(1)->minutes();
        }
    }

    $this->artisan('app:kiem-nhip')->expectsOutputToContain('Nhịp trong 10 phút qua: 10/10')->assertSuccessful();
});

it('thiếu nhịp (lịch hay hàng đợi dừng) thì kiểm nhịp báo lỗi', function () {
    $now = now()->getTimestamp();
    foreach ([0, 1, 2, 3, 4] as $i) {
        Cache::put(GhiNhip::key($now - $i * 60), $now, now()->addDay());
    }

    $this->artisan('app:kiem-nhip')->expectsOutputToContain('5/10')->assertFailed();
    $this->artisan('app:kiem-nhip', ['--phut' => 5, '--toi-thieu' => 5])->assertSuccessful();
});

it('job ghi nhịp ghi đúng phút hiện tại', function () {
    (new GhiNhip)->handle();

    expect(Cache::has(GhiNhip::key(now()->getTimestamp())))->toBeTrue()
        ->and(Cache::has(GhiNhip::key(now()->getTimestamp() - 60)))->toBeFalse();
});
