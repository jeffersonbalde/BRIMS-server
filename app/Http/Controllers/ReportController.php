<?php
// app/Http/Controllers/ReportController.php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentFamily;
use App\Models\IncidentFamilyMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function generateMunicipalReport(Request $request)
    {
        try {
            $type = $request->get('type', 'population_detailed');

            if ($type === 'population_detailed') {
                return $this->generateDetailedPopulationReport($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid report type'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Generate municipal report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate municipal report'
            ], 500);
        }
    }

    public function generateBarangayReport(Request $request)
    {
        try {
            $type = $request->get('type', 'population_detailed');

            if ($type === 'population_detailed') {
                return $this->generateDetailedPopulationReport($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid report type'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Generate barangay report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate barangay report'
            ], 500);
        }
    }

    public function generateDetailedPopulationReport(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $barangay = $request->get('barangay', 'all');
            $incidentType = $request->get('incident_type', 'all');
            $incidentId = $request->get('incident_id', 'all');
            $user = $request->user();

            // Get accurate counts from database
            $totalStats = $this->getAccurateCountsFromDatabase($dateFrom, $dateTo, $barangay, $incidentType, $incidentId, $user);
            $detailedStats = $this->getDetailedStatisticsFromDatabase($dateFrom, $dateTo, $barangay, $incidentType, $incidentId, $user);

            $reportData = [
                'population_affected' => [
                    'no_of_families' => $totalStats['total_families'],
                    'no_of_persons' => $totalStats['total_persons'],
                    'displaced_families' => $totalStats['displaced_families'],
                    'displaced_persons' => $totalStats['displaced_persons'],
                    'families_requiring_assistance' => $totalStats['families_requiring_assistance'],
                    'families_assisted' => $totalStats['families_assisted'],
                    'percentage_families_assisted' => $totalStats['families_requiring_assistance'] > 0
                        ? round(($totalStats['families_assisted'] / $totalStats['families_requiring_assistance']) * 100, 1)
                        : 0,
                ],
                'gender_breakdown' => $detailedStats['gender_breakdown'],
                'civil_status' => $detailedStats['civil_status'],
                'vulnerable_groups' => $detailedStats['vulnerable_groups'],
                'age_categories' => $detailedStats['age_categories'],
                'casualties' => $detailedStats['casualties'],
                'incident_types' => $detailedStats['incident_types'],
                'selected_incident' => null
            ];

            // Get selected incident details if specific incident is chosen
            if ($incidentId && $incidentId !== 'all') {
                $selectedIncident = Incident::find($incidentId);
                if ($selectedIncident) {
                    $reportData['selected_incident'] = [
                        'id' => $selectedIncident->id,
                        'title' => $selectedIncident->title,
                        'incident_type' => $selectedIncident->incident_type,
                        'incident_date' => $selectedIncident->incident_date,
                        'barangay' => $selectedIncident->barangay
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $reportData,
                'summary' => [
                    'total_incidents' => $totalStats['total_incidents'],
                    'total_families' => $totalStats['total_families'],
                    'total_persons' => $totalStats['total_persons'],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Generate detailed population report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate detailed population report'
            ], 500);
        }
    }

    private function getAccurateCountsFromDatabase($dateFrom, $dateTo, $barangay, $incidentType, $incidentId, $user)
    {
        // Base query for incidents - JOIN with users to get user's barangay
        $incidentQuery = DB::table('incidents')
            ->join('users', 'incidents.reported_by', '=', 'users.id');

        if ($dateFrom && $dateTo) {
            $incidentQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        // Filter by USER'S barangay, not incident's barangay
        if ($user->role === 'barangay') {
            $incidentQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $incidentQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $incidentQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $incidentQuery->where('incidents.id', $incidentId);
        }

        $totalIncidents = $incidentQuery->count();

        // Total families (distinct count) - JOIN with users
        $familyQuery = DB::table('incident_families')
            ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
            ->join('users', 'incidents.reported_by', '=', 'users.id');

        if ($dateFrom && $dateTo) {
            $familyQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        if ($user->role === 'barangay') {
            $familyQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $familyQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $familyQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $familyQuery->where('incidents.id', $incidentId);
        }

        $totalFamilies = $familyQuery->distinct('incident_families.id')->count('incident_families.id');

        // Total persons (distinct count) - JOIN with users
        $personQuery = DB::table('incident_family_members')
            ->join('incident_families', 'incident_family_members.family_id', '=', 'incident_families.id')
            ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
            ->join('users', 'incidents.reported_by', '=', 'users.id');

        if ($dateFrom && $dateTo) {
            $personQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        if ($user->role === 'barangay') {
            $personQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $personQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $personQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $personQuery->where('incidents.id', $incidentId);
        }

        $totalPersons = $personQuery->distinct('incident_family_members.id')->count('incident_family_members.id');

        // Displaced families - JOIN with users
        $displacedFamiliesQuery = DB::table('incident_families')
            ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
            ->join('users', 'incidents.reported_by', '=', 'users.id')
            ->whereNotNull('incident_families.evacuation_center');

        if ($dateFrom && $dateTo) {
            $displacedFamiliesQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        if ($user->role === 'barangay') {
            $displacedFamiliesQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $displacedFamiliesQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $displacedFamiliesQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $displacedFamiliesQuery->where('incidents.id', $incidentId);
        }

        $displacedFamilies = $displacedFamiliesQuery->distinct('incident_families.id')->count('incident_families.id');

        // Displaced persons - JOIN with users
        $displacedPersonsQuery = DB::table('incident_family_members')
            ->join('incident_families', 'incident_family_members.family_id', '=', 'incident_families.id')
            ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
            ->join('users', 'incidents.reported_by', '=', 'users.id')
            ->where('incident_family_members.displaced', 'Y');

        if ($dateFrom && $dateTo) {
            $displacedPersonsQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        if ($user->role === 'barangay') {
            $displacedPersonsQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $displacedPersonsQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $displacedPersonsQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $displacedPersonsQuery->where('incidents.id', $incidentId);
        }

        $displacedPersons = $displacedPersonsQuery->distinct('incident_family_members.id')->count('incident_family_members.id');

        // Families assisted - Check multiple assistance fields
        $familiesAssistedQuery = DB::table('incident_families')
            ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
            ->join('users', 'incidents.reported_by', '=', 'users.id')
            ->where(function ($query) {
                $query->where('incident_families.assistance_received', true)
                    ->orWhere('incident_families.food_assistance', true)
                    ->orWhere('incident_families.non_food_assistance', true)
                    ->orWhere('incident_families.shelter_assistance', true)
                    ->orWhere('incident_families.medical_assistance', true);
            });

        if ($dateFrom && $dateTo) {
            $familiesAssistedQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        if ($user->role === 'barangay') {
            $familiesAssistedQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $familiesAssistedQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $familiesAssistedQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $familiesAssistedQuery->where('incidents.id', $incidentId);
        }

        $familiesAssisted = $familiesAssistedQuery->distinct('incident_families.id')->count('incident_families.id');

        return [
            'total_incidents' => $totalIncidents,
            'total_families' => $totalFamilies,
            'total_persons' => $totalPersons,
            'displaced_families' => $displacedFamilies,
            'displaced_persons' => $displacedPersons,
            'families_assisted' => $familiesAssisted,
            'families_requiring_assistance' => $totalFamilies,
        ];
    }

    private function getDetailedStatisticsFromDatabase($dateFrom, $dateTo, $barangay, $incidentType, $incidentId, $user)
    {
        $memberQuery = DB::table('incident_family_members')
            ->join('incident_families', 'incident_family_members.family_id', '=', 'incident_families.id')
            ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
            ->join('users', 'incidents.reported_by', '=', 'users.id')
            ->select('incident_family_members.*');

        if ($dateFrom && $dateTo) {
            $memberQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        if ($user->role === 'barangay') {
            $memberQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $memberQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $memberQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $memberQuery->where('incidents.id', $incidentId);
        }

        // Gender breakdown
        $genders = $memberQuery
            ->select('sex_gender_identity', DB::raw('COUNT(DISTINCT incident_family_members.id) as count'))
            ->groupBy('sex_gender_identity')
            ->get();

        $genderBreakdown = ['male' => 0, 'female' => 0, 'lgbtqia' => 0];
        foreach ($genders as $gender) {
            $genderType = strtolower(trim($gender->sex_gender_identity));

            if (str_contains($genderType, 'male') && !str_contains($genderType, 'female')) {
                $genderBreakdown['male'] += $gender->count;
            } elseif (str_contains($genderType, 'female')) {
                $genderBreakdown['female'] += $gender->count;
            } else {
                $genderBreakdown['lgbtqia'] += $gender->count;
            }
        }

        // Civil status
        $civilStatuses = $memberQuery
            ->select('civil_status', DB::raw('COUNT(DISTINCT incident_family_members.id) as count'))
            ->groupBy('civil_status')
            ->get();

        $civilStatus = ['single' => 0, 'married' => 0, 'widowed' => 0, 'separated' => 0, 'live_in' => 0];
        foreach ($civilStatuses as $status) {
            $statusType = strtolower(trim($status->civil_status));

            if (str_contains($statusType, 'single')) {
                $civilStatus['single'] += $status->count;
            } elseif (str_contains($statusType, 'married')) {
                $civilStatus['married'] += $status->count;
            } elseif (str_contains($statusType, 'widow')) {
                $civilStatus['widowed'] += $status->count;
            } elseif (str_contains($statusType, 'separated')) {
                $civilStatus['separated'] += $status->count;
            } elseif (str_contains($statusType, 'live-in') || str_contains($statusType, 'cohabiting')) {
                $civilStatus['live_in'] += $status->count;
            } else {
                $civilStatus['single'] += $status->count;
            }
        }

        // **FIXED: Age Categories - Use CATEGORY field instead of AGE field**
        $ageCategories = $this->getAccurateAgeCategoriesFromCategory($memberQuery);

        // FIXED: Vulnerable groups - use the new accurate counting method
        $vulnerableGroups = $this->getVulnerableGroupsCounts($memberQuery);

        // Casualties
        $casualtiesData = $memberQuery
            ->select('casualty', DB::raw('COUNT(DISTINCT incident_family_members.id) as count'))
            ->whereNotNull('casualty')
            ->where('casualty', '!=', '')
            ->groupBy('casualty')
            ->get();

        $casualties = ['dead' => 0, 'injured_ill' => 0, 'missing' => 0];
        foreach ($casualtiesData as $casualty) {
            $casualtyType = strtolower(trim($casualty->casualty));

            if (str_contains($casualtyType, 'dead') || str_contains($casualtyType, 'died')) {
                $casualties['dead'] += $casualty->count;
            } elseif (str_contains($casualtyType, 'injured') || str_contains($casualtyType, 'ill')) {
                $casualties['injured_ill'] += $casualty->count;
            } elseif (str_contains($casualtyType, 'missing')) {
                $casualties['missing'] += $casualty->count;
            }
        }

        // Incident types
        $incidentTypesQuery = DB::table('incidents')
            ->join('users', 'incidents.reported_by', '=', 'users.id')
            ->select('incidents.incident_type', DB::raw('COUNT(*) as count'));

        if ($dateFrom && $dateTo) {
            $incidentTypesQuery->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
        }

        if ($user->role === 'barangay') {
            $incidentTypesQuery->where('incidents.reported_by', $user->id);
        } else {
            if ($barangay !== 'all') {
                $incidentTypesQuery->where('users.barangay_name', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $incidentTypesQuery->where('incidents.incident_type', $incidentType);
        }
        if ($incidentId !== 'all') {
            $incidentTypesQuery->where('incidents.id', $incidentId);
        }

        $incidentTypes = $incidentTypesQuery
            ->groupBy('incidents.incident_type')
            ->pluck('count', 'incidents.incident_type')
            ->toArray();

        return [
            'gender_breakdown' => $genderBreakdown,
            'civil_status' => $civilStatus,
            'vulnerable_groups' => $vulnerableGroups,
            'age_categories' => $ageCategories,
            'casualties' => $casualties,
            'incident_types' => $incidentTypes,
        ];
    }







    /**
     * FIXED: Calculate age categories with detailed debugging
     */
    private function getAccurateAgeCategoriesFromCategory($memberQuery)
    {
        try {
            // Get ALL members with their ages first for debugging
            $allMembers = DB::table('incident_family_members')
                ->join('incident_families', 'incident_family_members.family_id', '=', 'incident_families.id')
                ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
                ->join('users', 'incidents.reported_by', '=', 'users.id')
                ->select('incident_family_members.id', 'incident_family_members.first_name', 'incident_family_members.last_name', 'incident_family_members.age');

            // Apply the same filters as the main query
            $dateFrom = request()->get('date_from');
            $dateTo = request()->get('date_to');
            $barangay = request()->get('barangay', 'all');
            $incidentType = request()->get('incident_type', 'all');
            $incidentId = request()->get('incident_id', 'all');
            $user = request()->user();

            if ($dateFrom && $dateTo) {
                $allMembers->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
            }

            if ($user->role === 'barangay') {
                $allMembers->where('incidents.reported_by', $user->id);
            } else {
                if ($barangay !== 'all') {
                    $allMembers->where('users.barangay_name', $barangay);
                }
            }

            if ($incidentType !== 'all') {
                $allMembers->where('incidents.incident_type', $incidentType);
            }
            if ($incidentId !== 'all') {
                $allMembers->where('incidents.id', $incidentId);
            }

            $members = $allMembers->get();

            // Debug: Log all members with their ages
            $ageDebug = [];
            foreach ($members as $member) {
                $ageDebug[] = [
                    'name' => $member->first_name . ' ' . $member->last_name,
                    'age' => $member->age,
                    'category' => $this->getAgeCategory($member->age)
                ];
            }
            Log::info('ALL MEMBERS WITH AGES:', $ageDebug);

            // Manually count by age
            $infant = 0;
            $toddlers = 0;
            $preschooler = 0;
            $school_age = 0;
            $teen_age = 0;
            $adult = 0;
            $elderly_age = 0;

            foreach ($members as $member) {
                $age = (int) $member->age;

                if ($age == 0) {
                    $infant++;
                } elseif ($age >= 1 && $age <= 2) {
                    $toddlers++;
                } elseif ($age >= 3 && $age <= 5) {
                    $preschooler++;
                } elseif ($age >= 6 && $age <= 12) {
                    $school_age++;
                } elseif ($age >= 13 && $age <= 17) {
                    $teen_age++;
                } elseif ($age >= 18 && $age <= 59) {
                    $adult++;
                } elseif ($age >= 60) {
                    $elderly_age++;
                }
            }

            $result = [
                'infant' => $infant,
                'toddlers' => $toddlers,
                'preschooler' => $preschooler,
                'school_age' => $school_age,
                'teen_age' => $teen_age,
                'adult' => $adult,
                'elderly_age' => $elderly_age,
            ];

            // Log detailed results
            Log::info('DETAILED AGE CALCULATION:', [
                'total_members' => $members->count(),
                'result' => $result,
                'age_distribution' => $members->groupBy('age')->map->count()
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Detailed age calculation error: ' . $e->getMessage());

            return [
                'infant' => 0,
                'toddlers' => 0,
                'preschooler' => 0,
                'school_age' => 0,
                'teen_age' => 0,
                'adult' => 0,
                'elderly_age' => 0,
            ];
        }
    }

    /**
     * Helper method to determine age category
     */
    private function getAgeCategory($age)
    {
        $age = (int) $age;
        if ($age == 0) return 'infant';
        if ($age <= 2) return 'toddlers';
        if ($age <= 5) return 'preschooler';
        if ($age <= 12) return 'school_age';
        if ($age <= 17) return 'teen_age';
        if ($age <= 59) return 'adult';
        return 'elderly_age';
    }

    /**
     * Simple manual calculation as fallback using integer ages
     */
    private function calculateAgeCategoriesManually($memberQuery)
    {
        $members = (clone $memberQuery)
            ->select('age')
            ->get();

        $ageGroups = [
            'infant' => 0,
            'toddlers' => 0,
            'preschooler' => 0,
            'school_age' => 0,
            'teen_age' => 0,
            'adult' => 0,
            'elderly_age' => 0,
        ];

        foreach ($members as $member) {
            $age = (int) $member->age;

            if ($age == 0) { // 0 years (infant)
                $ageGroups['infant']++;
            } elseif ($age <= 2) { // 1-2 years (toddlers)
                $ageGroups['toddlers']++;
            } elseif ($age <= 5) { // 3-5 years (preschooler)
                $ageGroups['preschooler']++;
            } elseif ($age <= 12) { // 6-12 years (school age)
                $ageGroups['school_age']++;
            } elseif ($age <= 17) { // 13-17 years (teen age)
                $ageGroups['teen_age']++;
            } elseif ($age <= 59) { // 18-59 years (adult)
                $ageGroups['adult']++;
            } else { // 60+ years (elderly)
                $ageGroups['elderly_age']++;
            }
        }

        Log::info('Manual age calculation result:', $ageGroups);

        return $ageGroups;
    }

    /**
     * Improved fallback method for age categories with better mapping
     */
    private function getFallbackAgeCategories($memberQuery)
    {
        $categoryCounts = (clone $memberQuery)
            ->select('category', DB::raw('COUNT(DISTINCT incident_family_members.id) as count'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->get()
            ->pluck('count', 'category')
            ->toArray();

        return $this->mapCategoriesToAgeGroups($categoryCounts);
    }

    /**
     * Map category names to age groups with exact matching
     */
    private function mapCategoriesToAgeGroups($categoryCounts)
    {
        $ageGroups = [
            'infant' => 0,
            'toddlers' => 0,
            'preschooler' => 0,
            'school_age' => 0,
            'teen_age' => 0,
            'adult' => 0,
            'elderly_age' => 0,
        ];

        foreach ($categoryCounts as $category => $count) {
            $category = trim($category);

            // Exact matching based on your database values
            switch ($category) {
                case 'Infant (0-6 mos)':
                    $ageGroups['infant'] += $count;
                    break;
                case 'Toddlers (7 mos- 2 y/o)':
                    $ageGroups['toddlers'] += $count;
                    break;
                case 'Preschooler (3-5 y/o)':
                    $ageGroups['preschooler'] += $count;
                    break;
                case 'School Age (6-12 y/o)':
                    $ageGroups['school_age'] += $count;
                    break;
                case 'Teen Age (13-17 y/o)':
                    $ageGroups['teen_age'] += $count;
                    break;
                case 'Adult (18-59 y/o)':
                    $ageGroups['adult'] += $count;
                    break;
                case 'Elderly (60 and above)':
                    $ageGroups['elderly_age'] += $count;
                    break;
                default:
                    // If there are any unexpected categories, log them
                    Log::warning('Unexpected age category found: ' . $category);
                    $ageGroups['adult'] += $count; // Default to adult
                    break;
            }
        }

        return $ageGroups;
    }








    /**
     * FIXED: Count vulnerable groups from JSON array
     */
    private function countVulnerableGroup($query, $groupName)
    {
        $baseQuery = clone $query;

        // Count members where the vulnerable_groups JSON array contains the group name
        return $baseQuery->where(function ($q) use ($groupName) {
            // Handle different JSON formats and exact matching
            $q->where('vulnerable_groups', 'like', '%"' . $groupName . '"%')  // JSON array format
                ->orWhere('vulnerable_groups', 'like', '%' . $groupName . '%'); // Fallback for string format
        })->distinct()->count('incident_family_members.id');
    }




    /**
     * NUCLEAR FIX: Simple and accurate vulnerable groups counting
     */
    private function getVulnerableGroupsCounts($memberQuery)
    {
        try {
            // Get ALL members with their vulnerable groups data
            $allMembers = DB::table('incident_family_members')
                ->join('incident_families', 'incident_family_members.family_id', '=', 'incident_families.id')
                ->join('incidents', 'incident_families.incident_id', '=', 'incidents.id')
                ->join('users', 'incidents.reported_by', '=', 'users.id')
                ->select('incident_family_members.id', 'incident_family_members.vulnerable_groups');

            // Apply the same filters as the main query
            $dateFrom = request()->get('date_from');
            $dateTo = request()->get('date_to');
            $barangay = request()->get('barangay', 'all');
            $incidentType = request()->get('incident_type', 'all');
            $incidentId = request()->get('incident_id', 'all');
            $user = request()->user();

            if ($dateFrom && $dateTo) {
                $allMembers->whereBetween('incidents.incident_date', [$dateFrom, $dateTo]);
            }

            if ($user->role === 'barangay') {
                $allMembers->where('incidents.reported_by', $user->id);
            } else {
                if ($barangay !== 'all') {
                    $allMembers->where('users.barangay_name', $barangay);
                }
            }

            if ($incidentType !== 'all') {
                $allMembers->where('incidents.incident_type', $incidentType);
            }
            if ($incidentId !== 'all') {
                $allMembers->where('incidents.id', $incidentId);
            }

            $members = $allMembers->get();

            // Initialize counts
            $counts = [
                'pwd' => 0,
                'pregnant' => 0,
                'elderly' => 0,
                'lactating_mother' => 0,
                'solo_parent' => 0,
                'indigenous_people' => 0,
                'lgbtqia_persons' => 0,
                'child_headed_household' => 0,
                'victim_gbv' => 0,
                '4ps_beneficiaries' => 0,
                'single_headed_family' => 0,
            ];

            // Count manually - SIMPLE AND DIRECT
            foreach ($members as $member) {
                $vulnerableGroups = $member->vulnerable_groups;

                if (!$vulnerableGroups) continue;

                // Convert to array if it's a JSON string
                if (is_string($vulnerableGroups)) {
                    $groups = json_decode($vulnerableGroups, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // If not valid JSON, try to parse as string
                        $groups = [$vulnerableGroups];
                    }
                } else {
                    $groups = (array) $vulnerableGroups;
                }

                if (!is_array($groups)) continue;

                // SIMPLE STRING MATCHING - NO COMPLEX LOGIC
                foreach ($groups as $group) {
                    $group = trim(strtolower($group));

                    if (str_contains($group, 'pwd')) $counts['pwd']++;
                    if (str_contains($group, 'pregnant')) $counts['pregnant']++;
                    if (str_contains($group, 'elderly')) $counts['elderly']++;
                    if (str_contains($group, 'lactating')) $counts['lactating_mother']++;
                    if (str_contains($group, 'solo parent') || str_contains($group, 'soloparent')) $counts['solo_parent']++;
                    if (str_contains($group, 'indigenous')) $counts['indigenous_people']++;
                    if (str_contains($group, 'lgbtqia')) $counts['lgbtqia_persons']++;
                    if (str_contains($group, 'child-headed') || str_contains($group, 'child headed')) $counts['child_headed_household']++;
                    if (str_contains($group, 'gender-based') || str_contains($group, 'gbv')) $counts['victim_gbv']++;
                    if (str_contains($group, '4ps') || str_contains($group, '4p')) $counts['4ps_beneficiaries']++;
                    if (str_contains($group, 'single headed') || str_contains($group, 'single-headed')) $counts['single_headed_family']++;
                }
            }

            return $counts;
        } catch (\Exception $e) {
            Log::error('Nuclear vulnerable groups count error: ' . $e->getMessage());

            // Return zeros if there's any error
            return [
                'pwd' => 0,
                'pregnant' => 0,
                'elderly' => 0,
                'lactating_mother' => 0,
                'solo_parent' => 0,
                'indigenous_people' => 0,
                'lgbtqia_persons' => 0,
                'child_headed_household' => 0,
                'victim_gbv' => 0,
                '4ps_beneficiaries' => 0,
                'single_headed_family' => 0,
            ];
        }
    }





















    public function getIncidentsForDropdown(Request $request)
    {
        try {
            $user = $request->user();
            $barangay = $request->get('barangay', 'all');
            $incidentType = $request->get('incident_type', 'all');

            $query = Incident::join('users', 'incidents.reported_by', '=', 'users.id')
                ->select(
                    'incidents.id',
                    'incidents.title',
                    'incidents.incident_type',
                    'incidents.incident_date',
                    'users.barangay_name as barangay'
                );

            if ($user->role === 'barangay') {
                $query->where('incidents.reported_by', $user->id);
            } else {
                if ($barangay !== 'all') {
                    $query->where('users.barangay_name', $barangay);
                }
            }

            if ($incidentType !== 'all') {
                $query->where('incidents.incident_type', $incidentType);
            }

            $incidents = $query->orderBy('incidents.incident_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'incidents' => $incidents
            ]);
        } catch (\Exception $e) {
            Log::error('Get incidents for dropdown error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch incidents'
            ], 500);
        }
    }




/**
 * Generate accurate incidents report
 */
public function generateIncidentsReport(Request $request)
{
    try {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $barangay = $request->get('barangay', 'all');
        $incidentType = $request->get('incident_type', 'all');
        $user = $request->user();

        // Get incidents with families data for accurate counting
        $query = Incident::with(['families', 'families.members']);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('incident_date', [$dateFrom, $dateTo]);
        }

        // Filter by barangay - use the incident's barangay field, not user's barangay
        if ($user->role === 'barangay') {
            $query->where('barangay', $user->barangay_name);
        } else {
            if ($barangay !== 'all') {
                $query->where('barangay', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $query->where('incident_type', $incidentType);
        }

        $incidents = $query->get();

        // Calculate accurate counts
        $totalIncidents = $incidents->count();
        
        // Active incidents = Reported + Investigating (exclude Resolved)
        $activeIncidents = $incidents->whereIn('status', ['Reported', 'Investigating'])->count();
        $resolvedIncidents = $incidents->where('status', 'Resolved')->count();
        $highCriticalIncidents = $incidents->whereIn('severity', ['High', 'Critical'])->count();

        // Incident types breakdown
        $incidentTypes = $incidents->groupBy('incident_type')
            ->map(function($group) {
                return $group->count();
            })->toArray();

        // Recent incidents (last 10)
        $recentIncidents = $incidents->sortByDesc('incident_date')
            ->take(10)
            ->map(function($incident) {
                return [
                    'title' => $incident->title,
                    'incident_type' => $incident->incident_type,
                    'incident_date' => $incident->incident_date,
                    'status' => $incident->status,
                    'severity' => $incident->severity
                ];
            })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_incidents' => $totalIncidents,
                'active_incidents' => $activeIncidents,
                'resolved_incidents' => $resolvedIncidents,
                'high_critical_incidents' => $highCriticalIncidents,
                'incident_types' => $incidentTypes,
                'recent_incidents' => $recentIncidents,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Generate incidents report error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate incidents report: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Generate accurate summary report
 */
public function generateSummaryReport(Request $request)
{
    try {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $barangay = $request->get('barangay', 'all');
        $incidentType = $request->get('incident_type', 'all');
        $user = $request->user();

        // Get incidents with families and members for accurate counting
        $query = Incident::with(['families', 'families.members']);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('incident_date', [$dateFrom, $dateTo]);
        }

        // Filter by barangay - use the incident's barangay field, not user's barangay
        if ($user->role === 'barangay') {
            $query->where('barangay', $user->barangay_name);
        } else {
            if ($barangay !== 'all') {
                $query->where('barangay', $barangay);
            }
        }

        if ($incidentType !== 'all') {
            $query->where('incident_type', $incidentType);
        }

        $incidents = $query->get();

        // Calculate accurate statistics from the loaded relationships
        $totalIncidents = $incidents->count();

        // Count families and persons from the loaded relationships
        $totalFamilies = 0;
        $totalPersons = 0;
        $displacedFamilies = 0;
        $displacedPersons = 0;
        $familiesAssisted = 0;

        foreach ($incidents as $incident) {
            $totalFamilies += $incident->families->count();
            $totalPersons += $incident->families->sum(function ($family) {
                return $family->members->count();
            });

            // Count displaced families (those with evacuation center)
            $displacedFamilies += $incident->families->whereNotNull('evacuation_center')->count();

            // Count displaced persons and assisted families
            foreach ($incident->families as $family) {
                $displacedPersons += $family->members->where('displaced', 'Y')->count();

                // Check if family received any type of assistance
                if ($family->assistance_received || 
                    $family->food_assistance || 
                    $family->non_food_assistance || 
                    $family->shelter_assistance || 
                    $family->medical_assistance) {
                    $familiesAssisted++;
                }
            }
        }

        $resolvedIncidents = $incidents->where('status', 'Resolved')->count();
        $resolutionRate = $totalIncidents > 0 ? round(($resolvedIncidents / $totalIncidents) * 100, 1) : 0;
        $assistanceCoverage = $totalFamilies > 0 ? round(($familiesAssisted / $totalFamilies) * 100, 1) : 0;

        // Incident overview by type with accurate family and person counts
        $incidentOverview = [];
        foreach ($incidents->groupBy('incident_type') as $type => $typeIncidents) {
            $typeFamilies = 0;
            $typePersons = 0;

            foreach ($typeIncidents as $incident) {
                $typeFamilies += $incident->families->count();
                $typePersons += $incident->families->sum(function ($family) {
                    return $family->members->count();
                });
            }

            $incidentOverview[$type] = [
                'count' => $typeIncidents->count(),
                'families' => $typeFamilies,
                'persons' => $typePersons,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_incidents' => $totalIncidents,
                'total_families' => $totalFamilies,
                'total_persons' => $totalPersons,
                'resolution_rate' => $resolutionRate,
                'displaced_families' => $displacedFamilies,
                'displaced_persons' => $displacedPersons,
                'families_assisted' => $familiesAssisted,
                'assistance_coverage' => $assistanceCoverage,
                'incident_overview' => $incidentOverview,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Generate summary report error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate summary report: ' . $e->getMessage()
        ], 500);
    }
}
}
