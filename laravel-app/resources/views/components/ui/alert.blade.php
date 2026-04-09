@props(['type' => 'info', 'message' => ''])
@php
    $alertCfg = match ($type) {
        'success' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'icon' => 'check-circle'],
        'error'   => ['bg' => 'bg-red-50',     'border' => 'border-red-200',     'text' => 'text-red-800',     'icon' => 'x-circle'],
        'warning' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'text' => 'text-amber-800',   'icon' => 'exclamation-triangle'],
        default   => ['bg' => 'bg-blue-50',    'border' => 'border-blue-200',    'text' => 'text-blue-800',    'icon' => 'information-circle'],
    };
@endphp
<div {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border p-4 {$alertCfg['bg']} {$alertCfg['border']}"]) }}>
    <x-app.icon :name="$alertCfg['icon']" class="mt-0.5 h-5 w-5 flex-shrink-0 {{ $alertCfg['text'] }}" />
    <div class="text-sm font-medium {{ $alertCfg['text'] }}">
        @if($message) {{ $message }} @else {{ $slot }} @endif
    </div>
</div>
