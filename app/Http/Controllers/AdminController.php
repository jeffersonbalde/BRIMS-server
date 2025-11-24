<?php
// app/Http/Controllers/AdminController.php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }


    // In AdminController.php - Add this NEW optimized method
public function getBarangaysSummaryForDashboard(Request $request)
{
    try {
        // Check if user is admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // OPTIMIZED: Get only what we need for the dashboard
        $barangayUsers = User::where('role', 'barangay')
            ->where('is_approved', true)
            ->where('is_active', true)
            ->select('barangay_name', 'municipality')
            ->distinct()
            ->get();

        $result = [];
        $totalPopulation = 0;
        $barangaysWithData = 0;

        foreach ($barangayUsers as $user) {
            $barangayName = $user->barangay_name;

            // OPTIMIZED: Get only incident counts and basic population data
            $incidentCount = Incident::whereHas('reporter', function ($query) use ($barangayName) {
                $query->where('barangay_name', $barangayName);
            })->count();

            // OPTIMIZED: Get population data from incidents with families
            $populationData = Incident::with(['families', 'populationData'])
                ->whereHas('reporter', function ($query) use ($barangayName) {
                    $query->where('barangay_name', $barangayName);
                })
                ->get()
                ->reduce(function ($carry, $incident) {
                    if ($incident->families->isNotEmpty()) {
                        $carry['total_population'] += $incident->families->sum('family_size');
                        $carry['has_data'] = true;
                    } elseif ($incident->populationData) {
                        $popData = $incident->populationData;
                        $carry['total_population'] += ($popData->male_count ?? 0) + 
                                                     ($popData->female_count ?? 0) + 
                                                     ($popData->lgbtqia_count ?? 0);
                        $carry['has_data'] = true;
                    }

                    
                    return $carry;
                }, ['total_population' => 0, 'has_data' => false]);

            $barangayPopulation = $populationData['total_population'];
            $totalPopulation += $barangayPopulation;
            
            if ($populationData['has_data']) {
                $barangaysWithData++;
            }

            $result[] = [
                'barangay_name' => $barangayName,
                'municipality' => $user->municipality,
                'total_incidents' => $incidentCount,
                'population_data' => [
                    'total_population' => $barangayPopulation,
                ],
                'has_population_data' => $populationData['has_data'],
            ];
        }

        return response()->json([
            'success' => true,
            'barangays' => $result,
            'total_barangays' => count($result),
            'barangays_with_data' => $barangaysWithData,
            'total_population' => $totalPopulation, // Add this for easy access in dashboard
        ]);
        
    } catch (\Exception $e) {
        Log::error('Get barangays summary for dashboard error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch barangay summary data'
        ], 500);
    }
}

    /**
     * Deactivate user account
     */
    public function deactivateUser(Request $request, $userId)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $admin = $request->user();

            // Store previous status for notification
            $previousStatus = $user->is_active;

            // Deactivate the user
            $user->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $admin->id,
                'deactivation_reason' => $request->deactivation_reason
            ]);

            // Create notification for the deactivated user
            $this->notificationService->createNotification(
                $user->id,
                NotificationService::TYPE_ACCOUNT_DEACTIVATED,
                'Account Deactivated',
                "Your account has been deactivated by administrator. Reason: " . ($request->deactivation_reason ?? 'No reason provided'),
                [
                    'action' => 'account_deactivated',
                    'deactivated_by' => $admin->name,
                    'deactivation_reason' => $request->deactivation_reason,
                    'deactivated_at' => now()->toISOString()
                ]
            );

            // Send email notification
            $this->notificationService->sendEmailNotification(
                $user->id,
                NotificationService::TYPE_ACCOUNT_DEACTIVATED,
                'Account Deactivated - BRIMS',
                $this->getAccountDeactivatedEmailContent($user, $admin, $request->deactivation_reason)
            );

            // Notify admins about the deactivation
            $this->notificationService->notifyAdmins(
                NotificationService::TYPE_ADMIN_ALERT,
                'User Account Deactivated',
                "User {$user->name} ({$user->barangay_name}) has been deactivated by {$admin->name}",
                [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'admin_name' => $admin->name,
                    'action' => 'account_deactivated'
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User account deactivated successfully',
                'user' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Deactivate user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user account'
            ], 500);
        }
    }

    /**
     * Reactivate user account
     */
    public function reactivateUser(Request $request, $userId)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $admin = $request->user();

            // Reactivate the user
            $user->update([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivated_by' => null,
                'deactivation_reason' => null,
                'reactivated_at' => now(),
                'reactivated_by' => $admin->id
            ]);

            // Create notification for the reactivated user
            $this->notificationService->createNotification(
                $user->id,
                NotificationService::TYPE_ACCOUNT_REACTIVATED,
                'Account Reactivated',
                "Your account has been reactivated by administrator. You can now access the system.",
                [
                    'action' => 'account_reactivated',
                    'reactivated_by' => $admin->name,
                    'reactivated_at' => now()->toISOString()
                ]
            );

            // Send email notification
            $this->notificationService->sendEmailNotification(
                $user->id,
                NotificationService::TYPE_ACCOUNT_REACTIVATED,
                'Account Reactivated - BRIMS',
                $this->getAccountReactivatedEmailContent($user, $admin)
            );

            // Notify admins about the reactivation
            $this->notificationService->notifyAdmins(
                NotificationService::TYPE_ADMIN_ALERT,
                'User Account Reactivated',
                "User {$user->name} ({$user->barangay_name}) has been reactivated by {$admin->name}",
                [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'admin_name' => $admin->name,
                    'action' => 'account_reactivated'
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User account reactivated successfully',
                'user' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reactivate user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate user account'
            ], 500);
        }
    }


    /**
     * Email content for account deactivation
     */
    private function getAccountDeactivatedEmailContent($user, $admin, $reason)
    {
        return "
        <h2>Account Deactivated</h2>
        <p>Dear {$user->name},</p>
        
        <p>Your BRIMS account has been deactivated by an administrator.</p>
        
        <div style='background: #fff3f3; padding: 15px; margin: 15px 0; border-left: 4px solid #dc3545;'>
            <p><strong>Deactivation Details:</strong></p>
            <ul>
                <li><strong>Deactivated By:</strong> {$admin->name}</li>
                <li><strong>Deactivation Date:</strong> " . now()->format('F j, Y g:i A') . "</li>
                <li><strong>Reason:</strong> " . ($reason ?? 'No reason provided') . "</li>
            </ul>
        </div>

        <p><strong>What this means:</strong></p>
        <ul>
            <li>You will no longer be able to access your BRIMS account</li>
            <li>You cannot report new incidents</li>
            <li>You cannot view existing incident data</li>
            <li>All your account permissions have been revoked</li>
        </ul>

        <p>If you believe this deactivation was made in error, please contact the municipal administrator.</p>
        
        <p><em>This is an automated message from BRIMS. Please do not reply to this email.</em></p>
        ";
    }

    /**
     * Email content for account reactivation
     */
    private function getAccountReactivatedEmailContent($user, $admin)
    {
        return "
        <h2>Account Reactivated</h2>
        <p>Dear {$user->name},</p>
        
        <p>Your BRIMS account has been reactivated by an administrator.</p>
        
        <div style='background: #f0fff4; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745;'>
            <p><strong>Reactivation Details:</strong></p>
            <ul>
                <li><strong>Reactivated By:</strong> {$admin->name}</li>
                <li><strong>Reactivation Date:</strong> " . now()->format('F j, Y g:i A') . "</li>
            </ul>
        </div>

        <p><strong>Your account access has been restored:</strong></p>
        <ul>
            <li>You can now log in to your BRIMS account</li>
            <li>All previous incident data is available</li>
            <li>You can report new incidents</li>
            <li>Your account permissions have been restored</li>
        </ul>

        <p>You can access your account at: " . config('app.url') . "</p>
        
        <p>Welcome back to BRIMS!</p>
        
        <p><em>This is an automated message from BRIMS. Please do not reply to this email.</em></p>
        ";
    }

    public function getPendingUsers(Request $request)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $pendingUsers = User::where('role', 'barangay')
                ->where('is_approved', false)
                ->where('status', 'pending')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'users' => $pendingUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'barangay_name' => $user->barangay_name,
                        'position' => $user->position,
                        'municipality' => $user->municipality,
                        'contact' => $user->contact,
                        'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
                        'created_at' => $user->created_at,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Get pending users error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch pending users'], 500);
        }
    }

    public function approveUser(Request $request, User $user)
    {
        DB::beginTransaction();
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Check if user is pending barangay user
            if ($user->role !== 'barangay' || $user->is_approved) {
                return response()->json(['message' => 'Invalid user for approval'], 400);
            }

            // Update user with transaction for safety
            $user->update([
                'is_approved' => true,
                'status' => 'approved',
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'is_active' => true, // Ensure user is active
            ]);

            DB::commit();

            // Log the approval for debugging
            Log::info("User approved successfully", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'approved_at' => $user->approved_at
            ]);

            // Here you can add email notification if needed
            // Mail::to($user->email)->send(new AccountApprovedMail($user));

            return response()->json([
                'message' => 'User approved successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_approved' => $user->is_approved,
                    'status' => $user->status,
                    'approved_at' => $user->approved_at,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve user error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to approve user'], 500);
        }
    }

    public function rejectUser(Request $request, User $user)
    {
        DB::beginTransaction();
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|min:10|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user is pending barangay user
            if ($user->role !== 'barangay' || $user->is_approved) {
                return response()->json(['message' => 'Invalid user for rejection'], 400);
            }

            // Update user with transaction for safety
            $user->update([
                'is_approved' => false,
                'status' => 'rejected',
                'is_active' => true,
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            DB::commit();

            // Log the rejection for debugging
            Log::info("User rejected successfully", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'rejected_at' => $user->rejected_at,
                'has_reason' => !empty($user->rejection_reason)
            ]);

            // Here you can add email notification if needed
            // Mail::to($user->email)->send(new AccountRejectedMail($user, $request->rejection_reason));

            return response()->json([
                'message' => 'User rejected successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_approved' => $user->is_approved,
                    'status' => $user->status,
                    'rejected_at' => $user->rejected_at,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reject user error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to reject user'], 500);
        }
    }

    public function getAllUsers(Request $request)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Only get barangay users, exclude admin users
            $users = User::where('role', 'barangay')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'users' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'barangay_name' => $user->barangay_name,
                        'position' => $user->position,
                        'municipality' => $user->municipality,
                        'contact' => $user->contact,
                        'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
                        'role' => $user->role,
                        'is_approved' => $user->is_approved,
                        'status' => $user->status,
                        'is_active' => $user->is_active,
                        'created_at' => $user->created_at,
                        'approved_at' => $user->approved_at,
                        'rejected_at' => $user->rejected_at,
                        'rejection_reason' => $user->rejection_reason,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Get all users error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch users'], 500);
        }
    }

    public function getUserDetails(Request $request, User $user)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'barangay_name' => $user->barangay_name,
                    'position' => $user->position,
                    'municipality' => $user->municipality,
                    'contact' => $user->contact,
                    'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
                    'role' => $user->role,
                    'is_approved' => $user->is_approved,
                    'status' => $user->status,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'approved_at' => $user->approved_at?->format('Y-m-d H:i:s'),
                    'rejected_at' => $user->rejected_at?->format('Y-m-d H:i:s'),
                    'rejection_reason' => $user->rejection_reason,
                    'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get user details error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch user details'], 500);
        }
    }

    public function getPendingUsersCount(Request $request)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $pendingCount = User::where('role', 'barangay')
                ->where('is_approved', false)
                ->where('status', 'pending')
                ->where('is_active', true)
                ->count();

            return response()->json([
                'pending_count' => $pendingCount
            ]);
        } catch (\Exception $e) {
            Log::error('Get pending users count error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch pending users count'], 500);
        }
    }

    // In AdminController.php - update the getAllIncidents method
public function getAllIncidents(Request $request)
{
    try {
        $incidents = Incident::with([
            'reporter',
            'populationData',
            'infrastructureStatus',
            'families',
            'families.members'
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate casualties from family members for each incident
        $incidents->each(function ($incident) {
            $casualties = [
                'dead' => 0,
                'injured' => 0,
                'missing' => 0
            ];

            foreach ($incident->families as $family) {
                foreach ($family->members as $member) {
                    switch ($member->casualty) {
                        case 'Dead':
                            $casualties['dead']++;
                            break;
                        case 'Injured/ill':
                            $casualties['injured']++;
                            break;
                        case 'Missing':
                            $casualties['missing']++;
                            break;
                    }
                }
            }

            // Add calculated casualties to the incident
            $incident->calculated_casualties = $casualties;
        });

        return response()->json([
            'incidents' => $incidents
        ]);
    } catch (\Exception $e) {
        Log::error('Get all incidents error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to fetch incidents'
        ], 500);
    }
}

    public function updateIncidentStatus(Request $request, Incident $incident)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:Reported,Investigating,Resolved',
                'admin_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $incident->status;
            $newStatus = $request->status;

            $incident->update([
                'status' => $newStatus,
                'admin_notes' => $request->admin_notes,
            ]);

            // Send notification to the reporter
            if ($oldStatus !== $newStatus) {
                $this->notificationService->notifyIncidentStatusChanged($incident, $oldStatus, $newStatus);
            }

            return response()->json([
                'message' => 'Incident status updated successfully',
                'incident' => $incident->load(['reporter'])
            ]);
        } catch (\Exception $e) {
            Log::error('Update incident status error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update incident status'
            ], 500);
        }
    }


    // Add this method to your AdminController.php
    public function archiveIncident(Request $request, Incident $incident)
    {
        try {
            $validator = Validator::make($request->all(), [
                'archive_reason' => 'required|string|min:5|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $incident->status;

            $incident->update([
                'status' => 'Archived',
                'archive_reason' => $request->archive_reason,
                'archived_at' => now(),
                'archived_by' => $request->user()->id,
            ]);

            // Send notification to the reporter
            $this->notificationService->notifyIncidentArchived($incident, $request->archive_reason);

            // Log the archiving action
            Log::info("Incident archived", [
                'incident_id' => $incident->id,
                'archived_by' => $request->user()->id,
                'old_status' => $oldStatus,
                'reason' => $request->archive_reason
            ]);

            return response()->json([
                'message' => 'Incident archived successfully',
                'incident' => $incident->load(['reporter', 'archiver'])
            ]);
        } catch (\Exception $e) {
            Log::error('Archive incident error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to archive incident'
            ], 500);
        }
    }


    // Add to AdminController.php
    public function unarchiveIncident(Request $request, Incident $incident)
    {
        try {
            // Check if incident is actually archived
            if ($incident->status !== 'Archived') {
                return response()->json([
                    'message' => 'Incident is not archived'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'unarchive_reason' => 'required|string|min:5|max:500',
                'new_status' => 'required|in:Reported,Investigating,Resolved'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Store archive history before unarchiving
            $archiveHistory = [
                'archived_at' => $incident->archived_at,
                'archived_by' => $incident->archived_by,
                'archive_reason' => $incident->archive_reason,
                'unarchived_at' => now(),
                'unarchived_by' => $request->user()->id,
                'unarchive_reason' => $request->unarchive_reason,
                'previous_status' => $incident->status,
                'new_status' => $request->new_status
            ];

            $incident->update([
                'status' => $request->new_status,
                'archive_reason' => null,
                'archived_at' => null,
                'archived_by' => null,
                'unarchive_history' => json_encode($archiveHistory), // Store history
                'admin_notes' => $incident->admin_notes . "\n\n--- UNARCHIVED ---\n" .
                    "Unarchived on: " . now()->format('Y-m-d H:i:s') . "\n" .
                    "Reason: " . $request->unarchive_reason . "\n" .
                    "New Status: " . $request->new_status
            ]);

            // Send notification to the reporter
            $this->notificationService->notifyIncidentUnarchived($incident, $request->new_status, $request->unarchive_reason);

            // Log the unarchiving action
            Log::info("Incident unarchived", [
                'incident_id' => $incident->id,
                'unarchived_by' => $request->user()->id,
                'new_status' => $request->new_status,
                'reason' => $request->unarchive_reason
            ]);

            return response()->json([
                'message' => 'Incident unarchived successfully',
                'incident' => $incident->load(['reporter'])
            ]);
        } catch (\Exception $e) {
            Log::error('Unarchive incident error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to unarchive incident'
            ], 500);
        }
    }


    // In AdminController.php - Fix the getAllBarangaysWithPopulationData method

    public function getAllBarangaysWithPopulationData(Request $request)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Get all approved barangay users
            $barangayUsers = User::where('role', 'barangay')
                ->where('is_approved', true)
                ->where('is_active', true)
                ->select('barangay_name', 'municipality')
                ->distinct()
                ->get();

            $result = [];

            foreach ($barangayUsers as $user) {
                $barangayName = $user->barangay_name;

                // Get incidents for this barangay with families data
                $incidents = Incident::with(['reporter', 'families', 'families.members', 'populationData'])
                    ->whereHas('reporter', function ($query) use ($barangayName) {
                        $query->where('barangay_name', $barangayName);
                    })
                    ->get();

                // Calculate accurate totals from families data
                $totalPopulation = 0;
                $totalDisplacedPersons = 0;
                $totalDisplacedFamilies = 0;
                $totalFamiliesAssisted = 0;
                $totalFamiliesRequiringAssistance = 0;

                foreach ($incidents as $incident) {
                    if ($incident->families->isNotEmpty()) {
                        // Calculate from families data
                        $totalPopulation += $incident->families->sum('family_size');
                        $totalDisplacedFamilies += $incident->families->where('evacuation_center', '!=', null)->count();
                        $totalDisplacedPersons += $incident->families->sum(function ($family) {
                            return $family->members->where('displaced', 'Y')->count();
                        });
                        $totalFamiliesAssisted += $incident->families->where('assistance_given', '!=', null)->count();
                        $totalFamiliesRequiringAssistance += $incident->families->count();
                    } elseif ($incident->populationData) {
                        // Fallback to populationData
                        $totalPopulation += ($incident->populationData->male_count ?? 0) +
                            ($incident->populationData->female_count ?? 0) +
                            ($incident->populationData->lgbtqia_count ?? 0);
                        $totalDisplacedFamilies += $incident->populationData->displaced_families ?? 0;
                        $totalDisplacedPersons += $incident->populationData->displaced_persons ?? 0;
                        $totalFamiliesAssisted += $incident->populationData->families_assisted ?? 0;
                        $totalFamiliesRequiringAssistance += $incident->populationData->families_requiring_assistance ?? 0;
                    }
                }

                // Calculate special groups from family members
                $pwdCount = 0;
                $elderlyCount = 0;
                $pregnantCount = 0;

                foreach ($incidents as $incident) {
                    foreach ($incident->families as $family) {
                        foreach ($family->members as $member) {
                            $vulnerableGroups = $member->vulnerable_groups ?? [];
                            if (in_array('PWD', $vulnerableGroups)) {
                                $pwdCount++;
                            }
                            if (in_array('Elderly', $vulnerableGroups)) {
                                $elderlyCount++;
                            }
                            if (in_array('Pregnant', $vulnerableGroups)) {
                                $pregnantCount++;
                            }
                        }
                    }
                }

                $result[] = [
                    'barangay_name' => $barangayName,
                    'municipality' => $user->municipality,
                    'total_incidents' => $incidents->count(),
                    'population_data' => [
                        'total_population' => $totalPopulation,
                        'displaced_persons' => $totalDisplacedPersons,
                        'displaced_families' => $totalDisplacedFamilies,
                        'families_assisted' => $totalFamiliesAssisted,
                        'families_requiring_assistance' => $totalFamiliesRequiringAssistance,
                        'pwd_count' => $pwdCount,
                        'elderly_count' => $elderlyCount,
                        'pregnant_count' => $pregnantCount,
                    ],
                    'has_population_data' => $totalPopulation > 0,
                ];
            }

            return response()->json([
                'success' => true,
                'barangays' => $result,
                'total_barangays' => count($result),
                'barangays_with_data' => collect($result)->where('has_population_data', true)->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Get all barangays with population data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch barangay population data: ' . $e->getMessage()
            ], 500);
        }
    }


    // Add this method to AdminController.php
    public function getIncidentDetails($id)
    {
        try {
            $incident = Incident::with([
                'reporter',
                'populationData',
                'infrastructureStatus',
                'families',
                'families.members'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'incident' => $incident
            ]);
        } catch (\Exception $e) {
            Log::error('Get incident details error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch incident details'
            ], 500);
        }
    }



// Add these methods to your AdminController.php

    /**
     * Get all archived incidents
     */
    public function getArchivedIncidents(Request $request)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $archivedIncidents = Incident::with([
                'reporter',
                'populationData',
                'infrastructureStatus',
                'families',
                'families.members',
                'archiver'
            ])
                ->where('status', 'Archived')
                ->orderBy('archived_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'incidents' => $archivedIncidents,
                'total_count' => $archivedIncidents->count(),
                'total_size_mb' => $this->calculateArchivedIncidentsSize($archivedIncidents)
            ]);
        } catch (\Exception $e) {
            Log::error('Get archived incidents error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch archived incidents'
            ], 500);
        }
    }

    /**
     * Delete a single archived incident
     */
    public function deleteArchivedIncident(Request $request, Incident $incident)
    {
        try {
            DB::beginTransaction();

            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Check if incident is archived
            if ($incident->status !== 'Archived') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only archived incidents can be deleted'
                ], 400);
            }

            $incidentId = $incident->id;
            $incidentTitle = $incident->title;

            // Delete related data first
            $this->deleteIncidentRelatedData($incident);

            // Delete the incident
            $incident->delete();

            DB::commit();

            // Log the deletion
            Log::info("Archived incident deleted", [
                'incident_id' => $incidentId,
                'deleted_by' => $request->user()->id,
                'incident_title' => $incidentTitle
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Archived incident deleted successfully',
                'deleted_incident_id' => $incidentId
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete archived incident error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete archived incident'
            ], 500);
        }
    }

    /**
     * Delete all archived incidents
     */
    public function deleteAllArchivedIncidents(Request $request)
    {
        try {
            DB::beginTransaction();

            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $archivedIncidents = Incident::where('status', 'Archived')->get();
            $totalCount = $archivedIncidents->count();

            if ($totalCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No archived incidents to delete'
                ], 400);
            }

            $deletedIds = [];
            foreach ($archivedIncidents as $incident) {
                // Delete related data first
                $this->deleteIncidentRelatedData($incident);

                $deletedIds[] = $incident->id;
                $incident->delete();
            }

            DB::commit();

            // Log the bulk deletion
            Log::info("All archived incidents deleted", [
                'total_deleted' => $totalCount,
                'deleted_ids' => $deletedIds,
                'deleted_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$totalCount} archived incidents",
                'total_deleted' => $totalCount,
                'deleted_ids' => $deletedIds
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete all archived incidents error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete archived incidents'
            ], 500);
        }
    }

    /**
     * Schedule automatic cleanup of archived incidents
     */
    public function scheduleArchivedCleanup(Request $request)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'schedule_type' => 'required|in:monthly,quarterly,yearly',
                'auto_delete' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Here you would typically store the schedule in database or config
            // For now, we'll just return success and log the schedule
            $schedule = [
                'schedule_type' => $request->schedule_type,
                'auto_delete' => $request->auto_delete,
                'scheduled_by' => $request->user()->id,
                'scheduled_at' => now()
            ];

            Log::info("Archived incidents cleanup scheduled", $schedule);

            return response()->json([
                'success' => true,
                'message' => "Automatic cleanup scheduled for {$request->schedule_type} deletion",
                'schedule' => $schedule
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule archived cleanup error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule cleanup'
            ], 500);
        }
    }

    /**
     * Helper method to delete incident related data
     */
    private function deleteIncidentRelatedData(Incident $incident)
    {
        // Delete population data if exists
        if ($incident->populationData) {
            $incident->populationData->delete();
        }

        // Delete infrastructure status if exists
        if ($incident->infrastructureStatus) {
            $incident->infrastructureStatus->delete();
        }

        // Delete families and members if exist
        if ($incident->families->isNotEmpty()) {
            foreach ($incident->families as $family) {
                $family->members()->delete();
                $family->delete();
            }
        }
    }

    /**
     * Calculate estimated size of archived incidents in MB
     */
    private function calculateArchivedIncidentsSize($incidents)
    {
        $totalSize = 0;

        foreach ($incidents as $incident) {
            // Estimate size based on data complexity
            $size = 1; // Base size in KB

            if ($incident->populationData) $size += 0.5;
            if ($incident->infrastructureStatus) $size += 0.5;
            if ($incident->families->isNotEmpty()) {
                $size += ($incident->families->count() * 0.2);
                foreach ($incident->families as $family) {
                    $size += ($family->members->count() * 0.1);
                }
            }

            $totalSize += $size;
        }

        // Convert to MB (rough estimate)
        return round($totalSize / 1024, 2);
    }
}
