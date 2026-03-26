<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
      $request->session()->regenerate();

      $user = Auth::user();

      // Redirect based on role
      if ($user->isJurusan()) {
        return redirect()->intended(route('jurusan.dashboard'));
      }

      // For future: redirect mahasiswa to their dashboard
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
