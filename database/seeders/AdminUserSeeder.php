<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'full_name' => 'Super Admin',
            'phone' => '0500000000',
            'password' => Hash::make('Admin@123456'),
            'role' => 'admin',
            'status' => 'active'
        ]);
    }
}