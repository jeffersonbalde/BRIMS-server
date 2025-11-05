<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PopulationController;
use App\Http\Controllers\ReportController;
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

    // Profile routes
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::put('/profile/change-password', [AuthController::class, 'changePassword']);

    // Avatar management routes
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'removeAvatar']);

    // FIXED: Incident routes - Define stats BEFORE apiResource
    Route::get('/incidents/stats', [IncidentController::class, 'stats']);
    Route::apiResource('incidents', IncidentController::class);

    // Add these routes in the auth:sanctum group
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'deleteAll']); // Add this line
    });


    // Population Data Routes
    Route::post('/incidents/{incident}/population-data', [IncidentController::class, 'storePopulationData']);
    Route::get('/incidents/{incident}/population-data', [IncidentController::class, 'getPopulationData']);

    // Infrastructure Status Routes
    Route::post('/incidents/{incident}/infrastructure-status', [IncidentController::class, 'storeInfrastructureStatus']);
    Route::get('/incidents/{incident}/infrastructure-status', [IncidentController::class, 'getInfrastructureStatus']);


    // Analytics routes
    Route::prefix('analytics')->group(function () {
        Route::get('/municipal', [AnalyticsController::class, 'getMunicipalAnalytics']);
        Route::get('/barangay', [AnalyticsController::class, 'getBarangayAnalytics']);
    });

    // Reports routes
    Route::prefix('reports')->group(function () {
        Route::post('/municipal', [ReportController::class, 'generateMunicipalReport']);
        Route::post('/barangay', [ReportController::class, 'generateBarangayReport']);
    });

    // Population routes
    Route::prefix('population')->group(function () {
        Route::get('/barangay-overview', [PopulationController::class, 'getBarangayOverview']);
        Route::get('/municipal-overview', [PopulationController::class, 'getMunicipalOverview']);
        Route::get('/affected', [PopulationController::class, 'getAffectedPopulation']);
    });

    // Admin routes
    Route::prefix('admin')->group(function () {
        // User approval management
        Route::get('/pending-users', [AdminController::class, 'getPendingUsers']);
        Route::post('/users/{user}/approve', [AdminController::class, 'approveUser']);
        Route::post('/users/{user}/reject', [AdminController::class, 'rejectUser']);
        Route::get('/users', [AdminController::class, 'getAllUsers']);

        // FIXED: Remove duplicate /admin prefix
        Route::get('/users/{user}/details', [AdminController::class, 'getUserDetails']);
        Route::get('/pending-users-count', [AdminController::class, 'getPendingUsersCount']);

        // Incident management routes
        Route::get('/incidents', [AdminController::class, 'getAllIncidents']);
        Route::put('/incidents/{incident}/status', [AdminController::class, 'updateIncidentStatus']);

        Route::put('/incidents/{incident}/archive', [AdminController::class, 'archiveIncident']);
        Route::put('/incidents/{incident}/unarchive', [AdminController::class, 'unarchiveIncident']);

        // Account status management
        Route::post('/users/{user}/deactivate', [AdminController::class, 'deactivateUser']);
        Route::post('/users/{user}/reactivate', [AdminController::class, 'reactivateUser']);

        // In the admin routes group, add:
        Route::get('/barangays/population-data', [AdminController::class, 'getAllBarangaysWithPopulationData']);
    });
});
