@props(['type' => 'info', 'message' => ''])
@php
    $alertCfg = match ($type) {
        'success' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'error'   => ['bg' => 'bg-red-50',     'border' => 'border-red-200',     'text' => 'text-red-800',     'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'warning' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'text' => 'text-amber-800',   'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        default   => ['bg' => 'bg-blue-50',    'border' => 'border-blue-200',    'text' => 'text-blue-800',    'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    };
@endphp
<div {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border p-4 {$alertCfg['bg']} {$alertCfg['border']}"]) }}>
    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 {{ $alertCfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $alertCfg['icon'] }}" />
    </svg>
    <div class="text-sm font-medium {{ $alertCfg['text'] }}">
        @if($message) {{ $message }} @else {{ $slot }} @endif
    </div>
</div>
