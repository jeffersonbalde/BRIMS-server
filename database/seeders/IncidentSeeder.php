<?php
// database/seeders/IncidentSeeder.php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\IncidentFamily;
use App\Models\IncidentFamilyMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        IncidentFamilyMember::truncate();
        IncidentFamily::truncate();
        Incident::truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Get all approved barangay users and group them by barangay
        $barangayUsers = User::where('role', 'barangay')
            ->where('is_approved', true)
            ->get()
            ->groupBy('barangay_name');

        if ($barangayUsers->isEmpty()) {
            $this->command->warn('No barangay users found. Please run UserSeeder first.');
            return;
        }

        $incidentTypes = ['Flood', 'Landslide', 'Fire', 'Earthquake', 'Vehicular'];
        $severities = ['Low', 'Medium', 'High', 'Critical'];
        $barangays = [
            'BOGAYO', 'BOLISONG', 'BOYUGAN East', 'BOYUGAN West', 'BUALAN', 'DIPLO',
            'GAWIL', 'GUSOM', 'KITAANG DAGAT', 'LANTAWAN', 'LIMAMAWAN', 'MAHAYAHAY',
            'PANGI', 'PICANAN', 'POBLACION', 'SALAGMANOK', 'SICADE', 'SUMINALOM'
        ];
        
        $firstNames = [
            'Juan', 'Maria', 'Pedro', 'Ana', 'Jose', 'Teresa', 'Antonio', 'Carmen',
            'Manuel', 'Rosa', 'Francisco', 'Elena', 'Carlos', 'Isabel', 'Miguel', 'Lourdes',
            'Ramon', 'Patricia', 'Ricardo', 'Sofia', 'Fernando', 'Gabriela', 'Eduardo', 'Andrea'
        ];
        
        $lastNames = [
            'Dela Cruz', 'Garcia', 'Reyes', 'Ramos', 'Mendoza', 'Santos', 'Flores', 'Gonzales',
            'Bautista', 'Villanueva', 'Fernandez', 'Lopez', 'Perez', 'Castillo', 'Rivera', 'Navarro'
        ];

        $incidentsCreated = 0;

        foreach ($barangays as $barangay) {
            // Check if we have users for this barangay
            if (!isset($barangayUsers[$barangay]) || $barangayUsers[$barangay]->isEmpty()) {
                $this->command->warn("No users found for barangay: $barangay. Skipping.");
                continue;
            }

            // Create 1-3 incidents per barangay
            $incidentsPerBarangay = rand(1, 3);
            
            for ($i = 1; $i <= $incidentsPerBarangay; $i++) {
                // Pick a random user from this barangay
                $user = $barangayUsers[$barangay]->random();
                
                $incidentType = $incidentTypes[array_rand($incidentTypes)];
                $severity = $severities[array_rand($severities)];
                
                $incident = Incident::create([
                    'reported_by' => $user->id,
                    'incident_type' => $incidentType,
                    'title' => $this->generateIncidentTitle($incidentType, $barangay),
                    'description' => $this->generateDescription($incidentType),
                    'location' => "Purok " . rand(1, 8) . ", $barangay",
                    'barangay' => $barangay, // This matches the user's barangay
                    'purok' => "Purok " . rand(1, 8),
                    'incident_date' => now()->subDays(rand(1, 30))->subHours(rand(1, 24)),
                    'severity' => $severity,
                    'status' => $this->getRandomStatus(),
                    'affected_families' => rand(1, 8),
                    'affected_individuals' => rand(3, 40),
                    'casualties' => json_encode([
                        'dead' => rand(0, 2),
                        'injured' => rand(0, 5),
                        'missing' => rand(0, 1)
                    ]),
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now()->subDays(rand(0, 30)),
                ]);

                // Create families for this incident
                $familyCount = rand(1, 8);
                $totalMembers = 0;
                
                for ($familyNum = 1; $familyNum <= $familyCount; $familyNum++) {
                    $familySize = rand(1, 6);
                    $totalMembers += $familySize;
                    
                    $family = IncidentFamily::create([
                        'incident_id' => $incident->id,
                        'family_number' => $familyNum,
                        'family_size' => $familySize,
                        'evacuation_center' => rand(0, 1) ? "Evacuation Center " . rand(1, 3) : null,
                        'alternative_location' => rand(0, 1) ? "Relative's House" : null,
                        'assistance_given' => rand(0, 1) ? (rand(0, 1) ? 'F' : 'NFI') : null,
                        // NEW FIELDS:
                        'assistance_received' => rand(0, 1),
                        'food_assistance' => rand(0, 1),
                        'non_food_assistance' => rand(0, 1),
                        'shelter_assistance' => rand(0, 1),
                        'medical_assistance' => rand(0, 1),
                        'other_remarks' => rand(0, 1) ? "Relief goods distributed" : null,
                    ]);

                    // Create family members
                    $this->createFamilyMembers($family, $familySize, $firstNames, $lastNames);
                }

                // Update incident with actual counts
                $incident->update([
                    'affected_families' => $familyCount,
                    'affected_individuals' => $totalMembers
                ]);

                $incidentsCreated++;
                $this->command->info("Created incident for $barangay reported by {$user->name}");
            }
        }

        $this->command->info("Incidents with families seeded successfully! Created $incidentsCreated incidents.");
        $this->command->info('Incidents are now properly matched with barangay users.');
    }

    // ... keep all the helper methods the same (they remain unchanged) ...
    private function generateIncidentTitle($type, $barangay): string
    {
        $titles = [
            'Flood' => ["Flash Flood in $barangay", "Heavy Flooding in $barangay", "River Overflow in $barangay"],
            'Landslide' => ["Landslide Incident in $barangay", "Mudslide in $barangay", "Soil Erosion in $barangay"],
            'Fire' => ["Residential Fire in $barangay", "Commercial Fire in $barangay", "Forest Fire in $barangay"],
            'Earthquake' => ["Earthquake in $barangay", "Tremor in $barangay", "Seismic Activity in $barangay"],
            'Vehicular' => ["Road Accident in $barangay", "Vehicle Collision in $barangay", "Traffic Incident in $barangay"]
        ];

        return $titles[$type][array_rand($titles[$type])];
    }

    private function generateDescription($type): string
    {
        $descriptions = [
            'Flood' => "Heavy rainfall caused flooding in the area. Several families were affected and required evacuation.",
            'Landslide' => "Continuous rain triggered a landslide affecting residential areas. Immediate response needed.",
            'Fire' => "Fire broke out in the residential area. Firefighters responded immediately to control the situation.",
            'Earthquake' => "Earthquake measured at magnitude 4.5 caused damage to infrastructure and homes.",
            'Vehicular' => "Road accident involving multiple vehicles. Emergency services dispatched to the location."
        ];

        return $descriptions[$type];
    }

    private function getRandomStatus(): string
    {
        $statuses = ['Reported', 'Investigating', 'Resolved'];
        $weights = [4, 3, 3]; // Higher weight for 'Reported'
        $random = rand(1, array_sum($weights));
        
        $current = 0;
        foreach ($statuses as $index => $status) {
            $current += $weights[$index];
            if ($random <= $current) {
                return $status;
            }
        }
        
        return 'Reported';
    }

    private function createFamilyMembers($family, $familySize, $firstNames, $lastNames): void
    {
        $positions = ['Head (Father)', 'Head (Mother)', 'Head (Solo Parent)', 'Member'];
        $genders = ['Male', 'Female', 'LGBTQIA+ / Other (self-identified)'];
        $categories = [
            'Infant (0-6 mos)', 'Toddlers (7 mos- 2 y/o)', 'Preschooler (3-5 y/o)',
            'School Age (6-12 y/o)', 'Teen Age (13-17 y/o)', 'Adult (18-59 y/o)', 'Elderly (60 and above)'
        ];
        $civilStatuses = ['Single', 'Married', 'Widowed', 'Separated', 'Live-In/Cohabiting'];
        $ethnicities = ['CHRISTIAN', 'SUBANEN (IPs)', 'MORO'];
        $vulnerableGroups = [
            'PWD', 'Pregnant', 'Elderly', 'Lactating Mother', 'Solo parent',
            'Indigenous People', 'LGBTQIA+ Persons', 'Child-Headed Household',
            '4Ps Beneficiaries'
        ];
        $pwdTypes = [
            'Psychosocial Disability', 'Hearing Disability', 'Visual Disability',
            'Orthopedic Disability', 'Intellectual Disability'
        ];

        $familyLastName = $lastNames[array_rand($lastNames)];

        for ($memberNum = 1; $memberNum <= $familySize; $memberNum++) {
            $position = $memberNum === 1 ? $positions[array_rand(array_slice($positions, 0, 3))] : 'Member';
            $gender = $position === 'Head (Father)' ? 'Male' : 
                     ($position === 'Head (Mother)' ? 'Female' : 
                     $genders[array_rand($genders)]);
            
            $age = $this->generateAgeForCategory($categories);
            $category = $this->getCategoryByAge($age);

            $vulnerable = [];
            if (rand(0, 3) === 0) { // 25% chance of having vulnerable groups
                $vulnerableCount = rand(1, 2);
                $selectedGroups = array_rand($vulnerableGroups, min($vulnerableCount, count($vulnerableGroups)));
                $vulnerable = is_array($selectedGroups) ? 
                    array_map(fn($idx) => $vulnerableGroups[$idx], $selectedGroups) : 
                    [$vulnerableGroups[$selectedGroups]];
            }

            $pwdType = in_array('PWD', $vulnerable) ? $pwdTypes[array_rand($pwdTypes)] : null;
            $casualty = rand(0, 10) === 0 ? ['Dead', 'Injured/ill', 'Missing'][array_rand(['Dead', 'Injured/ill', 'Missing'])] : null;
            $displaced = rand(0, 1) ? 'Y' : 'N';

            IncidentFamilyMember::create([
                'family_id' => $family->id,
                'last_name' => $familyLastName,
                'first_name' => $firstNames[array_rand($firstNames)],
                'middle_name' => rand(0, 1) ? $firstNames[array_rand($firstNames)] : null,
                'position_in_family' => $position,
                'sex_gender_identity' => $gender,
                'age' => $age,
                'category' => $category,
                'civil_status' => $civilStatuses[array_rand($civilStatuses)],
                'ethnicity' => $ethnicities[array_rand($ethnicities)],
                'vulnerable_groups' => $vulnerable,
                'casualty' => $casualty,
                'displaced' => $displaced,
                'pwd_type' => $pwdType,
                // Add the new member assistance fields
                'assistance_received' => rand(0, 1),
                'food_assistance' => rand(0, 1),
                'non_food_assistance' => rand(0, 1),
                'medical_attention' => rand(0, 1),
                'psychological_support' => rand(0, 1),
                'other_remarks' => rand(0, 1) ? "Received assistance" : null,
                'created_at' => $family->created_at,
                'updated_at' => $family->updated_at,
            ]);
        }
    }

    private function generateAgeForCategory($categories): int
    {
        $category = $categories[array_rand($categories)];
        
        return match($category) {
            'Infant (0-6 mos)' => 0,              // ONLY generate age 0 for infants
            'Toddlers (7 mos- 2 y/o)' => rand(1, 2), // Generate ages 1-2 for toddlers
            'Preschooler (3-5 y/o)' => rand(3, 5),
            'School Age (6-12 y/o)' => rand(6, 12),
            'Teen Age (13-17 y/o)' => rand(13, 17),
            'Adult (18-59 y/o)' => rand(18, 59),
            'Elderly (60 and above)' => rand(60, 85),
            default => rand(18, 60)
        };
    }

    private function getCategoryByAge($age): string
    {
        return match(true) {
            $age == 0 => 'Infant (0-6 mos)',      // ONLY age 0 is infant
            $age <= 2 => 'Toddlers (7 mos- 2 y/o)', // Ages 1-2 are toddlers
            $age <= 5 => 'Preschooler (3-5 y/o)',
            $age <= 12 => 'School Age (6-12 y/o)',
            $age <= 17 => 'Teen Age (13-17 y/o)',
            $age <= 59 => 'Adult (18-59 y/o)',
            default => 'Elderly (60 and above)'
        };
    }
}