<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin user only
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('123456'),
            'barangay_name' => null,
            'position' => 'Administrator',
            'municipality' => 'Pagadian City',
            'contact' => '09123456789',
            'avatar' => null,
            'role' => 'admin',
            'is_approved' => true,
            'approved_at' => now(),
            'status' => 'approved',
            'is_active' => true,
        ]);
    }
}