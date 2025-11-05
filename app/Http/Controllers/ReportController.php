<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\InfrastructureStatus;
use App\Models\PopulationData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDF;

class ReportController extends Controller
{
    public function generateMunicipalReport(Request $request)
    {
        try {
            $type = $request->get('type', 'incidents');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            $data = [];

            switch ($type) {
                case 'incidents':
                    $query = Incident::with(['reporter']);
                    
                    if ($dateFrom && $dateTo) {
                        $query->whereBetween('created_at', [$dateFrom, $dateTo]);
                    }
                    
                    $incidents = $query->get();
                    
                    $data['incidents'] = $incidents;
                    $data['summary'] = [
                        'total' => $incidents->count(),
                        'by_type' => $incidents->groupBy('incident_type')->map->count(),
                        'by_status' => $incidents->groupBy('status')->map->count(),
                        'by_barangay' => $incidents->groupBy('reporter.barangay_name')->map->count(),
                    ];
                    break;

case 'population':
    // Use join to ensure we get all related data
    $populationData = PopulationData::join('incidents', 'population_data.incident_id', '=', 'incidents.id')
        ->leftJoin('users', 'incidents.reported_by', '=', 'users.id')
        ->select(
            'population_data.*',
            'incidents.id as incident_id',
            'incidents.title as incident_title',
            'incidents.incident_type',
            'incidents.incident_date',
            'incidents.created_at as incident_created_at',
            'users.barangay_name',
            'users.municipality'
        );
    
    if ($dateFrom && $dateTo) {
        $populationData->whereBetween('incidents.created_at', [$dateFrom, $dateTo]);
    }
    
    $populationData = $populationData->get();
    
    $detailedData = [];
    foreach ($populationData as $data) {
        $detailedData[] = [
            'incident_id' => $data->incident_id,
            'incident_title' => $data->incident_title ?? 'Untitled Incident',
            'incident_type' => $data->incident_type ?? 'Unknown Type',
            'incident_date' => $data->incident_date ? date('Y-m-d H:i:s', strtotime($data->incident_date)) : ($data->incident_created_at ? date('Y-m-d H:i:s', strtotime($data->incident_created_at)) : null),
            'created_at' => $data->incident_created_at ? date('Y-m-d H:i:s', strtotime($data->incident_created_at)) : null,
            'barangay_name' => $data->barangay_name ?? 'Unknown Barangay',
            'municipality' => $data->municipality ?? 'Unknown Municipality',
            'total_population' => $data->total_population ?? 0,
            'displaced_persons' => $data->displaced_persons ?? 0,
            'families_assisted' => $data->families_assisted ?? 0,
            'pwd_count' => $data->pwd_count ?? 0,
            'elderly_count' => $data->elderly_count ?? 0,
            'pregnant_count' => $data->pregnant_count ?? 0,
        ];
    }
    
    $data['population_data'] = $detailedData;
    $data['summary'] = [
        'total_records' => $populationData->count(),
        'total_population' => $populationData->sum('total_population'),
        'total_displaced' => $populationData->sum('displaced_persons'),
        'total_families_assisted' => $populationData->sum('families_assisted'),
        'unique_barangays' => collect($detailedData)->pluck('barangay_name')->unique()->count(),
        'unique_incidents' => collect($detailedData)->pluck('incident_id')->unique()->count(),
    ];
    break;

case 'infrastructure':
    // Use join to ensure we get all related data
    $infrastructureData = InfrastructureStatus::join('incidents', 'infrastructure_statuses.incident_id', '=', 'incidents.id')
        ->leftJoin('users', 'incidents.reported_by', '=', 'users.id')
        ->select(
            'infrastructure_statuses.*',
            'incidents.id as incident_id',
            'incidents.title as incident_title',
            'incidents.incident_type',
            'incidents.incident_date',
            'incidents.created_at as incident_created_at',
            'users.barangay_name',
            'users.municipality'
        );
    
    if ($dateFrom && $dateTo) {
        $infrastructureData->whereBetween('incidents.created_at', [$dateFrom, $dateTo]);
    }
    
    $infrastructureData = $infrastructureData->get();
    
    // DEBUG: Check what data we're getting
    Log::info('Infrastructure Data for Report:', [
        'total_records' => $infrastructureData->count(),
        'sample_record' => $infrastructureData->first() ? [
            'incident_id' => $infrastructureData->first()->incident_id,
            'incident_title' => $infrastructureData->first()->incident_title,
            'incident_type' => $infrastructureData->first()->incident_type,
            'barangay_name' => $infrastructureData->first()->barangay_name,
            'municipality' => $infrastructureData->first()->municipality,
        ] : null
    ]);
    
    $detailedData = [];
    foreach ($infrastructureData as $data) {
        $detailedData[] = [
            'incident_id' => $data->incident_id,
            'incident_title' => $data->incident_title ?? 'Untitled Incident',
            'incident_type' => $data->incident_type ?? 'Unknown Type',
            'incident_date' => $data->incident_date ? date('Y-m-d H:i:s', strtotime($data->incident_date)) : ($data->incident_created_at ? date('Y-m-d H:i:s', strtotime($data->incident_created_at)) : null),
            'created_at' => $data->incident_created_at ? date('Y-m-d H:i:s', strtotime($data->incident_created_at)) : null,
            'barangay_name' => $data->barangay_name ?? 'Unknown Barangay',
            'municipality' => $data->municipality ?? 'Unknown Municipality',
            'roads_bridges_status' => $data->roads_bridges_status,
            'roads_reported_not_passable' => $data->roads_reported_not_passable,
            'roads_reported_passable' => $data->roads_reported_passable,
            'roads_remarks' => $data->roads_remarks,
            'power_outage_time' => $data->power_outage_time,
            'power_restored_time' => $data->power_restored_time,
            'power_remarks' => $data->power_remarks,
            'communication_interruption_time' => $data->communication_interruption_time,
            'communication_restored_time' => $data->communication_restored_time,
            'communication_remarks' => $data->communication_remarks,
        ];
    }
    
    $data['infrastructure_data'] = $detailedData;
    $data['summary'] = [
        'total_records' => $infrastructureData->count(),
        'roads_affected' => $infrastructureData->where('roads_bridges_status', '!=', null)->count(),
        'power_outages' => $infrastructureData->where('power_outage_time', '!=', null)->count(),
        'communication_issues' => $infrastructureData->where('communication_interruption_time', '!=', null)->count(),
        'unique_barangays' => collect($detailedData)->pluck('barangay_name')->unique()->count(),
        'unique_incidents' => collect($detailedData)->pluck('incident_id')->unique()->count(),
    ];
    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid report type'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'filters' => [
                    'type' => $type,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Generate municipal report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    // Add to ReportController.php
public function generateBarangayReport(Request $request)
{
    try {
        $user = $request->user();

        if ($user->role !== 'barangay') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Barangay users only.'
            ], 403);
        }

        $type = $request->get('type', 'incidents');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = Incident::with(['populationData', 'infrastructureStatus'])
            ->where('reported_by', $user->id);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        $data = [];

        switch ($type) {
            case 'incidents':
                $incidents = $query->get();
                
                $data['incidents'] = $incidents;
                $data['summary'] = [
                    'total' => $incidents->count(),
                    'by_type' => $incidents->groupBy('incident_type')->map->count(),
                    'by_status' => $incidents->groupBy('status')->map->count(),
                ];
                break;

case 'population':
    // Use join to get incident data along with population data
    $populationData = PopulationData::join('incidents', 'population_data.incident_id', '=', 'incidents.id')
        ->where('incidents.reported_by', $user->id)
        ->select(
            'population_data.*',
            'incidents.id as incident_id',
            'incidents.title as incident_title',
            'incidents.incident_type',
            'incidents.incident_date',
            'incidents.created_at as incident_created_at'
        );

    if ($dateFrom && $dateTo) {
        $populationData->whereBetween('incidents.created_at', [$dateFrom, $dateTo]);
    }

    $populationData = $populationData->get();

    $data['population_data'] = $populationData;
    $data['summary'] = [
        'total_records' => $populationData->count(),
        'total_population' => $populationData->sum('total_population'),
        'total_displaced' => $populationData->sum('displaced_persons'),
        'total_families_assisted' => $populationData->sum('families_assisted'),
    ];
    break;

case 'infrastructure':
    // Use join to get incident data along with infrastructure data
    $infrastructureData = InfrastructureStatus::join('incidents', 'infrastructure_statuses.incident_id', '=', 'incidents.id')
        ->where('incidents.reported_by', $user->id)
        ->select(
            'infrastructure_statuses.*',
            'incidents.id as incident_id',
            'incidents.title as incident_title',
            'incidents.incident_type',
            'incidents.incident_date',
            'incidents.created_at as incident_created_at'
        );

    if ($dateFrom && $dateTo) {
        $infrastructureData->whereBetween('incidents.created_at', [$dateFrom, $dateTo]);
    }

    $infrastructureData = $infrastructureData->get();

    $data['infrastructure_data'] = $infrastructureData;
    $data['summary'] = [
        'total_records' => $infrastructureData->count(),
        'roads_affected' => $infrastructureData->where('roads_bridges_status', '!=', null)->count(),
        'power_outages' => $infrastructureData->where('power_outage_time', '!=', null)->count(),
        'communication_issues' => $infrastructureData->where('communication_interruption_time', '!=', null)->count(),
    ];
    break;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'barangay' => $user->barangay_name,
            'filters' => [
                'type' => $type,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Generate barangay report error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate barangay report: ' . $e->getMessage()
        ], 500);
    }
}
}
