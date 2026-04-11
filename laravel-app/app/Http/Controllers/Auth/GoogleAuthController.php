<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $driver = Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email']);

        $primaryDomain = $this->singleAllowedDomain();
        if ($primaryDomain !== null) {
            $driver = $driver->with(['hd' => $primaryDomain]);
        }

        return $driver->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('login')
                ->withErrors(['oauth' => 'Login Google gagal. Silakan coba lagi.']);
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));
        if ($email === '' || !$this->hasAllowedDomain($email)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'oauth' => 'Hanya email domain ' . $this->allowedDomainsLabel() . ' yang diizinkan.',
                ]);
        }

        $googleId = trim((string) $googleUser->getId());
        if ($googleId === '') {
            return redirect()
                ->route('login')
                ->withErrors(['oauth' => 'Google ID tidak valid. Silakan coba lagi.']);
        }

        $name = trim((string) ($googleUser->getName() ?: $googleUser->getNickname()));
        if ($name === '') {
            $name = Str::before($email, '@');
        }

        $user = User::where('email', $email)->first();

        if ($user && !$user->isMahasiswa()) {
            return redirect()
                ->route('login')
                ->withErrors(['oauth' => 'Akun ini bukan role mahasiswa.']);
        }

        if ($user && filled($user->google_id) && $user->google_id !== $googleId) {
            return redirect()
                ->route('login')
                ->withErrors(['oauth' => 'Akun Google tidak cocok dengan akun yang tersimpan.']);
        }

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => null,
                'role' => 'mahasiswa',
                'google_id' => $googleId,
                'email_verified_at' => now(),
            ]);
        } else {
            $updates = [];

            if (blank($user->google_id)) {
                $updates['google_id'] = $googleId;
            }

            if (blank($user->email_verified_at)) {
                $updates['email_verified_at'] = now();
            }

            if (blank($user->name)) {
                $updates['name'] = $name;
            }

            if (!empty($updates)) {
                $user->update($updates);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('mahasiswa.dashboard'));
    }

    /**
     * @return list<string>
     */
    private function allowedDomains(): array
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

    private function singleAllowedDomain(): ?string
    {
        $domains = $this->allowedDomains();
        if (count($domains) !== 1) {
            return null;
        }

        return $domains[0] ?? null;
    }

    private function allowedDomainsLabel(): string
    {
        return implode(' atau ', array_map(
            static fn(string $domain): string => '@' . $domain,
            $this->allowedDomains()
        ));
    }

    private function hasAllowedDomain(string $email): bool
    {
        $normalizedEmail = Str::lower(trim($email));

        foreach ($this->allowedDomains() as $domain) {
            if (Str::endsWith($normalizedEmail, '@' . $domain)) {
                return true;
            }
        }

        return false;
    }
}