<?php

namespace Tests\Feature;

use App\Livewire\Mahasiswa\ProfileEditor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MahasiswaProfileEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_mahasiswa_can_set_local_password_when_currently_empty(): void
    {
        $mahasiswa = User::factory()->create([
            'role' => 'mahasiswa',
            'password' => null,
        ]);

        $this->actingAs($mahasiswa);

        Livewire::test(ProfileEditor::class)
            ->set('newPassword', 'password-baru-123')
            ->set('newPasswordConfirmation', 'password-baru-123')
            ->set('clearPassword', false)
            ->call('savePasswordSettings')
            ->assertHasNoErrors();

        $mahasiswa->refresh();

        $this->assertTrue(Hash::check('password-baru-123', (string) $mahasiswa->password));
    }

    public function test_mahasiswa_can_clear_local_password_with_current_password(): void
    {
        $mahasiswa = User::factory()->create([
            'role' => 'mahasiswa',
            'password' => Hash::make('password-lama-123'),
        ]);

        $this->actingAs($mahasiswa);

        Livewire::test(ProfileEditor::class)
            ->set('currentPassword', 'password-lama-123')
            ->set('clearPassword', true)
            ->set('newPassword', '')
            ->call('savePasswordSettings')
            ->assertHasNoErrors();

        $mahasiswa->refresh();

        $this->assertNull($mahasiswa->password);
    }
}
