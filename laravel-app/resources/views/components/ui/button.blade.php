@props(['type' => 'button', 'variant' => 'primary', 'size' => 'md', 'href' => null])
@php
    $baseClasses = 'inline-flex items-center justify-center gap-1.5 font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed';
    
    $sizes = [
        'sm' => 'rounded-md px-3 py-1.5 text-xs',
        'md' => 'rounded-lg px-4 py-2 text-sm shadow-sm',
        'lg' => 'rounded-xl px-5 py-2.5 text-base shadow-sm',
        'icon' => 'rounded-lg p-2 shadow-sm',
        'icon-sm' => 'rounded-md p-1.5',
    ];

    $variants = [
        'primary' => 'bg-unsoed-blue-800 text-white hover:bg-unsoed-blue-900 focus:ring-2 focus:ring-unsoed-blue-500 focus:ring-offset-2',
        'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-gray-300 focus:ring-offset-2',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2',
        'warning' => 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-2 focus:ring-amber-400 focus:ring-offset-2',
        'info' => 'bg-sky-500 text-white hover:bg-sky-600 focus:ring-2 focus:ring-sky-400 focus:ring-offset-2',
        'light' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-2 focus:ring-gray-200 focus:ring-offset-2',
        'ghost' => 'bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900',
        'ghost-danger' => 'bg-transparent text-red-600 hover:bg-red-50 hover:text-red-700',
        'ghost-primary' => 'bg-transparent text-unsoed-blue-700 hover:bg-unsoed-blue-50 hover:text-unsoed-blue-800',
    ];
    
    $s = is_string($size) ? $size : 'md';
    $v = is_string($variant) ? $variant : 'primary';
    $classes = $baseClasses . ' ' . ($sizes[$s] ?? $sizes['md']) . ' ' . ($variants[$v] ?? $variants['primary']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
