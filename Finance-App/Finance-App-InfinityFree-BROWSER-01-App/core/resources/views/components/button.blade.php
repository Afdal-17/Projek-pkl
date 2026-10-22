@props(['variant' => 'dark', 'href' => null])
@php
    $styles = [
        'dark' => 'bg-dark text-white hover:bg-black',
        'outline' => 'bg-white text-ink border border-line hover:bg-gray-50',
        'brand' => 'bg-brand text-white hover:bg-indigo-700',
        'danger' => 'bg-expense text-white hover:bg-red-700',
    ][$variant];
    $classes = "inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition $styles";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>{{ $slot }}</button>
@endif