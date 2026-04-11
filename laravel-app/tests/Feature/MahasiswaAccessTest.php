<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MahasiswaAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function mahasiswaRoutes(): array
    {
        return [
            'dashboard' => ['mahasiswa.dashboard'],
            'profil' => ['mahasiswa.profil'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mahasiswaRoutes')]
    public function test_guest_is_redirected_from_mahasiswa_pages(string $routeName): void
    {
        $this->get(route($routeName))
            ->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mahasiswaRoutes')]
    public function test_jurusan_is_forbidden_from_mahasiswa_pages(string $routeName): void
    {
        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $this->actingAs($jurusan)
            ->get(route($routeName))
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mahasiswaRoutes')]
    public function test_mahasiswa_can_access_pages(string $routeName): void
    {
        $mahasiswa = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $this->actingAs($mahasiswa)
            ->get(route($routeName))
            ->assertOk();
    }
}
