<?php
// app/Http/Controllers/AnalyticsController.php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\PopulationData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    /**
     * Get municipal analytics with accurate data
     */
    public function getMunicipalAnalytics(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Admin users only.'
                ], 403);
            }

            $dateRange = $request->get('date_range', 'last_6_months');
            $startDate = $this->getStartDate($dateRange);
            
            // Get all incidents with proper relationships
            $incidents = Incident::with([
                'reporter', 
                'populationData',
                'families',
                'families.members'
            ])
            ->where('created_at', '>=', $startDate)
            ->get();

            // Calculate accurate analytics from the data
            $incidentsByType = $incidents->groupBy('incident_type')
                ->map(function($group, $type) {
                    return [
                        'incident_type' => $type ?: 'Uncategorized',
                        'count' => $group->count()
                    ];
                })->values()->sortByDesc('count');

            $incidentsByBarangay = $incidents->groupBy(function($incident) {
                    return $incident->reporter->barangay_name ?? 'Unknown';
                })
                ->map(function($group, $barangay) {
                    // Calculate actual high/critical and resolved counts
                    $highCritical = $group->whereIn('severity', ['High', 'Critical'])->count();
                    $resolved = $group->where('status', 'Resolved')->count();
                    
                    return [
                        'barangay_name' => $barangay,
                        'count' => $group->count(),
                        'high_critical_count' => $highCritical,
                        'resolved_count' => $resolved
                    ];
                })->values()->sortByDesc('count');

            $monthlyTrends = $incidents->groupBy(function($incident) {
                    return $incident->created_at->format('M Y');
                })
                ->map(function($group, $month) {
                    return [
                        'month' => $month,
                        'incidents' => $group->count()
                    ];
                })->values()->sortBy(function($item) {
                    return Carbon::createFromFormat('M Y', $item['month'])->timestamp;
                });

            $severityDistribution = $incidents->groupBy('severity')
                ->map(function($group, $severity) {
                    return [
                        'severity' => $severity ?: 'Not Specified',
                        'count' => $group->count()
                    ];
                })->values();

            $statusDistribution = $incidents->groupBy('status')
                ->map(function($group, $status) {
                    return [
                        'status' => $status,
                        'count' => $group->count()
                    ];
                })->values();

            // Calculate accurate overall stats
            $totalIncidents = $incidents->count();
            $resolvedIncidents = $incidents->where('status', 'Resolved')->count();
            $highCriticalIncidents = $incidents->whereIn('severity', ['High', 'Critical'])->count();

            // Calculate actual average response time for resolved incidents
            $resolvedIncidentsWithTime = $incidents->where('status', 'Resolved')
                ->filter(function($incident) {
                    return $incident->created_at && $incident->updated_at;
                });
            
            $avgResponseTime = $resolvedIncidentsWithTime->isNotEmpty() 
                ? $resolvedIncidentsWithTime->avg(function($incident) {
                    return $incident->created_at->diffInHours($incident->updated_at);
                })
                : 0;

            // Calculate accurate population stats from families data
            $populationStats = $this->calculateAccuratePopulationStats($incidents);

            return response()->json([
                'success' => true,
                'data' => [
                    'incidents_by_type' => $incidentsByType,
                    'incidents_by_barangay' => $incidentsByBarangay,
                    'monthly_trends' => $monthlyTrends,
                    'severity_distribution' => $severityDistribution,
                    'status_distribution' => $statusDistribution,
                    'overall_stats' => [
                        'total_incidents' => $totalIncidents,
                        'resolved_incidents' => $resolvedIncidents,
                        'high_critical_incidents' => $highCriticalIncidents,
                        'avg_response_time_hours' => round($avgResponseTime, 1),
                        'resolution_rate' => $totalIncidents > 0 ? round(($resolvedIncidents / $totalIncidents) * 100, 1) : 0
                    ],
                    'population_stats' => $populationStats,
                    'date_range' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d'),
                        'range_type' => $dateRange
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get municipal analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate accurate population statistics from incidents with families
     */
    private function calculateAccuratePopulationStats($incidents)
    {
        $totalAffected = 0;
        $totalDisplacedFamilies = 0;
        $totalDisplacedPersons = 0;
        $totalFamiliesAssisted = 0;
        $totalFamiliesRequiringAssistance = 0;

        foreach ($incidents as $incident) {
            // Count from families data if available
            if ($incident->families->isNotEmpty()) {
                $totalAffected += $incident->families->sum('family_size');
                $totalDisplacedFamilies += $incident->families->where('evacuation_center', '!=', null)->count();
                $totalDisplacedPersons += $incident->families->sum(function($family) {
                    return $family->members->where('displaced', 'Y')->count();
                });
                $totalFamiliesAssisted += $incident->families->where('assistance_given', '!=', null)->count();
                $totalFamiliesRequiringAssistance += $incident->families->count(); // All families need assistance initially
            }
            // Fallback to populationData if no families data
            elseif ($incident->populationData) {
                $totalAffected += ($incident->populationData->male_count ?? 0) + 
                                 ($incident->populationData->female_count ?? 0) + 
                                 ($incident->populationData->lgbtqia_count ?? 0);
                $totalDisplacedFamilies += $incident->populationData->displaced_families ?? 0;
                $totalDisplacedPersons += $incident->populationData->displaced_persons ?? 0;
                $totalFamiliesAssisted += $incident->populationData->families_assisted ?? 0;
                $totalFamiliesRequiringAssistance += $incident->populationData->families_requiring_assistance ?? 0;
            }
        }

        $assistanceCoverage = $totalFamiliesRequiringAssistance > 0 
            ? round(($totalFamiliesAssisted / $totalFamiliesRequiringAssistance) * 100, 1)
            : 0;

        return [
            'total_affected' => $totalAffected,
            'total_displaced_families' => $totalDisplacedFamilies,
            'total_displaced_persons' => $totalDisplacedPersons,
            'total_families_assisted' => $totalFamiliesAssisted,
            'total_families_requiring_assistance' => $totalFamiliesRequiringAssistance,
            'avg_assistance_coverage' => $assistanceCoverage
        ];
    }

    private function getStartDate($rangeType)
    {
        switch ($rangeType) {
            case 'last_week':
                return now()->subWeek();
            case 'last_month':
                return now()->subMonth();
            case 'last_3_months':
                return now()->subMonths(3);
            case 'last_6_months':
                return now()->subMonths(6);
            case 'last_year':
                return now()->subYear();
            case 'all_time':
            default:
                return Carbon::create(2000, 1, 1);
        }
    }
}