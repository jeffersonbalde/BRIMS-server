<?php
// app/Http/Controllers/AdminController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
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

            $users = User::orderBy('created_at', 'desc')->get();

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

    // Add this method to AdminController.php
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
}
