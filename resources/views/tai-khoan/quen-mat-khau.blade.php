@extends('layouts.tai-khoan')

@section('tieu-de', __('Quên mật khẩu'))

@section('noi-dung')
    <p class="mt-3 text-body text-ink-2">{{ __('Nhập email đã đăng ký, mình gửi link để đặt mật khẩu mới.') }}</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-4" novalidate>
        @csrf
        <x-o-nhap name="email" type="email" :label="__('Email')" autocomplete="email" autofocus required />
        <x-nut-chinh>{{ __('Gửi link đặt lại') }}</x-nut-chinh>
    </form>
    <p class="mt-6 text-body">
        <a href="{{ route('login') }}" class="font-semibold text-tim underline underline-offset-4 hover:text-tim-strong">{{ __('Về trang đăng nhập') }}</a>
    </p>
@endsection
