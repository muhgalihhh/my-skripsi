<?php

namespace App\Livewire\Jurusan;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
      'addPassword' => 'required|min:8|same:addPasswordConfirmation',
      'addRole' => 'required|in:jurusan,mahasiswa',
    ], [
      'addName.required' => 'Nama wajib diisi.',
      'addEmail.unique' => 'Email sudah terdaftar.',
      'addPassword.min' => 'Password minimal 8 karakter.',
      'addPassword.same' => 'Konfirmasi password tidak cocok.',
    ]);

    User::create([
      'name' => $this->addName,
      'email' => $this->addEmail,
      'password' => Hash::make($this->addPassword),
      'role' => $this->addRole,
    ]);

    $this->closeAdd();
    $this->dispatch('toast', type: 'success', message: 'Akun berhasil ditambahkan.');
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
    $this->editPassword = '';
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
      'editPassword' => 'nullable|min:8',
    ], [
      'editName.required' => 'Nama wajib diisi.',
      'editEmail.unique' => 'Email sudah dipakai akun lain.',
      'editPassword.min' => 'Password baru minimal 8 karakter.',
    ]);

    $user = User::find($this->editUserId);
    if (!$user)
      return;

    // Prevent self lockout: a jurusan user must not demote their own account.
    if ((int) $user->id === (int) auth()->id() && $this->editRole !== 'jurusan') {
      $this->addError('editRole', 'Akun Anda harus tetap memiliki role Jurusan.');
      $this->dispatch('toast', type: 'error', message: 'Role akun sendiri tidak boleh diubah dari Jurusan.');
      return;
    }

    $data = [
      'name' => $this->editName,
      'email' => $this->editEmail,
      'role' => $this->editRole,
    ];

    if ($this->editPassword) {
      $data['password'] = Hash::make($this->editPassword);
    }

    $user->update($data);

    $this->closeEdit();
    $this->dispatch('toast', type: 'success', message: 'Akun berhasil diperbarui.');
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
