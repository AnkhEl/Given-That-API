<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseModuleController;
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

        // Courses routes
        Route::prefix('courses')->group(function () {
            // Public (authenticated users can view published courses)
            Route::get('/', [CourseController::class, 'index']);
            Route::get('/by-skill-transition', [CourseController::class, 'bySkillTransition']);
            Route::get('/{course}', [CourseController::class, 'show']);
            Route::get('/{course}/statistics', [CourseController::class, 'statistics']);

            // Curator and Admin only
            Route::middleware('role:super_admin,curator')->group(function () {
                Route::post('/', [CourseController::class, 'store']);
                Route::put('/{course}', [CourseController::class, 'update']);
                Route::patch('/{course}', [CourseController::class, 'update']);
                Route::delete('/{course}', [CourseController::class, 'destroy']);
                Route::post('/{course}/publish', [CourseController::class, 'publish']);
                Route::post('/{course}/archive', [CourseController::class, 'archive']);

                // Course Modules
                Route::prefix('{course}/modules')->group(function () {
                    Route::get('/', [CourseModuleController::class, 'index']);
                    Route::post('/', [CourseModuleController::class, 'store']);
                    Route::get('/{module}', [CourseModuleController::class, 'show']);
                    Route::put('/{module}', [CourseModuleController::class, 'update']);
                    Route::patch('/{module}', [CourseModuleController::class, 'update']);
                    Route::delete('/{module}', [CourseModuleController::class, 'destroy']);
                    Route::post('/{module}/publish', [CourseModuleController::class, 'publish']);
                    Route::post('/{module}/unpublish', [CourseModuleController::class, 'unpublish']);
                    Route::post('/reorder', [CourseModuleController::class, 'reorder']);
                });
            });
        });

        // TODO: Add more API endpoints here
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
