{{--    Reusable Confirm Modal Component    Props:      - wireModel    : Livewire entangle property name, e.g. "showDeleteConfirm"  (default: "showConfirm")      - title        : Modal title                                                 (default: "Konfirmasi")      - message      : Body message text                                           (default: "Apakah Anda yakin?")      - subtext      : Optional extra text below message                          (default: null)      - confirmLabel : Label for confirm button                                   (default: "Ya, Lanjutkan")      - confirmWire  : wire:click action for confirm button                       (required)      - confirmTarget: wire:target for loading spinner                            (optional, same as confirmWire)      - type         : "danger" | "warning" | "info"                             (default: "danger")      - closeWire    : wire:click action for cancel/close button                  (optional)    PENTING: Semua Tailwind class ditulis LANGSUNG (bukan dinamis dari PHP variable)    agar Tailwind CSS v4 bisa mendeteksi saat build / purge. --}}@props([
    'wireModel' => 'showConfirm',
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin?',
    'subtext' => null,
    'confirmLabel' => 'Ya, Lanjutkan',
    'confirmWire' => '',
    'confirmTarget' => null,
    'type' => 'danger',
    'closeWire' => '',
])@php $confirmTarget = $confirmTarget ?? $confirmWire;@endphp<div
    x-data="{ open: @entangle($wireModel).live }" x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" role="dialog"
    aria-modal="true"> {{-- Backdrop --}} <div x-show="open" x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm"
        @if ($closeWire) wire:click="{{ $closeWire }}" @else @click="open = false" @endif> </div>
    {{-- Panel Container --}} <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90" @click.stop
            class="relative bg-white rounded-2xl shadow-2xl p-6" style="width: 100%; max-width: 24rem;">
            <div class="flex flex-col items-center text-center"> {{-- Icon — class langsung per type --}} @if ($type === 'warning')
                    <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mb-4 shrink-0">
                        <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg> </div>
                @elseif ($type === 'info')
                    <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mb-4 shrink-0"> <svg
                            class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg> </div>
                @else
                    {{-- danger (default) --}} <div
                        class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mb-4 shrink-0"> <svg
                            class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg> </div>
                    @endif {{-- Title --}} <h3 class="text-lg font-bold text-gray-900 mb-1.5">{{ $title }}
                    </h3> {{-- Message --}} <p class="text-sm text-gray-500 leading-relaxed">{{ $message }}</p>
                    {{-- Subtext --}} @if ($subtext)
                        <p class="text-xs text-gray-400 mt-1">{{ $subtext }}</p>
                        @endif {{-- Custom slot content --}} @if ($slot->isNotEmpty())
                            <div class="mt-2 w-full">{{ $slot }}</div>
                            @endif {{-- Buttons --}} <div class="flex gap-3 w-full mt-6"> {{-- Cancel --}}
                                <button
                                    @if ($closeWire) wire:click="{{ $closeWire }}" @else @click="open = false" @endif
                                    type="button"
                                    class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition">
                                    Batal </button> {{-- Confirm — class langsung per type --}} @if ($confirmWire)
                                    @if ($type === 'warning')
                                        <button wire:click="{{ $confirmWire }}" wire:loading.attr="disabled"
                                            wire:target="{{ $confirmTarget }}" type="button"
                                            class="flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-60">
                                            <svg wire:loading wire:target="{{ $confirmTarget }}"
                                                class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg> <span wire:loading.remove
                                                wire:target="{{ $confirmTarget }}">{{ $confirmLabel }}</span> <span
                                                wire:loading wire:target="{{ $confirmTarget }}"
                                                class="sr-only">Memproses...</span> </button>
                                    @elseif ($type === 'info')
                                        <button wire:click="{{ $confirmWire }}" wire:loading.attr="disabled"
                                            wire:target="{{ $confirmTarget }}" type="button"
                                            class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-60">
                                            <svg wire:loading wire:target="{{ $confirmTarget }}"
                                                class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <span wire:loading.remove
                                                wire:target="{{ $confirmTarget }}">{{ $confirmLabel }}</span>
                                            <span wire:loading wire:target="{{ $confirmTarget }}"
                                                class="sr-only">Memproses...</span>
                                        </button>
                                    @else
                                        {{-- danger (default) --}}
                                        <button wire:click="{{ $confirmWire }}" wire:loading.attr="disabled"
                                            wire:target="{{ $confirmTarget }}" type="button"
                                            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-60">
                                            <svg wire:loading wire:target="{{ $confirmTarget }}"
                                                class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <span wire:loading.remove
                                                wire:target="{{ $confirmTarget }}">{{ $confirmLabel }}</span>
                                            <span wire:loading wire:target="{{ $confirmTarget }}"
                                                class="sr-only">Memproses...</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
            </div>
        </div>
    </div>
</div>
