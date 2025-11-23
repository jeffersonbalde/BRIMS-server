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
            'avatar' => null,
            'role' => 'admin',
            'is_approved' => true,
            'approved_at' => now(),
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Barangay users for each barangay
        $barangays = [
            'BOGAYO', 'BOLISONG', 'BOYUGAN East', 'BOYUGAN West', 'BUALAN', 'DIPLO',
            'GAWIL', 'GUSOM', 'KITAANG DAGAT', 'LANTAWAN', 'LIMAMAWAN', 'MAHAYAHAY',
            'PANGI', 'PICANAN', 'POBLACION', 'SALAGMANOK', 'SICADE', 'SUMINALOM'
        ];

        foreach ($barangays as $index => $barangay) {
            $number = $index + 1;
            $email = strtolower(str_replace(' ', '', $barangay)) . '@barangay.com';
            
            User::create([
                'name' => "Barangay Captain {$barangay}",
                'email' => $email,
                'password' => Hash::make('123456'),
                'barangay_name' => $barangay,
                'position' => 'Barangay Captain',
                'municipality' => 'Pagadian City',
                'contact' => '09' . sprintf('%09d', $number + 100000000),
                'avatar' => null,
                'role' => 'barangay',
                'is_approved' => true,
                'approved_at' => now(),
                'status' => 'approved',
                'is_active' => true,
            ]);

            // Create additional barangay staff for larger barangays
            if (in_array($barangay, ['POBLACION', 'BOGAYO', 'BOLISONG'])) {
                $staffEmail = strtolower(str_replace(' ', '', $barangay)) . 'secretary@barangay.com';
                User::create([
                    'name' => "Barangay Secretary {$barangay}",
                    'email' => $staffEmail,
                    'password' => Hash::make('123456'),
                    'barangay_name' => $barangay,
                    'position' => 'Barangay Secretary',
                    'municipality' => 'Pagadian City',
                    'contact' => '09' . sprintf('%09d', $number + 200000000),
                    'avatar' => null,
                    'role' => 'barangay',
                    'is_approved' => true,
                    'approved_at' => now(),
                    'status' => 'approved',
                    'is_active' => true,
                ]);
            }
        }

        // Create some pending barangay users for testing
        User::create([
            'name' => 'Pending Barangay User',
            'email' => 'pending@barangay.com',
            'password' => Hash::make('123456'),
            'barangay_name' => 'BOGAYO',
            'position' => 'Barangay Staff',
            'municipality' => 'Pagadian City',
            'contact' => '09111111111',
            'avatar' => null,
            'role' => 'barangay',
            'is_approved' => false,
            'approved_at' => null,
            'status' => 'pending',
            'is_active' => true,
        ]);

        $this->command->info('Users seeded successfully!');
        $this->command->info('Admin: admin@admin.com / 123456');
        $this->command->info('Barangay users: barangayname@barangay.com / 123456');
    }
}