@props(['name', 'label', 'type' => 'text', 'ghichu' => null, 'value' => null])
@php
    $loi = $errors->first($name);
    $moTa = $loi ? $name.'-loi' : ($ghichu ? $name.'-ghi-chu' : null);
@endphp
<div class="mt-5">
    <label for="{{ $name }}" class="block text-label text-ink">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($loi) aria-invalid="true" @endif
        @if ($moTa) aria-describedby="{{ $moTa }}" @endif
        {{ $attributes->class([
            'mt-1.5 block min-h-11 w-full rounded-md border bg-paper px-3 py-2 text-body text-ink',
            'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-focus',
            'border-danger' => $loi,
            'border-line-strong' => ! $loi,
        ]) }}>
    @if ($loi)
        <p id="{{ $name }}-loi" class="mt-1.5 text-label text-danger">{{ $loi }}</p>
    @elseif ($ghichu)
        <p id="{{ $name }}-ghi-chu" class="mt-1.5 text-label font-normal text-ink-2">{{ $ghichu }}</p>
    @endif
</div>
