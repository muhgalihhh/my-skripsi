<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JurusanSeeder extends Seeder
{
  /**
   * Seed the jurusan (admin) user.
   */
  public function run(): void
  {
    User::updateOrCreate(
      ['email' => 'jurusan@unsoed.ac.id'],
      [
        'name' => 'Admin Jurusan Informatika',
        'email' => 'jurusan@unsoed.ac.id',
        'password' => Hash::make('password'),
        'role' => 'jurusan',
        'email_verified_at' => now(),
      ]
    );

    $this->command->info('✅ Jurusan admin user created:');
    $this->command->info('   Email: jurusan@unsoed.ac.id');
    $this->command->info('   Password: password');
  }
}
