@extends('layouts.tai-khoan')

@section('tieu-de', __('Đăng nhập'))

@section('noi-dung')
    <form method="POST" action="{{ route('login.store') }}" class="mt-6" novalidate>
        @csrf
        <x-o-nhap name="email" type="email" :label="__('Email')" autocomplete="email" autofocus required />
        <x-o-nhap name="password" type="password" :label="__('Mật khẩu')" autocomplete="current-password" required />
        <label class="mt-5 flex min-h-11 cursor-pointer items-center gap-3 text-body">
            <input type="checkbox" name="remember" value="1" class="size-5 accent-tim" @checked(old('remember'))>
            {{ __('Ghi nhớ đăng nhập trên máy này') }}
        </label>
        <x-nut-chinh>{{ __('Đăng nhập') }}</x-nut-chinh>
    </form>
    <p class="mt-6 text-body">
        <a href="{{ route('password.request') }}" class="font-semibold text-tim underline underline-offset-4 hover:text-tim-strong">{{ __('Quên mật khẩu?') }}</a>
    </p>
    <p class="mt-3 text-body text-ink-2">
        {{ __('Chưa có tài khoản?') }}
        <a href="{{ route('register') }}" class="font-semibold text-tim underline underline-offset-4 hover:text-tim-strong">{{ __('Đăng ký') }}</a>
    </p>
@endsection
