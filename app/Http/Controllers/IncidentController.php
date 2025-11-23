<?php
// app/Http/Controllers/IncidentController.php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInfrastructureStatusRequest;
use App\Http\Requests\StorePopulationDataRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Models\IncidentFamily;
use App\Models\IncidentFamilyMember;
use App\Models\InfrastructureStatus;
use App\Models\PopulationData;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'infrastructureStatus',
                'families',
                'families.members'
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







    public function storeWithFamilies(Request $request)
    {
        try {
            DB::beginTransaction();

            Log::info('Starting incident creation with families', [
                'user_id' => $request->user()->id,
                'families_count' => count($request->families ?? [])
            ]);

            $validator = Validator::make($request->all(), [
                'incident_type' => 'required|in:Flood,Landslide,Fire,Earthquake,Vehicular',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'location' => 'required|string|max:255',
                'barangay' => 'required|string|max:255',
                'purok' => 'nullable|string|max:255',
                'incident_date' => 'required|date',
                'severity' => 'required|in:Low,Medium,High,Critical',
                'families' => 'required|array|min:1',
                'families.*.family_number' => 'required|integer|min:1',
                'families.*.family_size' => 'required|integer|min:1',
                'families.*.evacuation_center' => 'nullable|string|max:255',
                'families.*.alternative_location' => 'nullable|string|max:255',
                'families.*.assistance_given' => 'nullable|string|max:255',
                'families.*.remarks' => 'nullable|string',
                'families.*.members' => 'required|array|min:1',
                'families.*.members.*.last_name' => 'required|string|max:255',
                'families.*.members.*.first_name' => 'required|string|max:255',
                'families.*.members.*.middle_name' => 'nullable|string|max:255',
                'families.*.members.*.position_in_family' => 'required|string|max:255',
                'families.*.members.*.sex_gender_identity' => 'required|string|max:255',
                'families.*.members.*.age' => 'required|integer|min:0|max:120',
                'families.*.members.*.category' => 'required|string|max:255',
                'families.*.members.*.civil_status' => 'required|string|max:255',
                'families.*.members.*.ethnicity' => 'nullable|string|max:255',
                'families.*.members.*.vulnerable_groups' => 'nullable|array',
                'families.*.members.*.casualty' => 'nullable|string|max:255',
                'families.*.members.*.displaced' => 'required|in:Y,N',
                'families.*.members.*.pwd_type' => 'nullable|string|max:255',



                // Add these to the families validation rules
                'families.*.assistance_received' => 'nullable|boolean',
                'families.*.food_assistance' => 'nullable|boolean',
                'families.*.non_food_assistance' => 'nullable|boolean',
                'families.*.shelter_assistance' => 'nullable|boolean',
                'families.*.medical_assistance' => 'nullable|boolean',
                'families.*.other_remarks' => 'nullable|string',

                // Add these to the members validation rules
                'families.*.members.*.assistance_received' => 'nullable|boolean',
                'families.*.members.*.food_assista  nce' => 'nullable|boolean',
                'families.*.members.*.non_food_assistance' => 'nullable|boolean',
                'families.*.members.*.medical_attention' => 'nullable|boolean',
                'families.*.members.*.psychological_support' => 'nullable|boolean',
                'families.*.members.*.other_remarks' => 'nullable|string',

            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Please check all required fields',
                    'errors' => $validator->errors(),
                    'validation_errors' => $this->formatValidationErrors($validator->errors())
                ], 422);
            }

            $user = $request->user();

            // Calculate totals
            $totalFamilies = count($request->families);
            $totalIndividuals = collect($request->families)->sum(function ($family) {
                return count($family['members']);
            });

            Log::info('Creating incident', [
                'title' => $request->title,
                'total_families' => $totalFamilies,
                'total_individuals' => $totalIndividuals
            ]);

            // Create incident
            $incident = Incident::create([
                'reported_by' => $user->id,
                'incident_type' => $request->incident_type,
                'title' => $request->title,
                'description' => $request->description,
                'location' => $request->location,
                'barangay' => $request->barangay,
                'purok' => $request->purok,
                'incident_date' => $request->incident_date,
                'severity' => $request->severity,
                'affected_families' => $totalFamilies,
                'affected_individuals' => $totalIndividuals,
                'status' => 'Reported',
            ]);

            Log::info('Incident created', ['incident_id' => $incident->id]);

            // Create families and members
            foreach ($request->families as $familyIndex => $familyData) {
                Log::info('Creating family', [
                    'incident_id' => $incident->id,
                    'family_number' => $familyData['family_number'],
                    'members_count' => count($familyData['members'])
                ]);

                $family = IncidentFamily::create([
                    'incident_id' => $incident->id,
                    'family_number' => $familyData['family_number'],
                    'family_size' => $familyData['family_size'],
                    'evacuation_center' => $familyData['evacuation_center'] ?? null,
                    'alternative_location' => $familyData['alternative_location'] ?? null,
                    'assistance_given' => $familyData['assistance_given'] ?? null,
                    'remarks' => $familyData['remarks'] ?? null,
                ]);

                foreach ($familyData['members'] as $memberIndex => $memberData) {
                    IncidentFamilyMember::create([
                        'family_id' => $family->id,
                        'last_name' => $memberData['last_name'],
                        'first_name' => $memberData['first_name'],
                        'middle_name' => $memberData['middle_name'] ?? null,
                        'position_in_family' => $memberData['position_in_family'],
                        'sex_gender_identity' => $memberData['sex_gender_identity'],
                        'age' => $memberData['age'],
                        'category' => $memberData['category'],
                        'civil_status' => $memberData['civil_status'],
                        'ethnicity' => $memberData['ethnicity'] ?? null,
                        'vulnerable_groups' => !empty($memberData['vulnerable_groups']) ? $memberData['vulnerable_groups'] : [],
                        'casualty' => $memberData['casualty'] ?? null,
                        'displaced' => $memberData['displaced'] ?? 'N',
                        'pwd_type' => $memberData['pwd_type'] ?? null,
                    ]);
                }
            }

            DB::commit();

            Log::info('Incident creation completed successfully', ['incident_id' => $incident->id]);

            // Send notifications
            $adminCount = $this->notificationService->notifyIncidentReported($incident, $user);

            // Load relationships for response
            $incident->load(['families', 'families.members']);

            return response()->json([
                'success' => true,
                'message' => 'Incident reported successfully with detailed family information!',
                'incident' => $incident,
                'notifications_sent' => $adminCount + 1
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create incident with families error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['families']) // Don't log sensitive family data
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to report incident. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Add method to get incident with families
    public function showWithFamilies($id)
    {
        try {
            $incident = Incident::with([
                'reporter',
                'families',
                'families.members',
                'populationData',
                'infrastructureStatus'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'incident' => $incident
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incident not found'
            ], 404);
        }
    }

    // Add method to generate reports (Excel second tab format)
    public function generateReport($id)
    {
        try {
            $incident = Incident::with(['families', 'families.members'])->findOrFail($id);

            $members = $incident->familyMembers;

            // Calculate report data based on Excel second tab
            $report = [
                // Population Affected
                'no_of_families' => $incident->families->count(),
                'no_of_persons' => $members->count(),
                'displaced_families' => $incident->families->where('evacuation_center', '!=', null)->count(),
                'displaced_persons' => $members->where('displaced', 'Y')->count(),
                'families_requiring_assistance' => $incident->families->where('assistance_given', '!=', null)->count(),
                'families_assisted' => $incident->families->where('assistance_given', '!=', null)->count(),
                'percent_families_assisted' => $incident->families->count() > 0 ?
                    round(($incident->families->where('assistance_given', '!=', null)->count() / $incident->families->count()) * 100, 2) : 0,

                // Gender Distribution
                'male_count' => $members->where('sex_gender_identity', 'Male')->count(),
                'female_count' => $members->where('sex_gender_identity', 'Female')->count(),
                'lgbtqia_count' => $members->where('sex_gender_identity', 'LGBTQIA+ / Other (self-identified)')->count(),

                // Civil Status
                'single_count' => $members->where('civil_status', 'Single')->count(),
                'married_count' => $members->where('civil_status', 'Married')->count(),
                'widowed_count' => $members->where('civil_status', 'Widowed')->count(),
                'separated_count' => $members->where('civil_status', 'Separated')->count(),
                'live_in_count' => $members->where('civil_status', 'Live-In/Cohabiting')->count(),

                // Vulnerable Groups
                'pwd_count' => $members->where('vulnerable_groups', 'like', '%PWD%')->count(),
                'pregnant_count' => $members->where('vulnerable_groups', 'like', '%Pregnant%')->count(),
                'elderly_count' => $members->where('vulnerable_groups', 'like', '%Elderly%')->count(),
                'lactating_mother_count' => $members->where('vulnerable_groups', 'like', '%Lactating Mother%')->count(),
                'solo_parent_count' => $members->where('vulnerable_groups', 'like', '%Solo parent%')->count(),
                'indigenous_people_count' => $members->where('vulnerable_groups', 'like', '%Indigenous People%')->count(),
                'lgbtqia_persons_count' => $members->where('vulnerable_groups', 'like', '%LGBTQIA+ Persons%')->count(),
                'child_headed_household_count' => $members->where('vulnerable_groups', 'like', '%Child-Headed Household%')->count(),
                'gbv_victim_count' => $members->where('vulnerable_groups', 'like', '%Victim of Gender-Based Violence (GBV)%')->count(),
                'four_ps_beneficiaries_count' => $members->where('vulnerable_groups', 'like', '%4Ps Beneficiaries%')->count(),
                'single_headed_family_count' => $members->where('vulnerable_groups', 'like', '%Single Headed Family%')->count(),

                // Casualties
                'dead_count' => $members->where('casualty', 'Dead')->count(),
                'injured_count' => $members->where('casualty', 'Injured/ill')->count(),
                'missing_count' => $members->where('casualty', 'Missing')->count(),
            ];

            return response()->json([
                'success' => true,
                'report' => $report,
                'incident' => $incident->only(['id', 'title', 'incident_type', 'location', 'incident_date'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report'
            ], 500);
        }
    }

    // ... rest of your existing methods

    private function formatValidationErrors($errors)
    {
        $formatted = [];
        foreach ($errors->toArray() as $field => $messages) {
            $formatted[] = [
                'field' => $field,
                'messages' => $messages
            ];
        }
        return $formatted;
    }


    /**
     * Update incident with families data
     */
    public function updateWithFamilies(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            Log::info('Starting incident update with families', [
                'user_id' => $request->user()->id,
                'incident_id' => $id,
                'families_count' => count($request->families ?? [])
            ]);

            $incident = Incident::findOrFail($id);
            $user = $request->user();

            // Authorization check
            if ($user->role === 'barangay' && !$incident->can_barangay_edit) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit recently reported incidents (within 1 hour)'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'incident_type' => 'required|in:Flood,Landslide,Fire,Earthquake,Vehicular',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'location' => 'required|string|max:255',
                'barangay' => 'required|string|max:255',
                'purok' => 'nullable|string|max:255',
                'incident_date' => 'required|date',
                'severity' => 'required|in:Low,Medium,High,Critical',
                'status' => 'sometimes|in:Reported,Investigating,Resolved',
                'families' => 'required|array|min:1',
                'families.*.family_number' => 'required|integer|min:1',
                'families.*.family_size' => 'required|integer|min:1',
                'families.*.evacuation_center' => 'nullable|string|max:255',
                'families.*.alternative_location' => 'nullable|string|max:255',
                'families.*.members' => 'required|array|min:1',
                'families.*.members.*.last_name' => 'required|string|max:255',
                'families.*.members.*.first_name' => 'required|string|max:255',
                'families.*.members.*.middle_name' => 'nullable|string|max:255',
                'families.*.members.*.position_in_family' => 'required|string|max:255',
                'families.*.members.*.sex_gender_identity' => 'required|string|max:255',
                'families.*.members.*.age' => 'required|integer|min:0|max:120',
                'families.*.members.*.category' => 'required|string|max:255',
                'families.*.members.*.civil_status' => 'required|string|max:255',
                'families.*.members.*.ethnicity' => 'nullable|string|max:255',
                'families.*.members.*.vulnerable_groups' => 'nullable|array',
                'families.*.members.*.casualty' => 'nullable|string|max:255',
                'families.*.members.*.displaced' => 'required|in:Y,N',
                'families.*.members.*.pwd_type' => 'nullable|string|max:255',

                // Add these to the families validation rules
                'families.*.assistance_received' => 'nullable|boolean',
                'families.*.food_assistance' => 'nullable|boolean',
                'families.*.non_food_assistance' => 'nullable|boolean',
                'families.*.shelter_assistance' => 'nullable|boolean',
                'families.*.medical_assistance' => 'nullable|boolean',
                'families.*.other_remarks' => 'nullable|string',

                // Add these to the members validation rules
                'families.*.members.*.assistance_received' => 'nullable|boolean',
                'families.*.members.*.food_assistance' => 'nullable|boolean',
                'families.*.members.*.non_food_assistance' => 'nullable|boolean',
                'families.*.members.*.medical_attention' => 'nullable|boolean',
                'families.*.members.*.psychological_support' => 'nullable|boolean',
                'families.*.members.*.other_remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Please check all required fields',
                    'errors' => $validator->errors(),
                    'validation_errors' => $this->formatValidationErrors($validator->errors())
                ], 422);
            }

            // Store old status for notification
            $oldStatus = $incident->status;

            // Calculate totals
            $totalFamilies = count($request->families);
            $totalIndividuals = collect($request->families)->sum(function ($family) {
                return count($family['members']);
            });

            Log::info('Updating incident', [
                'title' => $request->title,
                'total_families' => $totalFamilies,
                'total_individuals' => $totalIndividuals
            ]);

            // Update incident
            $incident->update([
                'incident_type' => $request->incident_type,
                'title' => $request->title,
                'description' => $request->description,
                'location' => $request->location,
                'barangay' => $request->barangay,
                'purok' => $request->purok,
                'incident_date' => $request->incident_date,
                'severity' => $request->severity,
                'status' => $request->status ?? $incident->status,
                'affected_families' => $totalFamilies,
                'affected_individuals' => $totalIndividuals,
            ]);

            Log::info('Incident updated', ['incident_id' => $incident->id]);

            // Delete existing families and members
            $incident->families()->each(function ($family) {
                $family->members()->delete();
            });
            $incident->families()->delete();

            // Create new families and members
            foreach ($request->families as $familyIndex => $familyData) {
                Log::info('Creating family', [
                    'incident_id' => $incident->id,
                    'family_number' => $familyData['family_number'],
                    'members_count' => count($familyData['members'])
                ]);

                $family = IncidentFamily::create([
                    'incident_id' => $incident->id,
                    'family_number' => $familyData['family_number'],
                    'family_size' => $familyData['family_size'],
                    'evacuation_center' => $familyData['evacuation_center'] ?? null,
                    'alternative_location' => $familyData['alternative_location'] ?? null,
                    'assistance_received' => $familyData['assistance_received'] ?? false,
                    'food_assistance' => $familyData['food_assistance'] ?? false,
                    'non_food_assistance' => $familyData['non_food_assistance'] ?? false,
                    'shelter_assistance' => $familyData['shelter_assistance'] ?? false,
                    'medical_assistance' => $familyData['medical_assistance'] ?? false,
                    'other_remarks' => $familyData['other_remarks'] ?? null,
                ]);

                foreach ($familyData['members'] as $memberIndex => $memberData) {
                    IncidentFamilyMember::create([
                        'family_id' => $family->id,
                        'last_name' => $memberData['last_name'],
                        'first_name' => $memberData['first_name'],
                        'middle_name' => $memberData['middle_name'] ?? null,
                        'position_in_family' => $memberData['position_in_family'],
                        'sex_gender_identity' => $memberData['sex_gender_identity'],
                        'age' => $memberData['age'],
                        'category' => $memberData['category'],
                        'civil_status' => $memberData['civil_status'],
                        'ethnicity' => $memberData['ethnicity'] ?? null,
                        'vulnerable_groups' => !empty($memberData['vulnerable_groups']) ? $memberData['vulnerable_groups'] : [],
                        'casualty' => $memberData['casualty'] ?? null,
                        'displaced' => $memberData['displaced'] ?? 'N',
                        'pwd_type' => $memberData['pwd_type'] ?? null,
                        'assistance_received' => $memberData['assistance_received'] ?? false,
                        'food_assistance' => $memberData['food_assistance'] ?? false,
                        'non_food_assistance' => $memberData['non_food_assistance'] ?? false,
                        'medical_attention' => $memberData['medical_attention'] ?? false,
                        'psychological_support' => $memberData['psychological_support'] ?? false,
                        'other_remarks' => $memberData['other_remarks'] ?? null,
                    ]);
                }
            }

            DB::commit();

            Log::info('Incident update completed successfully', ['incident_id' => $incident->id]);

            // Send notification if status changed and user is admin
            $newStatus = $incident->status;
            if ($user->role === 'admin' && $oldStatus !== $newStatus) {
                $this->notificationService->notifyIncidentStatusChanged($incident, $oldStatus, $newStatus);
            }

            // Load relationships for response
            $incident->load(['families', 'families.members']);

            return response()->json([
                'success' => true,
                'message' => 'Incident updated successfully with detailed family information!',
                'incident' => $incident
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update incident with families error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['families'])
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update incident. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
