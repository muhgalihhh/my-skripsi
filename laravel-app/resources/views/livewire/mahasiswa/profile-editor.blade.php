<div>
    @section('page-title', 'Profil Akademik')

    <div class="mx-auto max-w-3xl space-y-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-bold text-gray-900">Profil Akademik</h1>
            <p class="mt-1 text-sm text-gray-500">Manajemen kredensial autentikasi sistem informasi.</p>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nama Afiliasi</p>
                    <p class="mt-1 text-sm font-medium text-gray-800">{{ $name }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Posel Elektronik</p>
                    <p class="mt-1 text-sm font-medium text-gray-800">{{ $email }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                @if ($googleLinked)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 font-semibold text-emerald-700">Integrasi Google Terotorisasi</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 font-semibold text-amber-700">Integrasi Google Belum Terotorisasi</span>
                @endif

                @if ($hasLocalPassword)
                    <span class="inline-flex items-center rounded-full bg-unsoed-blue-100 px-2.5 py-1 font-semibold text-unsoed-blue-700">Akses Kredensial Pribadi Berjalan</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-semibold text-gray-700">Akses Kredensial Pribadi Ditangguhkan</span>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Konfigurasi Kata Sandi Sistem</h2>
            <p class="mt-1 text-sm text-gray-500">Hapus kata sandi jika bermaksud mendeaktivasi login mandiri. Autentikasi berbasis Google tetap dapat digunakan.</p>

            <form wire:submit="savePasswordSettings" class="mt-4 space-y-4">
                @if ($hasLocalPassword)
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Kata Sandi Berjalan</label>
                        <input wire:model="currentPassword" type="password"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            placeholder="Input rekam sandi yang berjalan">
                        @error('currentPassword')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Kata Sandi Baru <span class="normal-case text-gray-400">(Bebas diisi/opsional)</span></label>
                    <input wire:model="newPassword" type="password"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                        placeholder="Standar prosedur minimal 8 pemenggalan">
                    @error('newPassword')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                @if ($newPassword)
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Verifikasi Ulang Kata Sandi Baru</label>
                        <input wire:model="newPasswordConfirmation" type="password"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            placeholder="Tuliskan kembali kata sandi">
                    </div>
                @endif

                <label class="flex items-start gap-2 text-xs text-gray-600">
                    <input wire:model="clearPassword" type="checkbox" class="mt-0.5 rounded border-gray-300 text-unsoed-blue-600 focus:ring-unsoed-blue-500">
                    <span>Mendeaktivasi kata sandi independen (menonaktifkan proses masuk melalui kredensial form tunggal).</span>
                </label>

                <div class="pt-2">
                    <x-ui.button type="submit" variant="primary"
                        class="!rounded-xl !py-2.5"
                        wire:loading.attr="disabled">
                        <x-app.icon name="arrow-path" wire:loading wire:target="savePasswordSettings" class="mr-2 h-4 w-4 animate-spin" />
                        Simpan Modifikasi Akses
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
