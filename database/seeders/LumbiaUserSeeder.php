<?php
// database/seeders/LumbiaUserSeeder.php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\IncidentFamily;
use App\Models\IncidentFamilyMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LumbiaUserSeeder extends Seeder
{
    public function run(): void
    {
        // First, let's ensure user ID 24 exists and is from Lumbia
        $lumbiaUser = User::find(24);
        
        if (!$lumbiaUser) {
            $this->command->warn('User ID 24 not found. Creating Lumbia user...');
            
            $lumbiaUser = User::create([
                'id' => 24,
                'name' => 'Lumbia Barangay Official',
                'email' => 'lumbia@barangay.com',
                'password' => Hash::make('password123'),
                'role' => 'barangay',
                'barangay_name' => 'LUMBIA',
                'municipality' => 'Municipality',
                'position' => 'Barangay Captain',
                'contact' => '09123456789',
                'is_approved' => true,
                'status' => 'approved',
                'is_active' => true,
                'email_verified_at' => now(),
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info('Lumbia user created with ID 24');
        } else {
            // Update the user to ensure it's from Lumbia
            $lumbiaUser->update([
                'barangay_name' => 'LUMBIA',
                'is_approved' => true,
                'status' => 'approved',
                'is_active' => true,
            ]);
            $this->command->info('Updated existing user ID 24 to Lumbia barangay');
        }

        // Clear any existing incidents for Lumbia to start fresh
        Incident::where('barangay', 'LUMBIA')->orWhere('reported_by', 24)->delete();

        $this->command->info('Seeding incidents for Lumbia barangay...');

        // Create multiple incidents for Lumbia
        $incidents = [
            [
                'type' => 'Flood',
                'title' => 'Flash Flood in Lumbia Purok 3',
                'description' => 'Heavy rainfall caused flash flooding in Purok 3, affecting multiple families near the river area.',
                'location' => 'Purok 3, LUMBIA',
                'purok' => 'Purok 3',
                'severity' => 'High',
                'families_count' => 5,
                'incident_date' => now()->subDays(15),
            ],
            [
                'type' => 'Fire',
                'title' => 'Residential Fire in Lumbia Purok 5',
                'description' => 'Electrical fire damaged two residential houses in Purok 5. Families evacuated safely.',
                'location' => 'Purok 5, LUMBIA',
                'purok' => 'Purok 5',
                'severity' => 'Critical',
                'families_count' => 2,
                'incident_date' => now()->subDays(8),
            ],
            [
                'type' => 'Landslide',
                'title' => 'Landslide in Lumbia Hilly Area',
                'description' => 'Continuous rain triggered minor landslide affecting access road and one household.',
                'location' => 'Hilly Area, LUMBIA',
                'purok' => 'Purok 7',
                'severity' => 'Medium',
                'families_count' => 1,
                'incident_date' => now()->subDays(3),
            ],
            [
                'type' => 'Vehicular',
                'title' => 'Road Accident in Lumbia Main Road',
                'description' => 'Two vehicles collided on the main road, causing temporary road closure.',
                'location' => 'Main Road, LUMBIA',
                'purok' => 'Purok 1',
                'severity' => 'Medium',
                'families_count' => 3,
                'incident_date' => now()->subDays(1),
            ]
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

        $totalIncidentsCreated = 0;
        $totalFamiliesCreated = 0;
        $totalMembersCreated = 0;

        foreach ($incidents as $incidentData) {
            $incident = Incident::create([
                'reported_by' => $lumbiaUser->id,
                'incident_type' => $incidentData['type'],
                'title' => $incidentData['title'],
                'description' => $incidentData['description'],
                'location' => $incidentData['location'],
                'barangay' => 'LUMBIA',
                'purok' => $incidentData['purok'],
                'incident_date' => $incidentData['incident_date'],
                'severity' => $incidentData['severity'],
                'status' => $this->getRandomStatus(),
                'affected_families' => $incidentData['families_count'],
                'affected_individuals' => 0, // Will be updated after creating members
                'casualties' => json_encode([
                    'dead' => rand(0, 1),
                    'injured' => rand(0, 3),
                    'missing' => rand(0, 1)
                ]),
                'created_at' => $incidentData['incident_date'],
                'updated_at' => $incidentData['incident_date']->addHours(rand(1, 24)),
            ]);

            $totalIncidentsCreated++;
            $incidentTotalMembers = 0;

            // Create families for this incident
            for ($familyNum = 1; $familyNum <= $incidentData['families_count']; $familyNum++) {
                $familySize = rand(3, 6);
                $incidentTotalMembers += $familySize;
                
                $family = IncidentFamily::create([
                    'incident_id' => $incident->id,
                    'family_number' => $familyNum,
                    'family_size' => $familySize,
                    'evacuation_center' => rand(0, 1) ? "Lumbia Evacuation Center" : null,
                    'alternative_location' => rand(0, 1) ? "Relative's House" : null,
                    'assistance_given' => rand(0, 1) ? (rand(0, 1) ? 'F' : 'NFI') : null,
                    'assistance_received' => rand(0, 1),
                    'food_assistance' => rand(0, 1),
                    'non_food_assistance' => rand(0, 1),
                    'shelter_assistance' => rand(0, 1),
                    'medical_assistance' => rand(0, 1),
                    'other_remarks' => rand(0, 1) ? "Emergency relief provided" : null,
                    'created_at' => $incident->created_at,
                    'updated_at' => $incident->updated_at,
                ]);

                $totalFamiliesCreated++;
                $this->createFamilyMembersForLumbia($family, $familySize, $firstNames, $lastNames);
                $totalMembersCreated += $familySize;
            }

            // Update incident with actual member count
            $incident->update([
                'affected_individuals' => $incidentTotalMembers
            ]);

            $this->command->info("Created {$incidentData['type']} incident for Lumbia with {$incidentData['families_count']} families and $incidentTotalMembers members");
        }

        $this->command->info("✅ Lumbia seeding completed!");
        $this->command->info("📊 Summary:");
        $this->command->info("   - Incidents created: $totalIncidentsCreated");
        $this->command->info("   - Families created: $totalFamiliesCreated");
        $this->command->info("   - Family members created: $totalMembersCreated");
        $this->command->info("👤 User ID 24 (Lumbia) now has data for testing dashboard and reports.");
    }

    private function getRandomStatus(): string
    {
        $statuses = ['Reported', 'Investigating', 'Resolved'];
        return $statuses[array_rand($statuses)];
    }

    private function createFamilyMembersForLumbia($family, $familySize, $firstNames, $lastNames): void
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

            $casualty = rand(0, 10) === 0 ? ['Dead', 'Injured/ill', 'Missing'][array_rand(['Dead', 'Injured/ill', 'Missing'])] : null;
            $displaced = $family->evacuation_center ? 'Y' : (rand(0, 1) ? 'Y' : 'N');

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
                'pwd_type' => in_array('PWD', $vulnerable) ? ['Psychosocial Disability', 'Hearing Disability', 'Visual Disability'][array_rand([0,1,2])] : null,
                'assistance_received' => rand(0, 1),
                'food_assistance' => rand(0, 1),
                'non_food_assistance' => rand(0, 1),
                'medical_attention' => rand(0, 1),
                'psychological_support' => rand(0, 1),
                'other_remarks' => rand(0, 1) ? "Family affected by incident" : null,
                'created_at' => $family->created_at,
                'updated_at' => $family->updated_at,
            ]);
        }
    }

    private function generateAgeForCategory($categories): int
    {
        $category = $categories[array_rand($categories)];
        
        return match($category) {
            'Infant (0-6 mos)' => 0,
            'Toddlers (7 mos- 2 y/o)' => rand(1, 2),
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
            $age == 0 => 'Infant (0-6 mos)',
            $age <= 2 => 'Toddlers (7 mos- 2 y/o)',
            $age <= 5 => 'Preschooler (3-5 y/o)',
            $age <= 12 => 'School Age (6-12 y/o)',
            $age <= 17 => 'Teen Age (13-17 y/o)',
            $age <= 59 => 'Adult (18-59 y/o)',
            default => 'Elderly (60 and above)'
        };
    }
}