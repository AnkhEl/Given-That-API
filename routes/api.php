<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseModuleController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SkillMetricFileController;
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

    // Public certificate verification (no auth required)
    Route::get('/certificates/verify/{certificateNumber}', [CertificateController::class, 'verify']);

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

            // Assessments routes
            Route::prefix('{course}/assessments')->group(function () {
                // View assessments (all authenticated users)
                Route::get('/', [AssessmentController::class, 'index']);
                Route::get('/{assessment}', [AssessmentController::class, 'show']);

                // Submit assessment (trainees)
                Route::post('/{assessment}/submit', [AssessmentController::class, 'submit']);

                // View attempts
                Route::get('/{assessment}/attempts', [AssessmentController::class, 'attempts']);
                Route::get('/{assessment}/attempts/{attempt}', [AssessmentController::class, 'showAttempt']);

                // Manage assessments (curators and admins only)
                Route::middleware('role:super_admin,curator')->group(function () {
                    Route::post('/', [AssessmentController::class, 'store']);
                    Route::put('/{assessment}', [AssessmentController::class, 'update']);
                    Route::patch('/{assessment}', [AssessmentController::class, 'update']);
                    Route::delete('/{assessment}', [AssessmentController::class, 'destroy']);
                });
            });
        });

        // Enrollments routes
        Route::prefix('enrollments')->group(function () {
            // List enrollments (filtered by role)
            Route::get('/', [EnrollmentController::class, 'index']);
            Route::get('/my', [EnrollmentController::class, 'myEnrollments']);
            Route::get('/{enrollment}', [EnrollmentController::class, 'show']);
            Route::get('/{enrollment}/progress', [EnrollmentController::class, 'progress']);

            // Enroll in course
            Route::post('/', [EnrollmentController::class, 'store']);

            // Update enrollment (e.g., drop course)
            Route::put('/{enrollment}', [EnrollmentController::class, 'update']);
            Route::patch('/{enrollment}', [EnrollmentController::class, 'update']);

            // Progress tracking
            Route::post('/{enrollment}/modules/{module}/start', [EnrollmentController::class, 'startModule']);
            Route::post('/{enrollment}/modules/{module}/complete', [EnrollmentController::class, 'completeModule']);
            Route::post('/{enrollment}/modules/{module}/progress', [EnrollmentController::class, 'updateProgress']);

            // Issue certificate for enrollment
            Route::post('/{enrollment}/certificate/issue', [CertificateController::class, 'issue']);
        });

        // Certificates routes
        Route::prefix('certificates')->group(function () {
            // List certificates (role-filtered)
            Route::get('/', [CertificateController::class, 'index']);
            Route::get('/my', [CertificateController::class, 'myCertificates']);
            Route::get('/{certificate}', [CertificateController::class, 'show']);
            Route::get('/{certificate}/download', [CertificateController::class, 'download']);

            // Admin only - revoke certificate
            Route::middleware('role:super_admin,company_admin')->group(function () {
                Route::delete('/{certificate}', [CertificateController::class, 'destroy']);
            });
        });

        // Notifications routes
        Route::prefix('notifications')->group(function () {
            // Get notifications
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread', [NotificationController::class, 'unread']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::get('/statistics', [NotificationController::class, 'statistics']);
            Route::get('/{notification}', [NotificationController::class, 'show']);

            // Mark as read/unread
            Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead']);
            Route::post('/{notification}/mark-unread', [NotificationController::class, 'markAsUnread']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);

            // Delete notifications
            Route::delete('/{notification}', [NotificationController::class, 'destroy']);
            Route::delete('/read/all', [NotificationController::class, 'deleteRead']);
            Route::delete('/all/clear', [NotificationController::class, 'deleteAll']);
        });

        // Skill Metric Files routes
        Route::prefix('skill-metric-files')->middleware('role:super_admin,company_admin')->group(function () {
            Route::post('/upload', [SkillMetricFileController::class, 'upload']);
            Route::get('/', [SkillMetricFileController::class, 'index']);
            Route::get('/statistics', [SkillMetricFileController::class, 'statistics']);
            Route::get('/{file}', [SkillMetricFileController::class, 'show']);
            Route::get('/{file}/download', [SkillMetricFileController::class, 'download']);
            Route::post('/{file}/reprocess', [SkillMetricFileController::class, 'reprocess']);
            Route::delete('/{file}', [SkillMetricFileController::class, 'destroy']);
        });

        // TODO: Add more API endpoints here
        // - Admin Analytics
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
