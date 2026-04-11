<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Login')]
class LoginForm extends Component
{
  #[Rule('required|email')]
  public string $email = '';

  #[Rule('required|min:6')]
  public string $password = '';

  public bool $remember = false;

  public function login(): void
  {
    $this->validate();

    $normalizedEmail = Str::lower(trim($this->email));
    $user = User::where('email', $normalizedEmail)->first();

    if ($user && blank($user->password)) {
      $this->addError('password', 'Akun ini belum memiliki password login form. Gunakan Google OAuth atau atur password terlebih dahulu.');
      return;
    }

    if (Auth::attempt(['email' => $normalizedEmail, 'password' => $this->password], $this->remember)) {
      session()->regenerate();

      $user = Auth::user();

      if ($user->isJurusan()) {
        $this->redirect(route('jurusan.dashboard'), navigate: true);
      } elseif ($user->isMahasiswa()) {
        $this->redirect(route('mahasiswa.dashboard'), navigate: true);
      } else {
        $this->redirect('/', navigate: true);
      }
    } else {
      $this->addError('email', 'Email atau password salah.');
    }
  }

  public function render()
  {
    return view('livewire.auth.login-form');
  }
}
