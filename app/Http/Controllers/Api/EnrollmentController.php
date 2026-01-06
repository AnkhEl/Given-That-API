<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserProgress;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    use ApiResponse;

    /**
     * Display enrollments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Enrollment::with(['user', 'course', 'company']);

        // For company admins, show only their company's enrollments
        if ($request->user()->isCompanyAdmin()) {
            $query->byCompany($request->user()->company_id);
        }

        // For trainees, show only their own enrollments
        if ($request->user()->isTrainee()) {
            $query->byUser($request->user()->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by user (admins/curators only)
        if ($request->has('user_id') && $request->user()->hasAnyRole(['super_admin', 'curator'])) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $enrollments = $query->latest()->paginate($perPage);

        return $this->paginatedResponse($enrollments, 'Enrollments retrieved successfully');
    }

    /**
     * Enroll user(s) in a course.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $course = Course::findOrFail($request->course_id);

        // Check if course is published
        if (!$course->isPublished()) {
            return $this->errorResponse('Cannot enroll in unpublished course', 400);
        }

        $user = $request->user();
        $enrollments = [];
        $errors = [];

        foreach ($request->user_ids as $userId) {
            $targetUser = User::findOrFail($userId);

            // Authorization checks
            if ($user->isTrainee() && $userId != $user->id) {
                $errors[] = "Cannot enroll other users (User ID: {$userId})";
                continue;
            }

            if ($user->isCompanyAdmin() && $targetUser->company_id != $user->company_id) {
                $errors[] = "Cannot enroll users from other companies (User ID: {$userId})";
                continue;
            }

            // Check if already enrolled
            $existing = Enrollment::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->first();

            if ($existing) {
                $errors[] = "User {$targetUser->name} is already enrolled";
                continue;
            }

            // Create enrollment
            $enrollment = Enrollment::create([
                'user_id' => $userId,
                'course_id' => $course->id,
                'company_id' => $targetUser->company_id,
                'status' => 'enrolled',
            ]);

            // Create progress records for all modules
            foreach ($course->modules as $module) {
                UserProgress::create([
                    'enrollment_id' => $enrollment->id,
                    'module_id' => $module->id,
                    'user_id' => $userId,
                    'status' => 'not_started',
                ]);
            }

            $enrollments[] = $enrollment;
        }

        if (empty($enrollments) && !empty($errors)) {
            return $this->errorResponse('Enrollment failed', 400, $errors);
        }

        $message = count($enrollments) . ' user(s) enrolled successfully';
        if (!empty($errors)) {
            $message .= ' (with ' . count($errors) . ' error(s))';
        }

        return $this->createdResponse([
            'enrollments' => EnrollmentResource::collection(collect($enrollments)),
            'errors' => $errors,
        ], $message);
    }

    /**
     * Display the specified enrollment.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $enrollment = Enrollment::with(['user', 'course', 'progress.module'])->findOrFail($id);

        // Authorization check
        if ($request->user()->isTrainee() && $enrollment->user_id != $request->user()->id) {
            return $this->forbiddenResponse('Cannot view other users\' enrollments');
        }

        if ($request->user()->isCompanyAdmin() && $enrollment->company_id != $request->user()->company_id) {
            return $this->forbiddenResponse('Cannot view enrollments from other companies');
        }

        return $this->successResponse(
            new EnrollmentResource($enrollment),
            'Enrollment retrieved successfully'
        );
    }

    /**
     * Update enrollment status (e.g., drop course).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:dropped',
        ]);

        $enrollment = Enrollment::findOrFail($id);

        // Authorization check
        if ($request->user()->isTrainee() && $enrollment->user_id != $request->user()->id) {
            return $this->forbiddenResponse('Cannot update other users\' enrollments');
        }

        if ($request->user()->isCompanyAdmin() && $enrollment->company_id != $request->user()->company_id) {
            return $this->forbiddenResponse('Cannot update enrollments from other companies');
        }

        // Cannot drop completed course
        if ($enrollment->isCompleted()) {
            return $this->errorResponse('Cannot drop a completed course', 400);
        }

        $enrollment->update(['status' => $request->status]);

        return $this->successResponse(
            new EnrollmentResource($enrollment->fresh()),
            'Enrollment updated successfully'
        );
    }

    /**
     * Get my enrollments.
     */
    public function myEnrollments(Request $request): JsonResponse
    {
        $query = Enrollment::with(['course.sourceSkill', 'course.targetSkill'])
            ->byUser($request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->latest('enrolled_at')->get();

        return $this->successResponse(
            EnrollmentResource::collection($enrollments),
            'My enrollments retrieved successfully'
        );
    }

    /**
     * Get enrollment progress summary.
     */
    public function progress(Request $request, string $id): JsonResponse
    {
        $enrollment = Enrollment::with(['progress.module'])->findOrFail($id);

        // Authorization check
        if ($request->user()->isTrainee() && $enrollment->user_id != $request->user()->id) {
            return $this->forbiddenResponse();
        }

        $summary = [
            'enrollment_id' => $enrollment->id,
            'course_title' => $enrollment->course->title,
            'status' => $enrollment->status,
            'completion_percentage' => $enrollment->completion_percentage,
            'enrolled_at' => $enrollment->enrolled_at,
            'started_at' => $enrollment->started_at,
            'completed_at' => $enrollment->completed_at,
            'total_modules' => $enrollment->total_modules_count,
            'completed_modules' => $enrollment->completed_modules_count,
            'total_time_spent' => $enrollment->total_time_spent,
            'modules' => $enrollment->progress->map(function ($progress) {
                return [
                    'id' => $progress->id,
                    'module_id' => $progress->module_id,
                    'module_title' => $progress->module->title,
                    'module_order' => $progress->module->order_index,
                    'status' => $progress->status,
                    'progress_percentage' => $progress->progress_percentage,
                    'time_spent_seconds' => $progress->time_spent_seconds,
                    'started_at' => $progress->started_at,
                    'completed_at' => $progress->completed_at,
                ];
            }),
        ];

        return $this->successResponse($summary, 'Enrollment progress retrieved successfully');
    }

    /**
     * Start a module.
     */
    public function startModule(Request $request, string $enrollmentId, string $moduleId): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        // Authorization check
        if ($request->user()->id != $enrollment->user_id) {
            return $this->forbiddenResponse();
        }

        // Mark enrollment as started if not already
        if ($enrollment->status === 'enrolled') {
            $enrollment->markAsStarted();
        }

        // Update last accessed
        $enrollment->updateLastAccessed();

        // Find or create progress record
        $progress = UserProgress::firstOrCreate([
            'enrollment_id' => $enrollmentId,
            'module_id' => $moduleId,
            'user_id' => $request->user()->id,
        ]);

        // Mark as started if not already
        if ($progress->isNotStarted()) {
            $progress->markAsStarted();
        }

        return $this->successResponse(
            [
                'progress_id' => $progress->id,
                'status' => $progress->status,
                'started_at' => $progress->started_at,
            ],
            'Module started successfully'
        );
    }

    /**
     * Complete a module.
     */
    public function completeModule(Request $request, string $enrollmentId, string $moduleId): JsonResponse
    {
        $request->validate([
            'time_spent_seconds' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $enrollment = Enrollment::findOrFail($enrollmentId);

        // Authorization check
        if ($request->user()->id != $enrollment->user_id) {
            return $this->forbiddenResponse();
        }

        $progress = UserProgress::where('enrollment_id', $enrollmentId)
            ->where('module_id', $moduleId)
            ->firstOrFail();

        // Add time spent
        $progress->addTimeSpent($request->time_spent_seconds);

        // Save notes if provided
        if ($request->has('notes')) {
            $progress->update(['notes' => $request->notes]);
        }

        // Mark as completed
        $progress->markAsCompleted();

        // Get updated enrollment
        $enrollment->fresh();

        return $this->successResponse([
            'progress' => [
                'id' => $progress->id,
                'status' => $progress->status,
                'completed_at' => $progress->completed_at,
                'time_spent_seconds' => $progress->time_spent_seconds,
            ],
            'enrollment' => [
                'completion_percentage' => $enrollment->completion_percentage,
                'status' => $enrollment->status,
                'completed_at' => $enrollment->completed_at,
            ],
        ], 'Module completed successfully');
    }

    /**
     * Update module progress (for resume functionality).
     */
    public function updateProgress(Request $request, string $enrollmentId, string $moduleId): JsonResponse
    {
        $request->validate([
            'last_position' => 'nullable|string',
            'time_spent_seconds' => 'nullable|integer|min:0',
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $enrollment = Enrollment::findOrFail($enrollmentId);

        // Authorization check
        if ($request->user()->id != $enrollment->user_id) {
            return $this->forbiddenResponse();
        }

        $progress = UserProgress::where('enrollment_id', $enrollmentId)
            ->where('module_id', $moduleId)
            ->firstOrFail();

        // Update fields
        if ($request->has('last_position')) {
            $progress->updatePosition($request->last_position);
        }

        if ($request->has('time_spent_seconds')) {
            $progress->addTimeSpent($request->time_spent_seconds);
        }

        if ($request->has('progress_percentage')) {
            $progress->update(['progress_percentage' => $request->progress_percentage]);
        }

        if ($request->has('notes')) {
            $progress->update(['notes' => $request->notes]);
        }

        // Update last accessed
        $enrollment->updateLastAccessed();

        return $this->successResponse([
            'progress_id' => $progress->id,
            'last_position' => $progress->last_position,
            'time_spent_seconds' => $progress->time_spent_seconds,
            'progress_percentage' => $progress->progress_percentage,
        ], 'Progress updated successfully');
    }
}
