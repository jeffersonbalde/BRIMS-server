<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            DB::beginTransaction();

            // Custom validation rules with specific messages
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'barangayName' => 'required|string|max:255',
                'position' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'contact' => 'required|string|max:20',
                'municipality' => 'required|string|max:255',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            ], [
                'email.unique' => 'This email address is already registered.',
                'contact.required' => 'Contact number is required.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Additional duplicate checks with specific error messages
            $duplicateErrors = [];

            // Check for duplicate barangay name in same municipality
            $existingBarangay = User::where('barangay_name', $request->barangayName)
                ->where('municipality', $request->municipality)
                ->first();

            if ($existingBarangay) {
                $duplicateErrors['barangayName'] = ['A barangay with this name already exists in this municipality.'];
            }

            // Check for duplicate contact number
            $existingContact = User::where('contact', $request->contact)->first();
            if ($existingContact) {
                $duplicateErrors['contact'] = ['This contact number is already registered.'];
            }

            // Check for duplicate name + position combination
            $existingNamePosition = User::where('name', $request->name)
                ->where('position', $request->position)
                ->where('barangay_name', $request->barangayName)
                ->first();

            if ($existingNamePosition) {
                $duplicateErrors['name'] = ['A person with this name and position already exists in this barangay.'];
            }

            // If there are duplicate errors, return them
            if (!empty($duplicateErrors)) {
                return response()->json([
                    'message' => 'Duplicate data found',
                    'errors' => $duplicateErrors
                ], 422);
            }

            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
            }

            $user = User::create([
                'name' => $request->name,
                'barangay_name' => $request->barangayName,
                'position' => $request->position,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'contact' => $request->contact,
                'municipality' => $request->municipality,
                'avatar' => $avatarPath,
                'role' => 'barangay',
                'is_approved' => false,
                'is_active' => true,
                'status' => 'pending',
            ]);

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
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
                ],
                'message' => 'Registration successful! Waiting for admin approval. You can login but will have limited access until approved.'
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }

    // Add a new method to check for duplicates in real-time
    public function checkDuplicates(Request $request)
    {
        try {
            $field = $request->input('field');
            $value = $request->input('value');
            $currentMunicipality = $request->input('municipality');
            $currentBarangay = $request->input('barangayName');
            $currentName = $request->input('name');
            $currentPosition = $request->input('position');

            $exists = false;
            $message = '';

            switch ($field) {
                case 'email':
                    $exists = User::where('email', $value)->exists();
                    $message = $exists ? 'This email is already registered.' : '';
                    break;

                case 'contact':
                    $exists = User::where('contact', $value)->exists();
                    $message = $exists ? 'This contact number is already registered.' : '';
                    break;

                case 'barangayName':
                    if ($currentMunicipality) {
                        $exists = User::where('barangay_name', $value)
                            ->where('municipality', $currentMunicipality)
                            ->exists();
                        $message = $exists ? 'A barangay with this name already exists in this municipality.' : '';
                    }
                    break;

                case 'name':
                    if ($currentBarangay && $currentPosition) {
                        $exists = User::where('name', $value)
                            ->where('barangay_name', $currentBarangay)
                            ->where('position', $currentPosition)
                            ->exists();
                        $message = $exists ? 'A person with this name and position already exists in this barangay.' : '';
                    }
                    break;
            }

            return response()->json([
                'exists' => $exists,
                'message' => $message,
                'field' => $field
            ]);
        } catch (\Exception $e) {
            Log::error('Duplicate check error: ' . $e->getMessage());
            return response()->json([
                'exists' => false,
                'message' => ''
            ], 500);
        }
    }

    // ... rest of your existing methods (login, logout, user, checkAuth) remain the same
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            // Check if user exists
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.'
                ], 401);
            }

            // Check if user is active
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Your account has been deactivated. Please contact administrator.'
                ], 401);
            }

            // Check password
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.'
                ], 401);
            }

            // Allow login even if not approved, but with limited access
            if (!$user->is_approved) {
                // Create token for unapproved users (they'll have limited access)
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'access_token' => $token,
                    'token_type' => 'Bearer',
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
                        // Add these rejection fields:
                        'rejected_at' => $user->rejected_at,
                        'rejection_reason' => $user->rejection_reason,
                        'approved_at' => $user->approved_at,
                        'created_at' => $user->created_at,
                    ],
                    'warning' => 'Your account is pending approval. Some features may be limited.'
                ], 200);
            }

            // For approved users
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
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
                    // Add these rejection fields:
                    'rejected_at' => $user->rejected_at,
                    'rejection_reason' => $user->rejection_reason,
                    'approved_at' => $user->approved_at,
                    'created_at' => $user->created_at,
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Login failed. Please try again.'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Logout failed. Please try again.'
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            $user = $request->user();

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
                    // Add these rejection fields:
                    'rejected_at' => $user->rejected_at,
                    'rejection_reason' => $user->rejection_reason,
                    'approved_at' => $user->approved_at,
                    'created_at' => $user->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('User fetch error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch user data.'
            ], 500);
        }
    }

    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                return response()->json([
                    'authenticated' => true,
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
                        // Add these rejection fields:
                        'rejected_at' => $user->rejected_at,
                        'rejection_reason' => $user->rejection_reason,
                        'approved_at' => $user->approved_at,
                        'created_at' => $user->created_at,
                    ]
                ]);
            }

            return response()->json(['authenticated' => false]);
        } catch (\Exception $e) {
            Log::error('Auth check error: ' . $e->getMessage());
            return response()->json(['authenticated' => false]);
        }
    }

    public function serveAvatar($filename)
    {
        try {
            // Security check
            if (!preg_match('/^[a-zA-Z0-9._-]+\.(jpg|jpeg|png|gif|webp)$/', $filename)) {
                abort(404);
            }

            $path = storage_path('app/public/avatars/' . $filename);

            if (!file_exists($path)) {
                abort(404);
            }

            $fileSize = filesize($path);
            $fileTime = filemtime($path);
            $etag = md5($filename . $fileTime);

            // Check if client has cached version
            $ifNoneMatch = request()->header('If-None-Match');
            $ifModifiedSince = request()->header('If-Modified-Since');

            if (
                $ifNoneMatch === $etag ||
                ($ifModifiedSince && strtotime($ifModifiedSince) >= $fileTime)
            ) {
                return response()->noContent(304);
            }

            // Serve with caching headers
            return response()->file($path, [
                'Content-Type' => mime_content_type($path),
                'Content-Length' => $fileSize,
                'Cache-Control' => 'public, max-age=31536000, immutable', // 1 year
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000),
                'Last-Modified' => gmdate('D, d M Y H:i:s \G\M\T', $fileTime),
                'ETag' => $etag,
            ]);
        } catch (\Exception $e) {
            Log::error('Avatar serve error: ' . $e->getMessage());
            abort(404);
        }
    }

    // Add to AuthController.php
    public function updateProfile(Request $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'contact' => 'required|string|max:20',
                'position' => 'required|string|max:255',
            ], [
                'email.unique' => 'This email address is already registered.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate contact number
            $existingContact = User::where('contact', $request->contact)
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingContact) {
                return response()->json([
                    'message' => 'Duplicate data found',
                    'errors' => ['contact' => ['This contact number is already registered.']]
                ], 422);
            }

            // Update user profile
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'contact' => $request->contact,
                'position' => $request->position,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Profile updated successfully',
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
                    'rejected_at' => $user->rejected_at,
                    'rejection_reason' => $user->rejection_reason,
                    'approved_at' => $user->approved_at,
                    'created_at' => $user->created_at,
                ]
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Profile update failed. Please try again.'
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8',
                'confirm_password' => 'required|string|same:new_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Check current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['current_password' => ['The current password is incorrect.']]
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Password changed successfully'
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Password change error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Password change failed. Please try again.'
            ], 500);
        }
    }
}
