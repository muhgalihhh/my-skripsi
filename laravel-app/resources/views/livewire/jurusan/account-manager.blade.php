<div>
    @section('page-title', 'Manajemen Akun')

    <div class="space-y-5">

        {{-- ── Page Header ──────────────────────────────────── --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Akun</h1>
                <p class="mt-1 text-sm text-gray-500">Kelola akun pengguna yang terdaftar di sistem</p>
            </div>
            <button wire:click="openAdd"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Akun
            </button>
        </div>

        {{-- ── Search & Filter ──────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama atau email..."
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                </div>
                <select wire:model.live="roleFilter"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                    <option value="">Semua Role</option>
                    <option value="jurusan">Jurusan</option>
                    <option value="mahasiswa">Mahasiswa</option>
                </select>
            </div>
        </div>

        {{-- ── Users Table ──────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

            {{-- Bulk Action Bar --}}
            @if (count($selectedIds) > 0)
                <div x-data x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="bg-red-50 border-b border-red-200 px-6 py-3 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-semibold text-red-700">{{ count($selectedIds) }} akun dipilih</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button wire:click="$set('selectedIds', [])"
                            class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Batal Pilih
                        </button>
                        <button wire:click="openBulkDeleteConfirm"
                            class="px-4 py-1.5 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition flex items-center shadow-sm">
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Hapus {{ count($selectedIds) }} Akun
                        </button>
                    </div>
                </div>
            @endif

            @if ($users->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-3 px-4 w-10">
                                    <input type="checkbox" wire:model.live="selectAll" wire:change="toggleSelectAll"
                                        class="rounded border-gray-300 text-red-500 focus:ring-red-400 cursor-pointer">
                                </th>
                                <th
                                    class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide w-10">
                                    #</th>
                                <th
                                    class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Nama</th>
                                <th
                                    class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Email</th>
                                <th
                                    class="text-center py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide w-28">
                                    Role</th>
                                <th
                                    class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide w-36">
                                    Terdaftar</th>
                                <th
                                    class="text-center py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide w-24">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($users as $index => $user)
                                @php $isSelf = $user->id === auth()->id(); @endphp
                                <tr
                                    class="hover:bg-blue-50/30 transition-colors {{ $isSelf ? 'bg-blue-50/20' : '' }} {{ in_array((string) $user->id, $selectedIds) ? 'bg-red-50/40' : '' }}">
                                    <td class="py-3 px-4">
                                        @if (!$isSelf)
                                            <input type="checkbox" wire:model.live="selectedIds"
                                                value="{{ $user->id }}"
                                                class="rounded border-gray-300 text-red-500 focus:ring-red-400 cursor-pointer">
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-gray-400 text-xs font-mono">
                                        {{ $users->firstItem() + $index }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0
                                                {{ $user->role === 'jurusan' ? 'bg-unsoed-blue-500' : 'bg-emerald-500' }}">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800 text-sm">{{ $user->name }}</p>
                                                @if ($isSelf)
                                                    <p class="text-xs text-unsoed-blue-500 font-medium">(Anda)</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600">{{ $user->email }}</td>
                                    <td class="py-3 px-4 text-center">
                                        @if ($user->role === 'jurusan')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-unsoed-blue-100 text-unsoed-blue-700">Jurusan</span>
                                        @elseif ($user->role === 'mahasiswa')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Mahasiswa</span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">{{ $user->role }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-gray-500 text-xs">
                                        {{ $user->created_at->format('d M Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button wire:click="openEdit({{ $user->id }})"
                                                class="p-1.5 text-amber-600 hover:bg-amber-100 rounded-lg transition"
                                                title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button wire:click="confirmDelete({{ $user->id }})"
                                                @disabled($isSelf)
                                                class="p-1.5 rounded-lg transition {{ $isSelf ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:bg-red-100' }}"
                                                title="{{ $isSelf ? 'Tidak bisa menghapus akun sendiri' : 'Hapus' }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between">
                    <p class="text-xs text-gray-500">Total {{ $users->total() }} akun</p>
                    {{ $users->links() }}
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <svg class="w-14 h-14 mb-3 text-gray-200" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <p class="text-sm font-semibold text-gray-500">Tidak ada akun ditemukan</p>
                    <p class="text-xs mt-1">Coba ubah filter atau tambah akun baru</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         MODAL: Tambah Akun
    ═════════════════════════════════════════════════════ --}}
    <div x-data="{ open: @entangle('showAddModal').live }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog"
        aria-modal="true">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeAdd()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                <div
                    class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white">Tambah Akun Baru</h3>
                    </div>
                    <button wire:click="closeAdd"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveAdd" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Nama
                            Lengkap <span class="text-red-500">*</span></label>
                        <input wire:model="addName" type="text" placeholder="Masukkan nama lengkap"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                        @error('addName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Email
                            <span class="text-red-500">*</span></label>
                        <input wire:model="addEmail" type="email" placeholder="nama@example.com"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                        @error('addEmail')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Role
                            <span class="text-red-500">*</span></label>
                        <select wire:model="addRole"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                            <option value="">Pilih role...</option>
                            <option value="jurusan">Jurusan</option>
                            <option value="mahasiswa">Mahasiswa</option>
                        </select>
                        @error('addRole')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password
                            <span class="text-red-500">*</span></label>
                        <input wire:model="addPassword" type="password" placeholder="Min. 8 karakter"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                        @error('addPassword')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Konfirmasi
                            Password <span class="text-red-500">*</span></label>
                        <input wire:model="addPasswordConfirmation" type="password" placeholder="Ulangi password"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeAdd"
                            class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition">
                            Batal
                        </button>
                        <button type="submit"
                            class="flex-1 px-4 py-2.5 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white text-sm font-semibold rounded-xl transition flex items-center justify-center gap-2 shadow-sm"
                            wire:loading.attr="disabled" wire:target="saveAdd">
                            <svg wire:loading wire:target="saveAdd" class="animate-spin w-4 h-4" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Tambah Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         MODAL: Edit Akun
    ═════════════════════════════════════════════════════ --}}
    <div x-data="{ open: @entangle('showEditModal').live }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog"
        aria-modal="true">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeEdit()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                <div class="bg-gradient-to-r from-amber-500 to-amber-400 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white">Edit Akun</h3>
                    </div>
                    <button wire:click="closeEdit"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveEdit" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Nama
                            Lengkap <span class="text-red-500">*</span></label>
                        <input wire:model="editName" type="text"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                        @error('editName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Email
                            <span class="text-red-500">*</span></label>
                        <input wire:model="editEmail" type="email"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                        @error('editEmail')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Role
                            <span class="text-red-500">*</span></label>
                        <select wire:model="editRole"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            <option value="jurusan">Jurusan</option>
                            <option value="mahasiswa">Mahasiswa</option>
                        </select>
                        @error('editRole')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password
                            Baru <span class="text-gray-300 normal-case font-normal">(kosongkan jika tidak
                                diubah)</span></label>
                        <input wire:model="editPassword" type="password" placeholder="Min. 8 karakter"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                        @error('editPassword')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    @if ($editPassword)
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Konfirmasi
                                Password Baru</label>
                            <input wire:model="editPasswordConfirmation" type="password"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                        </div>
                    @endif
                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeEdit"
                            class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition">
                            Batal
                        </button>
                        <button type="submit"
                            class="flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition flex items-center justify-center gap-2 shadow-sm"
                            wire:loading.attr="disabled" wire:target="saveEdit">
                            <svg wire:loading wire:target="saveEdit" class="animate-spin w-4 h-4" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         MODAL: Konfirmasi Hapus Akun
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showDeleteConfirm" type="danger" title="Hapus Akun?" :message="$deleteTargetName
        ? 'Akun ' . $deleteTargetName . ' akan dihapus secara permanen.'
        : 'Akun ini akan dihapus secara permanen dan tidak dapat dikembalikan.'"
        confirmLabel="Ya, Hapus" confirmWire="deleteUser" closeWire="closeDeleteConfirm" />

    {{-- ════════════════════════════════════════════════════
         MODAL: Konfirmasi Bulk Hapus Akun
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showBulkDeleteConfirm" type="danger" title="Hapus {{ count($selectedIds) }} Akun?"
        message="Akun yang dipilih akan dihapus secara permanen. Akun Anda sendiri tidak akan terpengaruh."
        confirmLabel="Ya, Hapus Semua" confirmWire="bulkDelete" closeWire="closeBulkDeleteConfirm" />

</div>
