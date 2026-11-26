@extends('layouts.tai-khoan')

@section('tieu-de', __('Đặt mật khẩu mới'))

@section('noi-dung')
    <form method="POST" action="{{ route('password.update') }}" class="mt-6" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-o-nhap name="email" type="email" :label="__('Email')" :value="$email" autocomplete="email" required />
        @error('token')
            <p class="mt-3 text-label text-danger">{{ $message }}</p>
        @enderror
        <x-o-nhap name="password" type="password" :label="__('Mật khẩu mới')" autocomplete="new-password" autofocus required
            :ghichu="__('Ít nhất 8 ký tự, có cả chữ và số.')" />
        <x-o-nhap name="password_confirmation" type="password" :label="__('Nhập lại mật khẩu mới')" autocomplete="new-password" required />
        <x-nut-chinh>{{ __('Đặt mật khẩu mới') }}</x-nut-chinh>
    </form>
@endsection
