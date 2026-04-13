{{--
    Reusable Confirm Modal Component
    Props:
    - wireModel: Livewire entangle property name
    - title: modal title
    - message: body message
    - subtext: optional extra text
    - confirmLabel: confirm button label
    - confirmWire: wire:click action for confirm button
    - confirmTarget: wire:target for loading state
    - type: danger | warning | info
    - closeWire: wire:click action for cancel/close
--}}
@props([
    'wireModel' => 'showConfirm',
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin?',
    'subtext' => null,
    'confirmLabel' => 'Ya, Lanjutkan',
    'confirmWire' => '',
    'confirmTarget' => null,
    'type' => 'danger',
    'closeWire' => '',
])

@php
    $confirmTarget = $confirmTarget ?? $confirmWire;
@endphp

<div x-data="{ open: @entangle($wireModel).live }" x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" role="dialog"
    aria-modal="true">
    {{-- Backdrop --}}
    @if ($closeWire)
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="{{ $closeWire }}"></div>
    @else
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
    @endif

    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90" x-on:click.stop
            class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex flex-col items-center text-center">
                @if ($type === 'warning')
                    <div class="mb-4 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-100">
                        <x-app.icon variant="o" name="exclamation-triangle" class="h-7 w-7 text-amber-500" />
                    </div>
                @elseif ($type === 'info')
                    <div class="mb-4 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-blue-100">
                        <x-app.icon variant="o" name="information-circle" class="h-7 w-7 text-blue-500" />
                    </div>
                @else
                    <div class="mb-4 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <x-app.icon variant="o" name="x-circle" class="h-7 w-7 text-red-500" />
                    </div>
                @endif

                <h3 class="mb-1.5 text-lg font-bold text-gray-900">{{ $title }}</h3>
                <p class="text-sm leading-relaxed text-gray-500">{{ $message }}</p>

                @if ($subtext)
                    <p class="mt-1 text-xs text-gray-400">{{ $subtext }}</p>
                @endif

                @if ($slot->isNotEmpty())
                    <div class="mt-2 w-full">{{ $slot }}</div>
                @endif

                <div class="mt-6 flex w-full gap-3">
                    @if ($closeWire)
                        <x-ui.button wire:click="{{ $closeWire }}" variant="light" class="flex-1 !rounded-xl !py-2.5">
                            Batal
                        </x-ui.button>
                    @else
                        <x-ui.button x-on:click="open = false" variant="light" class="flex-1 !rounded-xl !py-2.5">
                            Batal
                        </x-ui.button>
                    @endif

                    @if ($confirmWire)
                        @if ($type === 'warning')
                            <x-ui.button variant="warning" wire:click="{{ $confirmWire }}" wire:loading.attr="disabled"
                                wire:target="{{ $confirmTarget }}" class="flex-1 !rounded-xl !py-2.5">
                                <x-app.icon name="arrow-path" wire:loading wire:target="{{ $confirmTarget }}"
                                    class="h-4 w-4 animate-spin" />
                                <span wire:loading.remove wire:target="{{ $confirmTarget }}">{{ $confirmLabel }}</span>
                                <span wire:loading wire:target="{{ $confirmTarget }}" class="sr-only">Memproses...</span>
                            </x-ui.button>
                        @elseif ($type === 'info')
                            <x-ui.button variant="info" wire:click="{{ $confirmWire }}" wire:loading.attr="disabled"
                                wire:target="{{ $confirmTarget }}" class="flex-1 !rounded-xl !py-2.5">
                                <x-app.icon name="arrow-path" wire:loading wire:target="{{ $confirmTarget }}"
                                    class="h-4 w-4 animate-spin" />
                                <span wire:loading.remove wire:target="{{ $confirmTarget }}">{{ $confirmLabel }}</span>
                                <span wire:loading wire:target="{{ $confirmTarget }}" class="sr-only">Memproses...</span>
                            </x-ui.button>
                        @else
                            <x-ui.button variant="danger" wire:click="{{ $confirmWire }}" wire:loading.attr="disabled"
                                wire:target="{{ $confirmTarget }}" class="flex-1 !rounded-xl !py-2.5">
                                <x-app.icon name="arrow-path" wire:loading wire:target="{{ $confirmTarget }}"
                                    class="h-4 w-4 animate-spin" />
                                <span wire:loading.remove wire:target="{{ $confirmTarget }}">{{ $confirmLabel }}</span>
                                <span wire:loading wire:target="{{ $confirmTarget }}" class="sr-only">Memproses...</span>
                            </x-ui.button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
