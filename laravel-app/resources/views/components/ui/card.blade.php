@props(['title' => '', 'description' => ''])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white shadow-sm']) }}>
    @if($title || $description)
    <div class="border-b border-gray-200 px-5 py-4">
        @if($title)<h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>@endif
        @if($description)<p class="mt-0.5 text-xs text-gray-500">{{ $description }}</p>@endif
    </div>
    @endif
    <div class="{{ $attributes->has('no-padding') ? '' : 'px-5 py-4' }}">
        {{ $slot }}
    </div>
</div>
