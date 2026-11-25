<?php

use App\Models\User;

it('chưa đăng nhập vào /admin thì về trang đăng nhập', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('trang đăng nhập quản trị mở được', function () {
    $this->get('/admin/login')->assertOk();
});

it('người học thường không vào được trang quản trị', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('nhân viên đang hoạt động vào được trang quản trị', function () {
    $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertOk();
});

it('nhân viên bị khoá không vào được trang quản trị', function () {
    $this->actingAs(User::factory()->staff()->suspended()->create())->get('/admin')->assertForbidden();
});
