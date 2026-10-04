@props(['color' => 'gray'])
@php
    $styles = [
        'gray'    => 'bg-gray-100 text-muted',
        'brand'   => 'bg-brand-soft text-brand',
        'income'  => 'bg-income-soft text-income',
        'expense' => 'bg-expense-soft text-expense',
    ][$color];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium $styles"]) }}>{{ $slot }}</span>