<?php

namespace Tests\Feature;

use App\Livewire\Jurusan\AccountManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JurusanAccountManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_jurusan_cannot_demote_own_role(): void
    {
        $jurusan = User::factory()->create([
            'name' => 'Admin Jurusan',
            'email' => 'jurusan@example.test',
            'role' => 'jurusan',
        ]);

        $this->actingAs($jurusan);

        Livewire::test(AccountManager::class)
            ->set('editUserId', $jurusan->id)
            ->set('editName', $jurusan->name)
            ->set('editEmail', $jurusan->email)
            ->set('editRole', 'mahasiswa')
            ->set('editPassword', '')
            ->call('saveEdit')
            ->assertHasErrors(['editRole']);

        $jurusan->refresh();

        $this->assertSame('jurusan', $jurusan->role);
    }

    public function test_jurusan_can_update_other_user_role(): void
    {
        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $target = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.test',
            'role' => 'mahasiswa',
        ]);

        $this->actingAs($jurusan);

        Livewire::test(AccountManager::class)
            ->set('editUserId', $target->id)
            ->set('editName', $target->name)
            ->set('editEmail', $target->email)
            ->set('editRole', 'jurusan')
            ->set('editPassword', '')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $target->refresh();

        $this->assertSame('jurusan', $target->role);
    }
}
