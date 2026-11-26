<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('in dung lượng database và số bảng theo dạng deploy ghi lại', function () {
    $soBang = (int) DB::selectOne('select count(*) as n from information_schema.tables where table_schema = database()')->n;

    expect(Artisan::call('app:dung-luong'))->toBe(0);
    expect(trim(Artisan::output()))->toMatch('/^Database: \d+\.\dM \('.$soBang.' bảng\)$/u');
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
