@props(['type' => 'button', 'variant' => 'primary'])
@php
    $baseClasses = 'inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm disabled:opacity-50 transition-colors';
    $variants = [
        'primary' => 'bg-unsoed-blue-800 text-white hover:bg-unsoed-blue-900',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
    ];
    $v = is_string($variant) ? $variant : 'primary';
    $classes = $baseClasses . ' ' . ($variants[$v] ?? $variants['primary']);
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
