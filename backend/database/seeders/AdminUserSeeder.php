<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando usuario Admin Demo...');

        User::updateOrCreate(
            ['email' => 'admin@demo.cl'],
            [
                'name' => 'Admin Demo',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
            ]
        );
    }
}
