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
        // Admin user
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('123456'),
            'barangay_name' => null,
            'position' => 'Administrator',
            'municipality' => 'Pagadian City',
            'contact' => '09123456789',
            'avatar' => 'https://ui-avatars.com/api/?name=System+Admin&background=0D8ABC&color=fff',
            'role' => 'admin',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Barangay 1
        User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juandelacruz@brims.com',
            'password' => Hash::make('password123'),
            'barangay_name' => 'Barangay San Pedro',
            'position' => 'Barangay Captain',
            'municipality' => 'Pagadian City',
            'contact' => '09123456788',
            'avatar' => 'https://ui-avatars.com/api/?name=JuanDela+Cruz&background=0D8ABC&color=fff',
            'role' => 'barangay',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Barangay 2
        User::create([
            'name' => 'Maria Santos',
            'email' => 'mariasantos@brims.com',
            'password' => Hash::make('password123'),
            'barangay_name' => 'Barangay Sta. Lucia',
            'position' => 'Barangay Secretary',
            'municipality' => 'Pagadian City',
            'contact' => '09987654321',
            'avatar' => 'https://ui-avatars.com/api/?name=Maria+Santos&background=0D8ABC&color=fff',
            'role' => 'barangay',
            'is_approved' => true,
            'is_active' => true,
        ]);
    }
}
