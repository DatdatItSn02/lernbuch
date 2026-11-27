<?php

use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('in dung lượng database và số bảng, rồi số nội dung đã xuất bản, theo dạng deploy ghi lại', function () {
    $m = (new NoiDungThuSeeder)->tao()->taoLuyenTap();
    // Thẻ riêng của người học, thẻ nháp, chủ đề đã ẩn không tính; một file audio đã đăng ký
    $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: User::factory()->create()->id);
    DB::table('media_assets')->insert(['kind' => 'audio', 'hash' => str_repeat('a', 40), 'disk' => 'public', 'path' => 'x.mp3', 'mime' => 'audio/mpeg']);
    $soBang = (int) DB::selectOne('select count(*) as n from information_schema.tables where table_schema = database()')->n;

    expect(Artisan::call('app:dung-luong'))->toBe(0);
    $dong = explode("\n", trim(Artisan::output()));
    expect($dong[0])->toMatch('/^Database: \d+\.\dM \('.$soBang.' bảng\)$/u')
        // 31 thẻ (5 + 1 + 25), 3 chủ đề nhỏ (1 chủ đề đã ẩn), 4 bài (1 bài nháp), 13 câu (1 câu nháp), 3 động từ
        ->and($dong[1])->toBe('Nội dung: 31 thẻ, 3 chủ đề nhỏ, 4 bài ngữ pháp, 13 câu bài tập, 3 động từ, 1 file audio');
    expect($soBang)->toBeGreaterThan(90);
});

it('không đo được database thì báo lỗi mà không lộ chi tiết kết nối', function () {
    $chinh = config('database.default');
    config(['database.connections.hong' => array_merge(config("database.connections.{$chinh}"), ['database' => 'khong_co_database_nay'])]);

    $this->artisan('app:dung-luong', ['--ket-noi' => 'hong'])
        ->expectsOutputToContain('Database: không đo được')
        ->doesntExpectOutputToContain('khong_co_database_nay')
        ->assertFailed();
});
