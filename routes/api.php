<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/check-auth', [AuthController::class, 'checkAuth']);
Route::get('/avatar/{filename}', [App\Http\Controllers\AuthController::class, 'serveAvatar']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Add these new routes for settings
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::put('/profile/change-password', [AuthController::class, 'changePassword']);

    // Admin routes
    Route::prefix('admin')->group(function () {
        // User approval management
        Route::get('/pending-users', [AdminController::class, 'getPendingUsers']);
        Route::post('/users/{user}/approve', [AdminController::class, 'approveUser']);
        Route::post('/users/{user}/reject', [AdminController::class, 'rejectUser']);
        Route::get('/users', [AdminController::class, 'getAllUsers']);

        // routes/api.php
        Route::get('/admin/users/{user}/details', [AdminController::class, 'getUserDetails']);

        // routes/api.php
        Route::get('/admin/pending-users-count', [AdminController::class, 'getPendingUsersCount']);
    });
});
