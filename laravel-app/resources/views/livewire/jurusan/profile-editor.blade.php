<div>
    @section('page-title', 'Edit Profil')

    <div class="max-w-2xl mx-auto space-y-6">

        {{-- ── Page Header ──────────────────────────────────── --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Profil</h1>
            <p class="mt-1 text-sm text-gray-500">Kelola informasi akun dan keamanan profil Anda</p>
        </div>

        {{-- ── Avatar Card ──────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-5">
                <div
                    class="w-16 h-16 rounded-2xl bg-gradient-to-br from-unsoed-blue-500 to-unsoed-blue-700 flex items-center justify-center shadow-lg">
                    <span
                        class="text-2xl font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900">{{ auth()->user()->name }}</p>
                    <p class="text-sm text-gray-500">{{ auth()->user()->email }}</p>
                    <span
                        class="inline-flex items-center mt-1 px-2.5 py-0.5 rounded-full text-xs font-semibold
                        {{ auth()->user()->role === 'jurusan' ? 'bg-unsoed-blue-100 text-unsoed-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                        {{ ucfirst(auth()->user()->role) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ── Profile Info Card ────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-6 py-4 flex items-center gap-2.5">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h2 class="text-base font-bold text-white">Informasi Profil</h2>
            </div>
            <form wire:submit="saveProfile" class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Nama Lengkap
                        <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                    @error('name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Alamat Email
                        <span class="text-red-500">*</span></label>
                    <input wire:model="email" type="email"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                    @error('email')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-5 py-2.5 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white text-sm font-semibold rounded-xl transition flex items-center gap-2 shadow-sm"
                        wire:loading.attr="disabled" wire:target="saveProfile">
                        <svg wire:loading wire:target="saveProfile" class="animate-spin w-4 h-4" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="saveProfile" class="w-4 h-4" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan Profil
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Change Password Card ─────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-amber-500 to-amber-400 px-6 py-4 flex items-center gap-2.5">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 class="text-base font-bold text-white">Ubah Password</h2>
            </div>
            <form wire:submit="changePassword" class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password
                        Saat Ini <span class="text-red-500">*</span></label>
                    <input wire:model="currentPassword" type="password" placeholder="Masukkan password saat ini"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                    @error('currentPassword')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password
                        Baru <span class="text-red-500">*</span></label>
                    <input wire:model="newPassword" type="password" placeholder="Min. 8 karakter"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                    @error('newPassword')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Konfirmasi
                        Password Baru <span class="text-red-500">*</span></label>
                    <input wire:model="newPasswordConfirmation" type="password" placeholder="Ulangi password baru"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition flex items-center gap-2 shadow-sm"
                        wire:loading.attr="disabled" wire:target="changePassword">
                        <svg wire:loading wire:target="changePassword" class="animate-spin w-4 h-4" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="changePassword" class="w-4 h-4" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Ubah Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
