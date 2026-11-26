@extends('layouts.tai-khoan')

@section('tieu-de', __('Xác nhận mật khẩu'))

@section('noi-dung')
    <p class="mt-3 text-body text-ink-2">{{ __('Việc này cần nhập lại mật khẩu cho chắc.') }}</p>
    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-4" novalidate>
        @csrf
        <x-o-nhap name="password" type="password" :label="__('Mật khẩu')" autocomplete="current-password" autofocus required />
        <x-nut-chinh>{{ __('Xác nhận') }}</x-nut-chinh>
    </form>
@endsection
