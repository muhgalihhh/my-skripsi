<?php

namespace App\Livewire\Jurusan;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.jurusan')]
#[Title('Edit Profil')]
class ProfileEditor extends Component
{
  public string $name = '';
  public string $email = '';
  public string $currentPassword = '';
  public string $newPassword = '';
  public string $newPasswordConfirmation = '';

  public bool $showDeleteConfirm = false;

  public function mount(): void
  {
    $user = Auth::user();
    $this->name = $user->name;
    $this->email = $user->email;
  }

  public function saveProfile(): void
  {
    /** @var \App\Models\User $user */
    $user = Auth::user();

    $this->validate([
      'name' => 'required|string|min:3|max:100',
      'email' => "required|email|unique:users,email,{$user->id}",
    ], [
      'name.required' => 'Nama wajib diisi.',
      'email.unique' => 'Email sudah dipakai akun lain.',
    ]);

    $user->update([
      'name' => $this->name,
      'email' => $this->email,
    ]);

    $this->dispatch('toast', type: 'success', message: 'Profil berhasil diperbarui.');
  }

  public function changePassword(): void
  {
    $this->validate([
      'currentPassword' => 'required',
      'newPassword' => 'required|min:8|same:newPasswordConfirmation',
    ], [
      'currentPassword.required' => 'Password saat ini wajib diisi.',
      'newPassword.min' => 'Password baru minimal 8 karakter.',
      'newPassword.same' => 'Konfirmasi password tidak cocok.',
    ]);

    /** @var \App\Models\User $user */
    $user = Auth::user();

    if (!Hash::check($this->currentPassword, $user->password)) {
      $this->addError('currentPassword', 'Password saat ini tidak sesuai.');
      return;
    }

    $user->update(['password' => Hash::make($this->newPassword)]);

    $this->currentPassword = '';
    $this->newPassword = '';
    $this->newPasswordConfirmation = '';
    $this->resetValidation();

    $this->dispatch('toast', type: 'success', message: 'Password berhasil diubah.');
  }

  public function render()
  {
    return view('livewire.jurusan.profile-editor');
  }
}
