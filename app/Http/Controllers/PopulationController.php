<?php
// app/Http/Controllers/PopulationController.php

namespace App\Http\Controllers;

use App\Models\PopulationData;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PopulationController extends Controller
{
    /**
     * Get barangay population overview
     */
    public function getBarangayOverview(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'barangay') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Barangay users only.'
                ], 403);
            }

            // Get all incidents for this barangay with population data
            $incidents = Incident::with('populationData')
                ->where('reported_by', $user->id)
                ->whereHas('populationData')
                ->get();

            // Calculate totals from all population data
            $totalPopulation = 0;
            $totalFamilies = 0;
            $totalDisplaced = 0;
            $demographics = [
                'male' => 0,
                'female' => 0,
                'lgbtqia' => 0
            ];
            $ageGroups = [
                'infant' => 0,
                'toddler' => 0,
                'preschooler' => 0,
                'school_age' => 0,
                'teen_age' => 0,
                'adult' => 0,
                'elderly' => 0
            ];
            $specialGroups = [
                'pwd' => 0,
                'pregnant' => 0,
                'elderly' => 0,
                'lactating_mother' => 0,
                'solo_parent' => 0,
                'indigenous_people' => 0,
                'child_headed_household' => 0,
                'gbv_victims' => 0
            ];

            foreach ($incidents as $incident) {
                if ($incident->populationData) {
                    $pd = $incident->populationData;
                    
                    $totalPopulation += $pd->male_count + $pd->female_count + $pd->lgbtqia_count;
                    $totalFamilies += $pd->displaced_families;
                    $totalDisplaced += $pd->displaced_persons;
                    
                    // Demographics
                    $demographics['male'] += $pd->male_count;
                    $demographics['female'] += $pd->female_count;
                    $demographics['lgbtqia'] += $pd->lgbtqia_count;
                    
                    // Age groups
                    $ageGroups['infant'] += $pd->infant_count;
                    $ageGroups['toddler'] += $pd->toddler_count;
                    $ageGroups['preschooler'] += $pd->preschooler_count;
                    $ageGroups['school_age'] += $pd->school_age_count;
                    $ageGroups['teen_age'] += $pd->teen_age_count;
                    $ageGroups['adult'] += $pd->adult_count;
                    $ageGroups['elderly'] += $pd->elderly_age_count;
                    
                    // Special groups
                    $specialGroups['pwd'] += $pd->pwd_count;
                    $specialGroups['pregnant'] += $pd->pregnant_count;
                    $specialGroups['elderly'] += $pd->elderly_count;
                    $specialGroups['lactating_mother'] += $pd->lactating_mother_count;
                    $specialGroups['solo_parent'] += $pd->solo_parent_count;
                    $specialGroups['indigenous_people'] += $pd->indigenous_people_count;
                    $specialGroups['child_headed_household'] += $pd->child_headed_household_count;
                    $specialGroups['gbv_victims'] += $pd->gbv_victims_count;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_population' => $totalPopulation,
                    'total_families' => $totalFamilies,
                    'total_displaced' => $totalDisplaced,
                    'demographics' => $demographics,
                    'age_groups' => $ageGroups,
                    'special_groups' => $specialGroups,
                    'incidents_with_data' => $incidents->count(),
                    'barangay_name' => $user->barangay_name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get barangay overview error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch population overview'
            ], 500);
        }
    }

    /**
     * Get municipal population summary (for admin)
     */
    public function getMunicipalOverview(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Admin users only.'
                ], 403);
            }

            // Get population data grouped by barangay
            $populationByBarangay = PopulationData::with(['incident.reporter'])
                ->get()
                ->groupBy(function($item) {
                    return $item->incident->reporter->barangay_name ?? 'Unknown';
                })
                ->map(function($group) {
                    return [
                        'total_population' => $group->sum(function($item) {
                            return $item->male_count + $item->female_count + $item->lgbtqia_count;
                        }),
                        'total_families' => $group->sum('displaced_families'),
                        'total_displaced' => $group->sum('displaced_persons'),
                        'incident_count' => $group->count()
                    ];
                });

            // Get overall totals
            $overallTotals = [
                'total_population' => PopulationData::get()->sum(function($item) {
                    return $item->male_count + $item->female_count + $item->lgbtqia_count;
                }),
                'total_families' => PopulationData::sum('displaced_families'),
                'total_displaced' => PopulationData::sum('displaced_persons'),
                'total_incidents_with_data' => PopulationData::count(),
// In PopulationController.php - update the getMunicipalOverview method
'assistance_coverage' => PopulationData::where('families_requiring_assistance', '>', 0)
    ->get()
    ->avg(function($item) {
        if ($item->families_requiring_assistance > 0) {
            return ($item->families_assisted / $item->families_requiring_assistance) * 100;
        }
        return 0;
    }) ?? 0
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'by_barangay' => $populationByBarangay,
                    'overall_totals' => $overallTotals
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get municipal overview error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch municipal population overview'
            ], 500);
        }
    }

    /**
     * Get affected population for specific barangay
     */
    public function getAffectedPopulation(Request $request)
    {
        try {
            $user = $request->user();
            $barangayId = $request->get('barangay_id');

            $query = Incident::with(['populationData', 'reporter'])
                ->whereHas('populationData');

            if ($user->role === 'barangay') {
                $query->where('reported_by', $user->id);
            } elseif ($barangayId) {
                $query->whereHas('reporter', function($q) use ($barangayId) {
                    $q->where('barangay', $barangayId);
                });
            }

            $incidents = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $incidents->map(function($incident) {
                    return [
                        'incident' => [
                            'id' => $incident->id,
                            'title' => $incident->title,
                            'type' => $incident->incident_type,
                            'location' => $incident->location,
                            'date' => $incident->incident_date,
                            'status' => $incident->status
                        ],
                        'population_data' => $incident->populationData,
                        'barangay' => $incident->reporter->barangay_name
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Get affected population error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch affected population data'
            ], 500);
        }
    }
}