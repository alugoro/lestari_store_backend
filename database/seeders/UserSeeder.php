<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin Lestari',
                'email' => 'admin@lestari.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'name' => 'Owner Toko',
                'email' => 'owner@lestari.com',
                'password' => Hash::make('password123'),
                'role' => 'owner',
                'is_active' => true,
            ],
            [
                'name' => 'Kasir 1',
                'email' => 'kasir1@lestari.com',
                'password' => Hash::make('password123'),
                'role' => 'kasir',
                'is_active' => true,
            ],
            [
                'name' => 'Kasir 2',
                'email' => 'kasir2@lestari.com',
                'password' => Hash::make('password123'),
                'role' => 'kasir',
                'is_active' => true,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}