<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@pawsitive.com'],
            [
                'name'     => 'Pawsitive Admin',
                'password' => Hash::make('Admin@12345'),
                'role'     => 'admin',
                'phone'    => null,
            ]
        );

        $this->command->info('Admin user seeded: admin@pawsitive.com / Admin@12345');
    }
}
