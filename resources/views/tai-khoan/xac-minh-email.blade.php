@extends('layouts.tai-khoan')

@section('tieu-de', __('Xác minh email'))

@section('noi-dung')
    <p class="mt-3 text-body">
        {{ __('Mình đã gửi link xác minh tới') }} <strong class="font-semibold">{{ auth()->user()?->email }}</strong>.
        {{ __('Bấm link trong thư để bắt đầu học. Thư có thể tới sau một hai phút; không thấy thì xem cả mục Thư rác.') }}
    </p>
    @if (session('status') === 'verification-link-sent')
        <p role="status" class="mt-4 rounded-md border border-line bg-tim-tint px-4 py-3 text-body">{{ __('Đã gửi lại thư xác minh.') }}</p>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-nut-chinh>{{ __('Gửi lại thư xác minh') }}</x-nut-chinh>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-sheet px-4 text-label text-tim transition-colors duration-200 hover:bg-tim-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">{{ __('Đăng xuất, dùng email khác') }}</button>
    </form>
@endsection
