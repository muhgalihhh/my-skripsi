<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsJurusan
{
  /**
   * Handle an incoming request.
   * Only allow users with role 'jurusan' to pass.
   */
  public function handle(Request $request, Closure $next): Response
  {
    if (!$request->user() || !$request->user()->isJurusan()) {
      abort(403, 'Akses ditolak. Hanya role Jurusan yang dapat mengakses halaman ini.');
    }

    return $next($request);
  }
}
