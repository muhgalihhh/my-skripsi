<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthAuthTest extends TestCase
{
    use RefreshDatabase;

    private function setAllowedDomains(array $domains): void
    {
        config()->set('services.google.allowed_domains', $domains);
        config()->set('services.google.allowed_domain', $domains[0] ?? 'mhs.unsoed.ac.id');
    }

    public function test_google_callback_creates_new_mahasiswa_for_allowed_domain(): void
    {
        $this->setAllowedDomains(['mhs.unsoed.ac.id', 'unsoed.ac.id']);

        $this->mockGoogleUser(
            id: 'google-123',
            email: 'andi@mhs.unsoed.ac.id',
            name: 'Andi Mahasiswa',
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('mahasiswa.dashboard'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'andi@mhs.unsoed.ac.id',
            'role' => 'mahasiswa',
            'google_id' => 'google-123',
        ]);

        $created = User::where('email', 'andi@mhs.unsoed.ac.id')->firstOrFail();
        $this->assertNull($created->password);
    }

    public function test_google_callback_accepts_unsoed_domain(): void
    {
        $this->setAllowedDomains(['mhs.unsoed.ac.id', 'unsoed.ac.id']);

        $this->mockGoogleUser(
            id: 'google-unsoed',
            email: 'dosen@unsoed.ac.id',
            name: 'Dosen Unsoed',
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('mahasiswa.dashboard'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'dosen@unsoed.ac.id',
            'role' => 'mahasiswa',
            'google_id' => 'google-unsoed',
        ]);
    }

    public function test_google_callback_rejects_non_unsoed_student_domain(): void
    {
        $this->setAllowedDomains(['mhs.unsoed.ac.id', 'unsoed.ac.id']);

        $this->mockGoogleUser(
            id: 'google-999',
            email: 'andi@gmail.com',
            name: 'Andi Gmail',
        );

        $response = $this->from(route('login'))->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('oauth');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_callback_rejects_existing_non_mahasiswa_user(): void
    {
        $this->setAllowedDomains(['mhs.unsoed.ac.id', 'unsoed.ac.id']);

        User::factory()->create([
            'name' => 'Admin Jurusan',
            'email' => 'admin@mhs.unsoed.ac.id',
            'role' => 'jurusan',
        ]);

        $this->mockGoogleUser(
            id: 'google-admin',
            email: 'admin@mhs.unsoed.ac.id',
            name: 'Admin Jurusan',
        );

        $response = $this->from(route('login'))->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('oauth');

        $this->assertGuest();
    }

    private function mockGoogleUser(string $id, string $email, string $name): void
    {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);
    }
}