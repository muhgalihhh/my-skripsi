@props(['type' => 'default'])
@php
    $variants = [
        'success' => 'bg-emerald-100 text-emerald-700',
        'info'    => 'bg-blue-100 text-blue-700',
        'warning' => 'bg-yellow-100 text-yellow-700',
        'error'   => 'bg-red-100 text-red-700',
        'default' => 'bg-gray-100 text-gray-600',
    ];
    $t = is_string($type) ? $type : 'default';
@endphp
<span {{ $attributes->merge(['class' => "rounded-full px-2 py-0.5 text-xs font-semibold " . ($variants[$t] ?? $variants['default'])]) }}>
    {{ $slot }}
</span>
