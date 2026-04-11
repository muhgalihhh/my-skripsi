<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsMahasiswa
{
  /**
   * Handle an incoming request.
   * Only allow users with role 'mahasiswa' to pass.
   */
  public function handle(Request $request, Closure $next): Response
  {
    if (!$request->user() || !$request->user()->isMahasiswa()) {
      abort(403, 'Akses ditolak. Hanya role Mahasiswa yang dapat mengakses halaman ini.');
    }

    return $next($request);
  }
}
