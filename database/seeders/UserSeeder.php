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
            'approved_at' => now(),
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Approved Barangay Users
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
            'approved_at' => now(),
            'status' => 'approved',
            'is_active' => true,
        ]);

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
            'approved_at' => now(),
            'status' => 'approved',
            'is_active' => true,
        ]);

        // List of barangays from your Excel data
        $barangays = [
            'BOGAYO', 'BOLISONG', 'BOYUGAN East', 'BOYUGAN West', 'BUALAN', 
            'DIPLO', 'GAWIL', 'GUSOM', 'KITAANG DAGAT', 'LANTAWAN', 
            'LIMAMAWAN', 'MAHAYAHAY', 'PANGI', 'PICANAN', 'POBLACION', 
            'SALAGMANOK', 'SICADE', 'SUMINALOM'
        ];

        // List of municipalities for variety
        $municipalities = [
            'Pagadian City',
            'Dumingag',
            'Molave', 
            'Aurora',
            'Bayog',
            'Dimataling',
            'Dinas',
            'Guipos',
            'Josefina',
            'Kumalarang'
        ];

        // Positions for barangay officials
        $positions = [
            'Barangay Captain',
            'Barangay Secretary',
            'Barangay Treasurer',
            'Barangay Councilor',
            'SK Chairman',
            'Barangay Health Worker'
        ];

        // Generate 30 pending barangay accounts
        for ($i = 1; $i <= 30; $i++) {
            $barangay = $barangays[array_rand($barangays)];
            $municipality = $municipalities[array_rand($municipalities)];
            $position = $positions[array_rand($positions)];
            
            User::create([
                'name' => $this->generateRandomName(),
                'email' => strtolower(str_replace(' ', '', $barangay)) . $i . '@barangay.com',
                'password' => Hash::make('password123'),
                'barangay_name' => $barangay,
                'position' => $position,
                'municipality' => $municipality,
                'contact' => '09' . rand(100000000, 999999999),
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($this->generateRandomName()) . '&background=0D8ABC&color=fff',
                'role' => 'barangay',
                'is_approved' => false,
                'approved_at' => null,
                'status' => 'pending',
                'is_active' => true,
                'created_at' => now()->subDays(rand(1, 30)) // Random registration dates
            ]);
        }

        // Add a few more with specific municipalities for better filtering tests
        User::create([
            'name' => 'Ramon Garcia',
            'email' => 'ramon.garcia@molave.com',
            'password' => Hash::make('password123'),
            'barangay_name' => 'POBLACION',
            'position' => 'Barangay Captain',
            'municipality' => 'Molave',
            'contact' => '09112223334',
            'avatar' => 'https://ui-avatars.com/api/?name=Ramon+Garcia&background=0D8ABC&color=fff',
            'role' => 'barangay',
            'is_approved' => false,
            'approved_at' => null,
            'status' => 'pending',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Lorna Dimaporo',
            'email' => 'lorna.dimaporo@aurora.com',
            'password' => Hash::make('password123'),
            'barangay_name' => 'BOGAYO',
            'position' => 'Barangay Secretary',
            'municipality' => 'Aurora',
            'contact' => '09123334445',
            'avatar' => 'https://ui-avatars.com/api/?name=Lorna+Dimaporo&background=0D8ABC&color=fff',
            'role' => 'barangay',
            'is_approved' => false,
            'approved_at' => null,
            'status' => 'pending',
            'is_active' => true,
        ]);
    }

    /**
     * Generate random Filipino names
     */
    private function generateRandomName()
    {
        $firstNames = [
            'Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Antonio', 'Carmen', 
            'Francisco', 'Teresa', 'Manuel', 'Isabel', 'Ramon', 'Lourdes', 'Carlos', 
            'Rosa', 'Luis', 'Concepcion', 'Miguel', 'Josefa', 'Rafael', 'Filomena',
            'Eduardo', 'Gertrudes', 'Alfredo', 'Marcela', 'Ricardo', 'Francisca',
            'Roberto', 'Andrea', 'Fernando', 'Catalina', 'Jorge', 'Felisa'
        ];

        $lastNames = [
            'Dela Cruz', 'Garcia', 'Reyes', 'Ramos', 'Mendoza', 'Santos', 
            'Flores', 'Gonzales', 'Bautista', 'Villanueva', 'Fernandez', 
            'Cruz', 'De Guzman', 'Lopez', 'Perez', 'Castillo', 'Francisco',
            'Rivera', 'Aquino', 'Castro', 'De Leon', 'Pascual', 'Gutierrez',
            'Navarro', 'Salazar', 'Torres', 'Domingo', 'Mercado', 'Estrada',
            'Marquez', 'Valdez', 'Romero', 'Ortega', 'Santiago'
        ];

        $middleInitials = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $middleInitial = $middleInitials[array_rand($middleInitials)];

        return $firstName . ' ' . $middleInitial . '. ' . $lastName;
    }
}