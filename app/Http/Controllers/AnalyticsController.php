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
     * Get barangay analytics
     */
    public function getBarangayAnalytics(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'barangay') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Barangay users only.'
                ], 403);
            }

            $dateRange = $request->get('date_range', 'last_6_months');
            $startDate = $this->getStartDate($dateRange);
            
            // Get incidents for this specific barangay user
            $incidents = Incident::where('reported_by', $user->id)
                ->where('created_at', '>=', $startDate)
                ->get();

            // Calculate analytics
            $incidentsByType = $incidents->groupBy('incident_type')
                ->map(function($group, $type) {
                    return [
                        'incident_type' => $type ?: 'Uncategorized',
                        'count' => $group->count()
                    ];
                })->values()->sortByDesc('count');

            $incidentsBySeverity = $incidents->groupBy('severity')
                ->map(function($group, $severity) {
                    return [
                        'severity' => $severity ?: 'Not Specified',
                        'count' => $group->count()
                    ];
                })->values();

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

            // Overall stats
            $totalIncidents = $incidents->count();
            $resolvedIncidents = $incidents->where('status', 'Resolved')->count();
            $activeIncidents = $incidents->where('status', 'Active')->count();
            $pendingIncidents = $incidents->where('status', 'Pending')->count();

            // Population stats
            $incidentsWithPopulation = $incidents->filter(function($incident) {
                return $incident->populationData !== null;
            });

            $populationStats = [
                'total_affected' => $incidentsWithPopulation->sum(function($incident) {
                    return $incident->populationData->male_count + 
                           $incident->populationData->female_count + 
                           $incident->populationData->lgbtqia_count;
                }),
                'total_displaced_families' => $incidentsWithPopulation->sum('populationData.displaced_families'),
                'total_displaced_persons' => $incidentsWithPopulation->sum('populationData.displaced_persons'),
                'total_families_assisted' => $incidentsWithPopulation->sum('populationData.families_assisted'),
                'total_families_requiring_assistance' => $incidentsWithPopulation->sum('populationData.families_requiring_assistance'),
                'assistance_coverage' => $this->calculateAssistanceCoverage($incidentsWithPopulation)
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    // Summary stats
                    'total_incidents' => $totalIncidents,
                    'resolved_incidents' => $resolvedIncidents,
                    'active_incidents' => $activeIncidents,
                    'pending_incidents' => $pendingIncidents,
                    
                    // Charts data
                    'incidents_by_type' => $incidentsByType,
                    'incidents_by_severity' => $incidentsBySeverity,
                    'monthly_trends' => $monthlyTrends,
                    
                    // Population data
                    'population_stats' => $populationStats,
                    
                    // Additional useful data
                    'incidents_with_population_data' => $incidentsWithPopulation->count(),
                    'date_range' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d'),
                        'range_type' => $dateRange
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get barangay analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch barangay analytics data'
            ], 500);
        }
    }

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
            
            // Get all incidents with reporters for the date range
            $incidents = Incident::with('reporter')
                ->where('created_at', '>=', $startDate)
                ->get();

            // Calculate analytics from the collection (avoid complex SQL joins)
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
                    return [
                        'barangay_name' => $barangay,
                        'count' => $group->count()
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
                })->values()->sortBy('month');

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

            // Overall stats
            $totalIncidents = $incidents->count();
            $resolvedIncidents = $incidents->where('status', 'Resolved')->count();
            $highCriticalIncidents = $incidents->whereIn('severity', ['High', 'Critical'])->count();

            // Calculate average response time
            $resolvedIncidentsWithTime = $incidents->where('status', 'Resolved')
                ->filter(function($incident) {
                    return $incident->created_at && $incident->updated_at;
                });
            
            $avgResponseTime = $resolvedIncidentsWithTime->isNotEmpty() 
                ? $resolvedIncidentsWithTime->avg(function($incident) {
                    return $incident->created_at->diffInHours($incident->updated_at);
                })
                : 0;

            // Population stats
            $incidentsWithPopulation = $incidents->filter(function($incident) {
                return $incident->populationData !== null;
            });

            $populationStats = [
                'total_affected' => $incidentsWithPopulation->sum(function($incident) {
                    return $incident->populationData->male_count + 
                           $incident->populationData->female_count + 
                           $incident->populationData->lgbtqia_count;
                }),
                'total_displaced_families' => $incidentsWithPopulation->sum('populationData.displaced_families'),
                'total_displaced_persons' => $incidentsWithPopulation->sum('populationData.displaced_persons'),
                'avg_assistance_coverage' => $this->calculateAssistanceCoverage($incidentsWithPopulation)
            ];

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
                        'start_date' => $startDate,
                        'end_date' => now()->format('Y-m-d H:i:s'),
                        'range_type' => $dateRange
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get municipal analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data'
            ], 500);
        }
    }

    private function calculateAssistanceCoverage($incidentsWithPopulation)
    {
        $totalRequiring = $incidentsWithPopulation->sum('populationData.families_requiring_assistance');
        $totalAssisted = $incidentsWithPopulation->sum('populationData.families_assisted');
        
        if ($totalRequiring > 0) {
            return round(($totalAssisted / $totalRequiring) * 100, 1);
        }
        
        return 0;
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