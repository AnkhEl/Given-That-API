<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreAssessmentRequest;
use App\Http\Requests\Assessment\SubmitAssessmentRequest;
use App\Http\Requests\Assessment\UpdateAssessmentRequest;
use App\Http\Resources\AssessmentAttemptResource;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    use ApiResponse;

    /**
     * Display assessments for a course.
     */
    public function index(string $courseId): JsonResponse
    {
        $assessments = Assessment::where('course_id', $courseId)
            ->with('module')
            ->get();

        return $this->successResponse(
            AssessmentResource::collection($assessments),
            'Assessments retrieved successfully'
        );
    }

    /**
     * Store a newly created assessment.
     */
    public function store(string $courseId, StoreAssessmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['course_id'] = $courseId;

        $assessment = Assessment::create($data);

        return $this->createdResponse(
            new AssessmentResource($assessment),
            'Assessment created successfully'
        );
    }

    /**
     * Display the specified assessment.
     */
    public function show(string $courseId, string $assessmentId, Request $request): JsonResponse
    {
        $assessment = Assessment::where('course_id', $courseId)
            ->findOrFail($assessmentId);

        // Get user's attempts if trainee
        if ($request->user()->isTrainee()) {
            $attempts = $assessment->attemptsByUser($request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $remainingAttempts = $assessment->getRemainingAttempts($request->user()->id);
            $bestScore = $assessment->getBestScore($request->user()->id);
            $hasPassed = $assessment->hasUserPassed($request->user()->id);

            return $this->successResponse([
                'assessment' => new AssessmentResource($assessment),
                'user_data' => [
                    'attempts' => AssessmentAttemptResource::collection($attempts),
                    'remaining_attempts' => $remainingAttempts,
                    'best_score' => $bestScore,
                    'has_passed' => $hasPassed,
                ],
            ], 'Assessment retrieved successfully');
        }

        return $this->successResponse(
            new AssessmentResource($assessment),
            'Assessment retrieved successfully'
        );
    }

    /**
     * Update the specified assessment.
     */
    public function update(string $courseId, string $assessmentId, UpdateAssessmentRequest $request): JsonResponse
    {
        $assessment = Assessment::where('course_id', $courseId)
            ->findOrFail($assessmentId);

        $assessment->update($request->validated());

        return $this->successResponse(
            new AssessmentResource($assessment->fresh()),
            'Assessment updated successfully'
        );
    }

    /**
     * Remove the specified assessment.
     */
    public function destroy(string $courseId, string $assessmentId): JsonResponse
    {
        $assessment = Assessment::where('course_id', $courseId)
            ->findOrFail($assessmentId);

        // Check if there are any attempts
        if ($assessment->attempts()->count() > 0) {
            return $this->errorResponse(
                'Cannot delete assessment with existing attempts',
                400
            );
        }

        $assessment->delete();

        return $this->deletedResponse('Assessment deleted successfully');
    }

    /**
     * Submit an assessment attempt.
     */
    public function submit(string $courseId, string $assessmentId, SubmitAssessmentRequest $request): JsonResponse
    {
        $assessment = Assessment::where('course_id', $courseId)
            ->findOrFail($assessmentId);

        $user = $request->user();

        // Check if user has attempts remaining
        if (!$assessment->hasAttemptsRemaining($user->id)) {
            return $this->errorResponse(
                'Maximum attempts reached for this assessment',
                400
            );
        }

        // Find active enrollment
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->active()
            ->firstOrFail();

        // Calculate attempt number
        $attemptNumber = $assessment->attemptsByUser($user->id)->count() + 1;

        // Create attempt
        $attempt = AssessmentAttempt::create([
            'assessment_id' => $assessment->id,
            'user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'attempt_number' => $attemptNumber,
            'answers' => $request->answers,
            'started_at' => $request->started_at ?? now(),
            'submitted_at' => now(),
            'time_taken_seconds' => $request->time_taken_seconds,
        ]);

        // Calculate score
        $attempt->calculateScore();

        // Refresh to get updated values
        $attempt->fresh();

        // Send notification based on pass/fail
        if ($attempt->passed) {
            NotificationService::assessmentPassed($user, $assessment, $attempt);
        } else {
            NotificationService::assessmentFailed($user, $assessment, $attempt);
        }

        return $this->createdResponse([
            'attempt' => new AssessmentAttemptResource($attempt),
            'remaining_attempts' => $assessment->getRemainingAttempts($user->id),
        ], 'Assessment submitted successfully');
    }

    /**
     * Get user's attempts for an assessment.
     */
    public function attempts(string $courseId, string $assessmentId, Request $request): JsonResponse
    {
        $assessment = Assessment::where('course_id', $courseId)
            ->findOrFail($assessmentId);

        $userId = $request->get('user_id', $request->user()->id);

        // Authorization check
        if ($request->user()->isTrainee() && $userId != $request->user()->id) {
            return $this->forbiddenResponse('Cannot view other users\' attempts');
        }

        $attempts = $assessment->attemptsByUser($userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(
            AssessmentAttemptResource::collection($attempts),
            'Assessment attempts retrieved successfully'
        );
    }

    /**
     * Get a specific attempt.
     */
    public function showAttempt(string $courseId, string $assessmentId, string $attemptId, Request $request): JsonResponse
    {
        $attempt = AssessmentAttempt::where('assessment_id', $assessmentId)
            ->findOrFail($attemptId);

        // Authorization check
        if ($request->user()->isTrainee() && $attempt->user_id != $request->user()->id) {
            return $this->forbiddenResponse('Cannot view other users\' attempts');
        }

        return $this->successResponse(
            new AssessmentAttemptResource($attempt),
            'Assessment attempt retrieved successfully'
        );
    }
}
