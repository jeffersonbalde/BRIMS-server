<?php
// database/seeders/IncidentSeeder.php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        // Get specific user with ID 2
        $user = User::find(2);
        
        if (!$user) {
            $this->command->error('User with ID 2 not found. Please make sure users are seeded first.');
            return;
        }

        $this->command->info("Seeding incidents for user: {$user->name} (ID: {$user->id})");

        $incidents = [
            // Incidents that CAN be edited/deleted (recent, status = Reported)
            [
                'title' => 'Recent Flood in Barangay Center',
                'description' => 'Heavy flooding in the main barangay area affecting multiple households. Water level reached 2 feet in some areas.',
                'incident_type' => 'Flood',
                'location' => 'Barangay Center, Near Municipal Hall',
                'incident_date' => Carbon::now()->subMinutes(30), // Within 1 hour - CAN EDIT
                'severity' => 'Medium',
                'status' => 'Reported',
                'affected_families' => 8,
                'affected_individuals' => 35,
                'casualties' => ['dead' => 0, 'injured' => 2, 'missing' => 0],
            ],
            [
                'title' => 'Kitchen Fire in Residential Area',
                'description' => 'Residential kitchen fire caused by LPG leak. Quickly contained by neighbors.',
                'incident_type' => 'Fire',
                'location' => 'Purok 5, Residential Area',
                'incident_date' => Carbon::now()->subMinutes(45), // Within 1 hour - CAN EDIT
                'severity' => 'High',
                'status' => 'Reported',
                'affected_families' => 1,
                'affected_individuals' => 6,
                'casualties' => ['dead' => 0, 'injured' => 1, 'missing' => 0],
            ],

            // Incidents that CANNOT be edited/deleted (older than 1 hour)
            [
                'title' => 'Landslide in Mountain Area',
                'description' => 'Minor landslide due to heavy rains blocking access road to mountain communities.',
                'incident_type' => 'Landslide',
                'location' => 'Sitio Upper Hills, Mountain Area',
                'incident_date' => Carbon::now()->subHours(3), // OLDER than 1 hour - CANNOT EDIT
                'severity' => 'Critical',
                'status' => 'Reported',
                'affected_families' => 12,
                'affected_individuals' => 48,
                'casualties' => ['dead' => 0, 'injured' => 3, 'missing' => 0],
            ],

            // Incidents that CANNOT be edited/deleted (status not "Reported")
            [
                'title' => 'Earthquake Damage Assessment',
                'description' => 'Minor earthquake caused structural damage to older buildings in commercial area.',
                'incident_type' => 'Earthquake',
                'location' => 'Commercial District, Main Road',
                'incident_date' => Carbon::now()->subMinutes(20),
                'severity' => 'High',
                'status' => 'Investigating', // NOT "Reported" - CANNOT EDIT
                'affected_families' => 15,
                'affected_individuals' => 62,
                'casualties' => ['dead' => 0, 'injured' => 4, 'missing' => 0],
                'response_actions' => 'Structural engineers dispatched for assessment',
            ],
            [
                'title' => 'Vehicular Accident on Highway',
                'description' => 'Two-vehicle collision on national highway near barangay boundary.',
                'incident_type' => 'Vehicular',
                'location' => 'National Highway, KM 125',
                'incident_date' => Carbon::now()->subHours(2),
                'severity' => 'Medium',
                'status' => 'Resolved', // NOT "Reported" - CANNOT EDIT
                'affected_families' => 2,
                'affected_individuals' => 8,
                'casualties' => ['dead' => 0, 'injured' => 3, 'missing' => 0],
                'response_actions' => 'Victims transported to hospital, road cleared',
            ],
            [
                'title' => 'Major Flooding in Low-Lying Areas',
                'description' => 'Continuous heavy rains caused major flooding in low-lying communities.',
                'incident_type' => 'Flood',
                'location' => 'Purok 1-3, Low-Lying Areas',
                'incident_date' => Carbon::now()->subDays(1),
                'severity' => 'Critical',
                'status' => 'Resolved', // Changed from 'Closed' to 'Resolved'
                'affected_families' => 25,
                'affected_individuals' => 120,
                'casualties' => ['dead' => 0, 'injured' => 8, 'missing' => 0],
                'admin_notes' => 'Relief operations completed, all affected families assisted',
            ],
        ];

        foreach ($incidents as $incidentData) {
            $incident = Incident::create(array_merge($incidentData, [
                'reported_by' => $user->id,
                'created_at' => $incidentData['incident_date'],
                'updated_at' => $incidentData['incident_date'],
            ]));

            $this->command->info("Created: {$incident->title} - Status: {$incident->status} - Can Edit: " . ($incident->can_barangay_edit ? 'YES' : 'NO'));
        }

        $this->command->info('');
        $this->command->info('=== INCIDENT SEEDER SUMMARY ===');
        $this->command->info('2 incidents CAN be edited/deleted (recent + status=Reported)');
        $this->command->info('4 incidents CANNOT be edited/deleted (old or status≠Reported)');
        $this->command->info('Total: 6 incidents created for user ID 2');
        $this->command->info('================================');
    }
}