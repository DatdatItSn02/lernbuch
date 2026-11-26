@extends('layouts.tai-khoan')

@section('tieu-de', __('Tạo tài khoản'))

@section('noi-dung')
    <p class="mt-3 text-body text-ink-2">{{ __('Miễn phí. Tiến độ học lưu theo tài khoản, học trên máy nào cũng tiếp được.') }}</p>
    <form method="POST" action="{{ route('register.store') }}" class="mt-4" novalidate>
        @csrf
        <x-o-nhap name="name" :label="__('Tên của bạn')" autocomplete="name" autofocus required maxlength="100" />
        <x-o-nhap name="email" type="email" :label="__('Email')" autocomplete="email" required maxlength="191"
            :ghichu="__('Mình gửi link xác minh tới email này.')" />
        <x-o-nhap name="password" type="password" :label="__('Mật khẩu')" autocomplete="new-password" required
            :ghichu="__('Ít nhất 8 ký tự, có cả chữ và số.')" />
        <x-o-nhap name="password_confirmation" type="password" :label="__('Nhập lại mật khẩu')" autocomplete="new-password" required />
        <x-nut-chinh>{{ __('Tạo tài khoản') }}</x-nut-chinh>
    </form>
    <p class="mt-6 text-body text-ink-2">
        {{ __('Đã có tài khoản?') }}
        <a href="{{ route('login') }}" class="font-semibold text-tim underline underline-offset-4 hover:text-tim-strong">{{ __('Đăng nhập') }}</a>
    </p>
@endsection
