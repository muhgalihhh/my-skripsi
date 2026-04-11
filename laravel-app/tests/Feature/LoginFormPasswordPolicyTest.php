<?php

namespace Tests\Feature;

use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginFormPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_rejects_user_with_empty_local_password(): void
    {
        $mahasiswa = User::factory()->create([
            'email' => 'mahasiswa@mhs.unsoed.ac.id',
            'role' => 'mahasiswa',
            'password' => null,
        ]);

        Livewire::test(LoginForm::class)
            ->set('email', $mahasiswa->email)
            ->set('password', 'password-coba-123')
            ->call('login')
            ->assertHasErrors(['password']);

        $this->assertGuest();
    }
}
