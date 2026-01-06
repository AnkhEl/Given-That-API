<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SkillController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API v1 routes
Route::prefix('v1')->group(function () {

    // Public routes (no authentication required)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {

        // Auth routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Skills routes
        Route::prefix('skills')->group(function () {
            // Public (authenticated users can view)
            Route::get('/', [SkillController::class, 'index']);
            Route::get('/categories', [SkillController::class, 'categories']);
            Route::get('/popular', [SkillController::class, 'popular']);
            Route::get('/{skill}', [SkillController::class, 'show']);

            // Admin only (super_admin can manage)
            Route::middleware('role:super_admin')->group(function () {
                Route::post('/', [SkillController::class, 'store']);
                Route::put('/{skill}', [SkillController::class, 'update']);
                Route::patch('/{skill}', [SkillController::class, 'update']);
                Route::delete('/{skill}', [SkillController::class, 'destroy']);
                Route::post('/{skill}/increment-popularity', [SkillController::class, 'incrementPopularity']);
            });
        });

        // TODO: Add more API endpoints here
        // - Courses
        // - Enrollments
        // - Progress
        // - Certificates
        // - Notifications
    });
});

// Health check endpoint (no version prefix)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'Given That API',
    ]);
});
