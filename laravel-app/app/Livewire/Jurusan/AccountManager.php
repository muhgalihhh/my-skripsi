<?php

namespace App\Livewire\Jurusan;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.jurusan')]
#[Title('Manajemen Akun')]
class AccountManager extends Component
{
  use WithPagination;

  public string $search = '';
  public string $roleFilter = '';
  public int $perPage = 15;

  // Add User Modal
  public bool $showAddModal = false;
  public string $addName = '';
  public string $addEmail = '';
  public string $addPassword = '';
  public string $addPasswordConfirmation = '';
  public string $addRole = 'jurusan';

  // Edit User Modal
  public bool $showEditModal = false;
  public ?int $editUserId = null;
  public string $editName = '';
  public string $editEmail = '';
  public string $editRole = '';
  public bool $editGoogleLinked = false;
  public bool $editClearPassword = false;
  public string $editPassword = '';
  public string $editPasswordConfirmation = '';

  // Delete Confirm Modal
  public bool $showDeleteConfirm = false;
  public ?int $deleteTargetId = null;
  public string $deleteTargetName = '';

  // Bulk Delete
  public array $selectedIds = [];
  public bool $selectAll = false;
  public bool $showBulkDeleteConfirm = false;

  public function updatingSearch(): void
  {
    $this->resetPage();
  }

  public function updatingRoleFilter(): void
  {
    $this->resetPage();
  }

  public function updatedAddRole(): void
  {
    $this->addPassword = '';
    $this->addPasswordConfirmation = '';
    $this->resetValidation();
  }

  public function updatedEditRole(): void
  {
    $this->editPassword = '';
    $this->editPasswordConfirmation = '';
    $this->editClearPassword = false;
    $this->resetValidation();
  }

  // ── Add ─────────────────────────────────────────────────
  public function openAdd(): void
  {
    $this->resetAddForm();
    $this->showAddModal = true;
  }

  public function closeAdd(): void
  {
    $this->showAddModal = false;
    $this->resetAddForm();
  }

  private function resetAddForm(): void
  {
    $this->addName = '';
    $this->addEmail = '';
    $this->addPassword = '';
    $this->addPasswordConfirmation = '';
    $this->addRole = 'jurusan';
    $this->resetValidation();
  }

  public function saveAdd(): void
  {
    $this->validate([
      'addName' => 'required|string|min:3|max:100',
      'addEmail' => 'required|email|unique:users,email',
      'addRole' => 'required|in:jurusan,mahasiswa',
    ], [
      'addName.required' => 'Nama wajib diisi.',
      'addEmail.unique' => 'Email sudah terdaftar.',
    ]);

    if ($this->addRole === 'jurusan') {
      $this->validate([
        'addPassword' => 'required|min:8|same:addPasswordConfirmation',
      ], [
        'addPassword.min' => 'Password minimal 8 karakter.',
        'addPassword.same' => 'Konfirmasi password tidak cocok.',
      ]);
    } elseif (trim($this->addPassword) !== '') {
      $this->validate([
        'addPassword' => 'min:8|same:addPasswordConfirmation',
      ], [
        'addPassword.min' => 'Password minimal 8 karakter.',
        'addPassword.same' => 'Konfirmasi password tidak cocok.',
      ]);
    }

    if ($this->addRole === 'mahasiswa' && !$this->isAllowedMahasiswaEmail($this->addEmail)) {
      $this->addError('addEmail', 'Email mahasiswa wajib menggunakan domain ' . $this->mahasiswaDomainLabel() . '.');
      return;
    }

    $newUserRole = $this->addRole;

    $password = null;
    if ($newUserRole === 'jurusan') {
      $password = Hash::make($this->addPassword);
    } elseif (trim($this->addPassword) !== '') {
      $password = Hash::make($this->addPassword);
    }

    User::create([
      'name' => $this->addName,
      'email' => Str::lower(trim($this->addEmail)),
      'password' => $password,
      'role' => $newUserRole,
    ]);

    $this->closeAdd();
    $this->dispatch(
      'toast',
      type: 'success',
      message: $newUserRole === 'mahasiswa'
        ? (trim($this->addPassword) !== ''
          ? 'Akun mahasiswa ditambahkan. Login via Google OAuth atau form password tersedia.'
          : 'Akun mahasiswa ditambahkan. Login form nonaktif sampai password diatur.')
        : 'Akun jurusan berhasil ditambahkan.'
    );
  }

  // ── Edit ─────────────────────────────────────────────────
  public function openEdit(int $id): void
  {
    $user = User::find($id);
    if (!$user)
      return;

    $this->editUserId = $id;
    $this->editName = $user->name;
    $this->editEmail = $user->email;
    $this->editRole = $user->role;
    $this->editGoogleLinked = filled($user->google_id);
    $this->editClearPassword = false;
    $this->editPassword = '';
    $this->editPasswordConfirmation = '';
    $this->resetValidation();
    $this->showEditModal = true;
  }

  public function closeEdit(): void
  {
    $this->showEditModal = false;
    $this->editUserId = null;
    $this->editName = '';
    $this->editEmail = '';
    $this->editRole = '';
    $this->editGoogleLinked = false;
    $this->editClearPassword = false;
    $this->editPassword = '';
    $this->editPasswordConfirmation = '';
    $this->resetValidation();
  }

  public function saveEdit(): void
  {
    $this->validate([
      'editName' => 'required|string|min:3|max:100',
      'editEmail' => "required|email|unique:users,email,{$this->editUserId}",
      'editRole' => 'required|in:jurusan,mahasiswa',
    ], [
      'editName.required' => 'Nama wajib diisi.',
      'editEmail.unique' => 'Email sudah dipakai akun lain.',
    ]);

    if ($this->editRole === 'jurusan') {
      $this->validate([
        'editPassword' => 'nullable|min:8',
      ], [
        'editPassword.min' => 'Password baru minimal 8 karakter.',
      ]);
    } elseif (trim($this->editPassword) !== '') {
      $this->validate([
        'editPassword' => 'min:8|same:editPasswordConfirmation',
      ], [
        'editPassword.min' => 'Password baru minimal 8 karakter.',
        'editPassword.same' => 'Konfirmasi password baru tidak cocok.',
      ]);
    }

    $user = User::find($this->editUserId);
    if (!$user)
      return;

    // Prevent self lockout: a jurusan user must not demote their own account.
    if ((int) $user->id === (int) auth()->id() && $this->editRole !== 'jurusan') {
      $this->addError('editRole', 'Akun Anda harus tetap memiliki role Jurusan.');
      $this->dispatch('toast', type: 'error', message: 'Role akun sendiri tidak boleh diubah dari Jurusan.');
      return;
    }

    if ($this->editRole === 'mahasiswa' && !$this->isAllowedMahasiswaEmail($this->editEmail)) {
      $this->addError('editEmail', 'Email mahasiswa wajib menggunakan domain ' . $this->mahasiswaDomainLabel() . '.');
      return;
    }

    if ($this->editRole === 'mahasiswa' && $this->editClearPassword && trim($this->editPassword) !== '') {
      $this->addError('editPassword', 'Pilih salah satu: isi password baru atau centang kosongkan password.');
      return;
    }

    $data = [
      'name' => $this->editName,
      'email' => Str::lower(trim($this->editEmail)),
      'role' => $this->editRole,
    ];

    if ($this->editRole === 'jurusan' && $this->editPassword) {
      $data['password'] = Hash::make($this->editPassword);
    }

    if ($this->editRole === 'mahasiswa') {
      if ($this->editClearPassword) {
        $data['password'] = null;
      } elseif (trim($this->editPassword) !== '') {
        $data['password'] = Hash::make($this->editPassword);
      }
    }

    $user->update($data);

    $this->closeEdit();
    $this->dispatch('toast', type: 'success', message: 'Akun berhasil diperbarui.');
  }

  /**
   * @return list<string>
   */
  private function mahasiswaDomains(): array
  {
    $configured = config('services.google.allowed_domains', []);
    if (!is_array($configured)) {
      $configured = [];
    }

    $domains = [];
    foreach ($configured as $domain) {
      $normalized = Str::lower(trim((string) $domain));
      if ($normalized !== '') {
        $domains[] = $normalized;
      }
    }

    if (empty($domains)) {
      $fallback = Str::lower((string) config('services.google.allowed_domain', 'mhs.unsoed.ac.id'));
      if ($fallback !== '') {
        $domains[] = $fallback;
      }
    }

    return array_values(array_unique($domains));
  }

  private function mahasiswaDomainLabel(): string
  {
    return implode(' atau ', array_map(
      static fn(string $domain): string => '@' . $domain,
      $this->mahasiswaDomains(),
    ));
  }

  private function isAllowedMahasiswaEmail(string $email): bool
  {
    $normalizedEmail = Str::lower(trim($email));

    foreach ($this->mahasiswaDomains() as $domain) {
      if (Str::endsWith($normalizedEmail, '@' . $domain)) {
        return true;
      }
    }

    return false;
  }

  // ── Delete ───────────────────────────────────────────────
  public function confirmDelete(int $id): void
  {
    $user = User::find($id);
    if (!$user)
      return;

    // Prevent deleting own account
    if ($id === auth()->id()) {
      $this->dispatch('toast', type: 'error', message: 'Tidak dapat menghapus akun sendiri.');
      return;
    }

    $this->deleteTargetId = $id;
    $this->deleteTargetName = $user->name;
    $this->showDeleteConfirm = true;
  }

  public function closeDeleteConfirm(): void
  {
    $this->showDeleteConfirm = false;
    $this->deleteTargetId = null;
    $this->deleteTargetName = '';
  }

  public function deleteUser(): void
  {
    if (!$this->deleteTargetId)
      return;

    if ($this->deleteTargetId === auth()->id()) {
      $this->dispatch('toast', type: 'error', message: 'Tidak dapat menghapus akun sendiri.');
      return;
    }

    $name = $this->deleteTargetName;
    User::where('id', $this->deleteTargetId)->delete();
    $this->closeDeleteConfirm();
    $this->dispatch('toast', type: 'success', message: "Akun \"{$name}\" berhasil dihapus.");
  }

  // ── Bulk ─────────────────────────────────────────────────
  public function toggleSelectAll(): void
  {
    if ($this->selectAll) {
      $query = User::query();
      if ($this->search) {
        $query->where(function ($q) {
          $q->where('name', 'like', "%{$this->search}%")
            ->orWhere('email', 'like', "%{$this->search}%");
        });
      }
      if ($this->roleFilter) {
        $query->where('role', $this->roleFilter);
      }
      $this->selectedIds = $query->latest()->paginate($this->perPage)->pluck('id')->map(fn($id) => (string) $id)->toArray();
    } else {
      $this->selectedIds = [];
    }
  }

  public function openBulkDeleteConfirm(): void
  {
    if (count($this->selectedIds) > 0) {
      $this->showBulkDeleteConfirm = true;
    }
  }

  public function closeBulkDeleteConfirm(): void
  {
    $this->showBulkDeleteConfirm = false;
  }

  public function bulkDelete(): void
  {
    $ids = array_filter($this->selectedIds, fn($id) => (int) $id !== auth()->id());
    $count = count($ids);
    if ($count === 0) {
      $this->showBulkDeleteConfirm = false;
      $this->dispatch('toast', type: 'warning', message: 'Tidak ada akun yang dapat dihapus (akun Anda tidak dapat dihapus).');
      return;
    }
    User::whereIn('id', $ids)->delete();
    $this->selectedIds = [];
    $this->selectAll = false;
    $this->showBulkDeleteConfirm = false;
    $this->resetPage();
    $this->dispatch('toast', type: 'success', message: "{$count} akun berhasil dihapus.");
  }

  public function render()
  {
    $query = User::query();

    if ($this->search) {
      $query->where(function ($q) {
        $q->where('name', 'like', "%{$this->search}%")
          ->orWhere('email', 'like', "%{$this->search}%");
      });
    }

    if ($this->roleFilter) {
      $query->where('role', $this->roleFilter);
    }

    $users = $query->latest()->paginate($this->perPage);
    $totalUsers = User::count();

    return view('livewire.jurusan.account-manager', compact('users', 'totalUsers'));
  }
}
