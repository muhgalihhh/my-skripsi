<?php

namespace App\Livewire\Mahasiswa;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.mahasiswa')]
#[Title('Profil Mahasiswa')]
class ProfileEditor extends Component
{
    public string $name = '';
    public string $email = '';

    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';
    public bool $clearPassword = false;

    public bool $hasLocalPassword = false;
    public bool $googleLinked = false;

    public function mount(): void
    {
        $this->refreshProfileState();
    }

    public function updatedClearPassword(): void
    {
        if ($this->clearPassword) {
            $this->newPassword = '';
            $this->newPasswordConfirmation = '';
            $this->resetValidation();
        }
    }

    public function savePasswordSettings(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->hasLocalPassword = filled($user->password);

        if ($this->clearPassword) {
            if (trim($this->newPassword) !== '') {
                $this->addError('newPassword', 'Jangan isi password baru jika memilih kosongkan password.');
                return;
            }

            if ($this->hasLocalPassword) {
                $this->validate([
                    'currentPassword' => 'required',
                ], [
                    'currentPassword.required' => 'Password saat ini wajib diisi untuk menonaktifkan login form.',
                ]);

                if (!Hash::check($this->currentPassword, (string) $user->password)) {
                    $this->addError('currentPassword', 'Password saat ini tidak sesuai.');
                    return;
                }
            }

            $user->update(['password' => null]);

            $this->currentPassword = '';
            $this->newPassword = '';
            $this->newPasswordConfirmation = '';
            $this->clearPassword = false;

            $this->refreshProfileState();
            $this->resetValidation();
            $this->dispatch('toast', type: 'success', message: 'Password login form berhasil dikosongkan.');
            return;
        }

        $this->validate([
            'newPassword' => 'required|min:8|same:newPasswordConfirmation',
        ], [
            'newPassword.required' => 'Password baru wajib diisi.',
            'newPassword.min' => 'Password baru minimal 8 karakter.',
            'newPassword.same' => 'Konfirmasi password baru tidak cocok.',
        ]);

        if ($this->hasLocalPassword) {
            $this->validate([
                'currentPassword' => 'required',
            ], [
                'currentPassword.required' => 'Password saat ini wajib diisi.',
            ]);

            if (!Hash::check($this->currentPassword, (string) $user->password)) {
                $this->addError('currentPassword', 'Password saat ini tidak sesuai.');
                return;
            }
        }

        $user->update([
            'password' => Hash::make($this->newPassword),
        ]);

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->clearPassword = false;

        $this->refreshProfileState();
        $this->resetValidation();
        $this->dispatch('toast', type: 'success', message: 'Password login form berhasil disimpan.');
    }

    private function refreshProfileState(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->hasLocalPassword = filled($user->password);
        $this->googleLinked = filled($user->google_id);
    }

    public function render()
    {
        return view('livewire.mahasiswa.profile-editor');
    }
}
