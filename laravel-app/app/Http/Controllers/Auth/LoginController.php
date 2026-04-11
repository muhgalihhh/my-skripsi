<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoginController extends Controller
{
  /**
   * Show login form.
   */
  public function showLoginForm()
  {
    return view('auth.login');
  }

  /**
   * Handle login request.
   */
  public function login(Request $request)
  {
    $credentials = $request->validate([
      'email' => ['required', 'email'],
      'password' => ['required'],
    ]);

    $credentials['email'] = Str::lower(trim((string) $credentials['email']));

    $user = User::where('email', $credentials['email'])->first();
    if ($user && blank($user->password)) {
      return back()->withErrors([
        'password' => 'Akun ini belum memiliki password login form. Gunakan Google OAuth atau atur password terlebih dahulu.',
      ])->onlyInput('email');
    }

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
      $request->session()->regenerate();

      $user = Auth::user();

      // Redirect based on role
      if ($user->isJurusan()) {
        return redirect()->intended(route('jurusan.dashboard'));
      }

      if ($user->isMahasiswa()) {
        return redirect()->intended(route('mahasiswa.dashboard'));
      }

      return redirect()->intended('/');
    }

    return back()->withErrors([
      'email' => 'Email atau password yang Anda masukkan salah.',
    ])->onlyInput('email');
  }

  /**
   * Handle logout request.
   */
  public function logout(Request $request)
  {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
  }
}
