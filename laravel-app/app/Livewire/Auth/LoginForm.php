<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
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

    if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
      session()->regenerate();

      $user = Auth::user();

      if ($user->isJurusan()) {
        $this->redirect(route('jurusan.dashboard'), navigate: true);
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
