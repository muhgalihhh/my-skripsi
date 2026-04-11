<?php

namespace Tests\Feature;

use App\Livewire\Jurusan\AccountManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class JurusanAccountManagerTest extends TestCase
{
    use RefreshDatabase;

    private function setAllowedDomains(array $domains): void
    {
        config()->set('services.google.allowed_domains', $domains);
        config()->set('services.google.allowed_domain', $domains[0] ?? 'mhs.unsoed.ac.id');
    }

    public function test_jurusan_can_add_mahasiswa_without_local_password_for_allowed_domain(): void
    {
        $this->setAllowedDomains(['mhs.unsoed.ac.id']);

        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $this->actingAs($jurusan);

        Livewire::test(AccountManager::class)
            ->set('addName', 'Mahasiswa Baru')
            ->set('addEmail', 'mahasiswa@mhs.unsoed.ac.id')
            ->set('addRole', 'mahasiswa')
            ->set('addPassword', '')
            ->set('addPasswordConfirmation', '')
            ->call('saveAdd')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Mahasiswa Baru',
            'email' => 'mahasiswa@mhs.unsoed.ac.id',
            'role' => 'mahasiswa',
        ]);

        $created = User::where('email', 'mahasiswa@mhs.unsoed.ac.id')->firstOrFail();
        $this->assertNull($created->password);
    }

    public function test_jurusan_cannot_add_mahasiswa_with_non_unsoed_domain(): void
    {
        $this->setAllowedDomains(['mhs.unsoed.ac.id']);

        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $this->actingAs($jurusan);

        Livewire::test(AccountManager::class)
            ->set('addName', 'Mahasiswa Non Domain')
            ->set('addEmail', 'mahasiswa@gmail.com')
            ->set('addRole', 'mahasiswa')
            ->call('saveAdd')
            ->assertHasErrors(['addEmail']);

        $this->assertDatabaseMissing('users', [
            'email' => 'mahasiswa@gmail.com',
        ]);
    }

    public function test_jurusan_can_add_mahasiswa_with_unsoed_domain_when_configured(): void
    {
        $this->setAllowedDomains(['mhs.unsoed.ac.id', 'unsoed.ac.id']);

        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $this->actingAs($jurusan);

        Livewire::test(AccountManager::class)
            ->set('addName', 'Mahasiswa Domain Unsoed')
            ->set('addEmail', 'mahasiswa@unsoed.ac.id')
            ->set('addRole', 'mahasiswa')
            ->set('addPassword', '')
            ->set('addPasswordConfirmation', '')
            ->call('saveAdd')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'mahasiswa@unsoed.ac.id',
            'role' => 'mahasiswa',
        ]);
    }

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

    public function test_jurusan_can_set_local_password_for_mahasiswa_account(): void
    {
        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $target = User::factory()->create([
            'name' => 'Mahasiswa Target',
            'email' => 'target@mhs.unsoed.ac.id',
            'role' => 'mahasiswa',
        ]);

        $this->actingAs($jurusan);

        Livewire::test(AccountManager::class)
            ->set('editUserId', $target->id)
            ->set('editName', $target->name)
            ->set('editEmail', $target->email)
            ->set('editRole', 'mahasiswa')
            ->set('editPassword', 'password-baru-123')
            ->set('editPasswordConfirmation', 'password-baru-123')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $target->refresh();

        $this->assertTrue(Hash::check('password-baru-123', (string) $target->password));
    }

    public function test_jurusan_can_clear_local_password_for_mahasiswa_account(): void
    {
        $jurusan = User::factory()->create([
            'role' => 'jurusan',
        ]);

        $target = User::factory()->create([
            'name' => 'Mahasiswa Target',
            'email' => 'target@mhs.unsoed.ac.id',
            'role' => 'mahasiswa',
            'password' => Hash::make('password-lama-123'),
        ]);

        $this->actingAs($jurusan);

        Livewire::test(AccountManager::class)
            ->set('editUserId', $target->id)
            ->set('editName', $target->name)
            ->set('editEmail', $target->email)
            ->set('editRole', 'mahasiswa')
            ->set('editClearPassword', true)
            ->set('editPassword', '')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $target->refresh();

        $this->assertNull($target->password);
    }
}
