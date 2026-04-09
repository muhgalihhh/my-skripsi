<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JurusanAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function jurusanPageRoutes(): array
    {
        return [
            'dashboard' => ['jurusan.dashboard'],
            'scraping' => ['jurusan.scraping.index'],
            'skripsi' => ['jurusan.skripsi.index'],
            'akun' => ['jurusan.akun.index'],
            'profil' => ['jurusan.profil'],
            'topic modeling' => ['jurusan.topic-modeling'],
            'topic curation' => ['jurusan.topic-curation'],
            'visualisasi' => ['jurusan.visualisasi'],
        ];
    }

    public function test_guest_is_redirected_from_jurusan_dashboard(): void
    {
        $this->get(route('jurusan.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_mahasiswa_is_forbidden_from_jurusan_dashboard(): void
    {
        $mahasiswa = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $this->actingAs($mahasiswa)
            ->get(route('jurusan.dashboard'))
            ->assertForbidden();
    }

    #[DataProvider('jurusanPageRoutes')]
    public function test_jurusan_can_access_core_pages(string $routeName): void
    {
        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $this->actingAs($jurusan)
            ->get(route($routeName))
            ->assertOk();
    }
}
