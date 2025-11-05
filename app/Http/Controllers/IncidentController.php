<?php
// app/Http/Controllers/IncidentController.php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInfrastructureStatusRequest;
use App\Http\Requests\StorePopulationDataRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Models\InfrastructureStatus;
use App\Models\PopulationData;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class IncidentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }


public function index(Request $request)
{
    try {
        $user = $request->user();

        $query = Incident::with([
            'reporter', 
            'populationData', 
            'infrastructureStatus' // Make sure this matches the relationship name
        ]);

        if ($user->role === 'barangay') {
            $query->where('reported_by', $user->id);
        }

        $incidents = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'incidents' => $incidents,
            'message' => 'Incidents fetched successfully'
        ]);
    } catch (\Exception $e) {
        Log::error('Get incidents error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch incidents',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function store(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'incident_type' => 'required|in:Flood,Landslide,Fire,Earthquake,Vehicular',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'incident_date' => 'required|date',
            'severity' => 'required|in:Low,Medium,High,Critical',
            'affected_families' => 'required|integer|min:1',
            'affected_individuals' => 'required|integer|min:1',
            'casualties' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        $incident = Incident::create([
            'reported_by' => $user->id,
            'incident_type' => $request->incident_type,
            'title' => $request->title,
            'description' => $request->description,
            'location' => $request->location,
            'incident_date' => $request->incident_date,
            'severity' => $request->severity,
            'affected_families' => $request->affected_families,
            'affected_individuals' => $request->affected_individuals,
            'casualties' => $request->casualties ?? ['dead' => 0, 'injured' => 0, 'missing' => 0],
            'status' => 'Reported',
        ]);

        // Send notifications
        $adminCount = $this->notificationService->notifyIncidentReported($incident, $user);

        return response()->json([
            'success' => true,
            'message' => 'Incident reported successfully! Administrators have been notified.',
            'incident' => $incident->load(['reporter']),
            'notifications_sent' => $adminCount + 1
        ], 201);
    } catch (\Exception $e) {
        Log::error('Create incident error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to report incident: ' . $e->getMessage()
        ], 500);
    }
}

    public function update(Request $request, Incident $incident)
    {
        try {
            $user = $request->user();

            // Store old status for notification
            $oldStatus = $incident->status;

            if ($user->role === 'barangay' && !$incident->can_barangay_edit) {
                return response()->json([
                    'message' => 'You can only edit recently reported incidents (within 1 hour)'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'incident_type' => 'sometimes|in:Flood,Landslide,Fire,Earthquake,Vehicular',
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'location' => 'sometimes|string|max:255',
                'incident_date' => 'sometimes|date',
                'severity' => 'sometimes|in:Low,Medium,High,Critical',
                'affected_families' => 'nullable|integer|min:0',
                'affected_individuals' => 'nullable|integer|min:0',
                'casualties' => 'nullable|array',
                'status' => 'sometimes|in:Reported,Investigating,Resolved',
                'admin_notes' => 'nullable|string',
                'response_actions' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $incident->update($validator->validated());

            // Send notification if status changed and user is admin
            $newStatus = $incident->status;
            if ($user->role === 'admin' && $oldStatus !== $newStatus) {
                $this->notificationService->notifyIncidentStatusChanged($incident, $oldStatus, $newStatus);
            }

            return response()->json([
                'message' => 'Incident updated successfully',
                'incident' => $incident->load(['reporter'])
            ]);
        } catch (\Exception $e) {
            Log::error('Update incident error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update incident'
            ], 500);
        }
    }

    public function destroy(Request $request, Incident $incident)
    {
        try {
            $user = $request->user();

            // FIX: Use the attribute instead of method
            if ($user->role === 'barangay' && !$incident->can_barangay_delete) {
                return response()->json([
                    'message' => 'You can only delete recently reported incidents (within 1 hour)'
                ], 403);
            }

            $incident->delete();

            return response()->json([
                'message' => 'Incident deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete incident error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete incident'
            ], 500);
        }
    }

    public function show(Incident $incident)
    {
        try {
            return response()->json([
                'incident' => $incident->load([
                    'reporter',
                    'populationData',
                    'infrastructureStatus'
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Show incident error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch incident details'
            ], 500);
        }
    }

    public function stats(Request $request)
    {
        try {
            $user = $request->user();

            $query = Incident::query();

            if ($user->role === 'barangay') {
                $query->where('reported_by', $user->id);
            }

            $stats = [
                'total' => $query->count(),
                'reported' => (clone $query)->where('status', 'Reported')->count(),
                'investigating' => (clone $query)->where('status', 'Investigating')->count(),
                'resolved' => (clone $query)->where('status', 'Resolved')->count(),
                'high_critical' => (clone $query)->whereIn('severity', ['High', 'Critical'])->count(),
            ];

            return response()->json(['stats' => $stats]);
        } catch (\Exception $e) {
            Log::error('Incident stats error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch stats'], 500);
        }
    }



public function storePopulationData(StorePopulationDataRequest $request, $incidentId): JsonResponse
{
    try {
        $incident = Incident::with(['populationData', 'infrastructureStatus'])->findOrFail($incidentId);
        $user = $request->user();

        // Authorization check
        if ($user->role === 'barangay' && $incident->reported_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only add population data to incidents you reported'
            ], 403);
        }

        $action = '';
        
        // Check if population data already exists
        if ($incident->populationData) {
            // Update existing population data
            $incident->populationData->update($request->validated());
            $action = 'updated';
            
            // Notify reporter when admin updates population data
            if ($user->role === 'admin') {
                $this->notificationService->notifyPopulationDataUpdatedByAdmin($incident, $user);
            }
            // Notify admin when barangay updates their own population data
            elseif ($user->role === 'barangay') {
                $this->notificationService->notifyPopulationDataAdded($incident, $user);
            }
        } else {
            // Create new population data
            $populationData = new PopulationData($request->validated());
            $incident->populationData()->save($populationData);
            $action = 'added';

            // Notify admin when new population data is added
            if ($user->role === 'barangay') {
                $this->notificationService->notifyPopulationDataAdded($incident, $user);
            }
        }

        // Reload relationships
        $incident->load(['populationData', 'infrastructureStatus']);

        return response()->json([
            'success' => true,
            'message' => "Population data {$action} successfully",
            'data' => [
                'incident' => $incident,
                'population_data' => $incident->populationData
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Store population data error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to save population data',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
     * Store infrastructure status for an incident with authorization
     */
public function storeInfrastructureStatus(StoreInfrastructureStatusRequest $request, $incidentId): JsonResponse
{
    try {
        $incident = Incident::with(['populationData', 'infrastructureStatus'])->findOrFail($incidentId);
        $user = $request->user();

        // Authorization check
        if ($user->role === 'barangay' && $incident->reported_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only add infrastructure status to incidents you reported'
            ], 403);
        }

        // Convert datetime strings to proper format
        $data = $request->validated();
        
        // Convert datetime fields
        $datetimeFields = [
            'roads_reported_not_passable',
            'roads_reported_passable',
            'power_outage_time',
            'power_restored_time',
            'communication_interruption_time',
            'communication_restored_time'
        ];
        
        foreach ($datetimeFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = \Carbon\Carbon::parse($data[$field]);
            } else {
                $data[$field] = null;
            }
        }

        // Check if infrastructure status already exists
        if ($incident->infrastructureStatus) {
            // Update existing infrastructure status
            $incident->infrastructureStatus->update($data);
            $action = 'updated';
            
            // ADD THIS: Notify reporter when admin updates infrastructure status
            if ($user->role === 'admin' && $action === 'updated') {
                $this->notificationService->notifyInfrastructureStatusUpdatedByAdmin($incident, $user);
            }
        } else {
            // Create new infrastructure status
            $infrastructureStatus = new InfrastructureStatus($data);
            $incident->infrastructureStatus()->save($infrastructureStatus);
            $action = 'added';
            
            // Notify admin when new infrastructure status is added
            if ($user->role === 'barangay') {
                $this->notificationService->notifyInfrastructureStatusAdded($incident, $user);
            }
        }

        // Reload relationships
        $incident->load(['populationData', 'infrastructureStatus']);

        return response()->json([
            'success' => true,
            'message' => "Infrastructure status {$action} successfully",
            'data' => [
                'incident' => $incident,
                'infrastructure_status' => $incident->infrastructureStatus
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Store infrastructure status error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to save infrastructure status',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get population data for an incident
     */
    public function getPopulationData($incidentId): JsonResponse
    {
        try {
            $incident = Incident::with('populationData')->findOrFail($incidentId);

            return response()->json([
                'success' => true,
                'data' => $incident->populationData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch population data'
            ], 404);
        }
    }

    /**
     * Get infrastructure status for an incident
     */
    public function getInfrastructureStatus($incidentId): JsonResponse
    {
        try {
            $incident = Incident::with('infrastructureStatus')->findOrFail($incidentId);

            return response()->json([
                'success' => true,
                'data' => $incident->infrastructureStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch infrastructure status'
            ], 404);
        }
    }

     /**
     * Get complete incident data including population and infrastructure
     */
    public function getCompleteIncident($incidentId): JsonResponse
    {
        try {
            $incident = Incident::with([
                'reporter', 
                'populationData', 
                'infrastructureStatus'
            ])->findOrFail($incidentId);

            return response()->json([
                'success' => true,
                'data' => $incident
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch incident data'
            ], 404);
        }
    }
}
