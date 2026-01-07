<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    use ApiResponse;

    /**
     * Display certificates.
     * - Trainees see only their own
     * - Company admins see their company's
     * - Super admins see all
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Certificate::with(['user', 'course', 'enrollment']);

        // Role-based filtering
        if ($user->isTrainee()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isCompanyAdmin()) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }
        // Super admins see all (no filter)

        // Optional filters
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('user_id') && !$user->isTrainee()) {
            $query->where('user_id', $request->user_id);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $certificates = $query->latest('issued_at')->paginate($perPage);

        return $this->successResponse(
            CertificateResource::collection($certificates)->response()->getData(true),
            'Certificates retrieved successfully'
        );
    }

    /**
     * Get current user's certificates.
     */
    public function myCertificates(Request $request): JsonResponse
    {
        $certificates = Certificate::where('user_id', $request->user()->id)
            ->with(['course', 'enrollment'])
            ->latest('issued_at')
            ->get();

        return $this->successResponse(
            CertificateResource::collection($certificates),
            'Your certificates retrieved successfully'
        );
    }

    /**
     * Issue a certificate for a completed enrollment.
     * Automatically called when course is completed, or manually by admin.
     */
    public function issue(Request $request, string $enrollmentId): JsonResponse
    {
        $enrollment = Enrollment::with(['user', 'course'])->findOrFail($enrollmentId);

        // Authorization check
        $user = $request->user();
        if ($user->isTrainee() && $enrollment->user_id !== $user->id) {
            return $this->forbiddenResponse('Cannot issue certificate for another user');
        }

        // Check if enrollment is completed
        if ($enrollment->status !== 'completed') {
            return $this->errorResponse(
                'Cannot issue certificate. Course must be completed first.',
                400
            );
        }

        // Check if certificate already exists
        if ($enrollment->certificate) {
            return $this->errorResponse(
                'Certificate already issued for this enrollment',
                400
            );
        }

        // Generate certificate
        $certificate = $this->generateCertificate($enrollment);

        // Update enrollment
        $enrollment->update([
            'certificate_issued_at' => now(),
            'certificate_url' => $certificate->certificate_url,
        ]);

        // Send notification about certificate issuance
        NotificationService::certificateIssued($enrollment->user, $certificate);

        return $this->createdResponse(
            new CertificateResource($certificate),
            'Certificate issued successfully'
        );
    }

    /**
     * Display the specified certificate.
     */
    public function show(string $certificateId, Request $request): JsonResponse
    {
        $certificate = Certificate::with(['user', 'course', 'enrollment'])
            ->findOrFail($certificateId);

        // Authorization check
        $user = $request->user();
        if ($user->isTrainee() && $certificate->user_id !== $user->id) {
            return $this->forbiddenResponse('Cannot view other users\' certificates');
        }

        if ($user->isCompanyAdmin() && $certificate->user->company_id !== $user->company_id) {
            return $this->forbiddenResponse('Cannot view certificates from other companies');
        }

        return $this->successResponse(
            new CertificateResource($certificate),
            'Certificate retrieved successfully'
        );
    }

    /**
     * Verify a certificate by certificate number (public endpoint).
     */
    public function verify(string $certificateNumber): JsonResponse
    {
        $certificate = Certificate::where('certificate_number', $certificateNumber)
            ->with(['user', 'course'])
            ->first();

        if (!$certificate) {
            return $this->errorResponse('Certificate not found', 404);
        }

        // Check if expired
        $isExpired = $certificate->expires_at && $certificate->expires_at->isPast();

        return $this->successResponse([
            'valid' => !$isExpired,
            'certificate_number' => $certificate->certificate_number,
            'issued_to' => $certificate->user->name,
            'course_title' => $certificate->course->title,
            'issued_at' => $certificate->issued_at->toIso8601String(),
            'expires_at' => $certificate->expires_at?->toIso8601String(),
            'is_expired' => $isExpired,
        ], 'Certificate verification completed');
    }

    /**
     * Download certificate as PDF.
     * In a real implementation, this would generate/serve the actual PDF.
     */
    public function download(string $certificateId, Request $request): JsonResponse
    {
        $certificate = Certificate::findOrFail($certificateId);

        // Authorization check
        $user = $request->user();
        if ($user->isTrainee() && $certificate->user_id !== $user->id) {
            return $this->forbiddenResponse('Cannot download other users\' certificates');
        }

        if ($user->isCompanyAdmin() && $certificate->user->company_id !== $user->company_id) {
            return $this->forbiddenResponse('Cannot download certificates from other companies');
        }

        // In real implementation, you would:
        // 1. Generate PDF using a library (e.g., barryvdh/laravel-dompdf)
        // 2. Return the file as a download
        // For now, return the URL

        return $this->successResponse([
            'download_url' => $certificate->certificate_url,
            'certificate_number' => $certificate->certificate_number,
        ], 'Certificate download link generated');
    }

    /**
     * Delete a certificate (admin only).
     * Only soft delete - for auditing purposes.
     */
    public function destroy(string $certificateId): JsonResponse
    {
        $certificate = Certificate::findOrFail($certificateId);

        // Update enrollment to remove certificate reference
        if ($certificate->enrollment) {
            $certificate->enrollment->update([
                'certificate_issued_at' => null,
                'certificate_url' => null,
            ]);
        }

        $certificate->delete();

        return $this->deletedResponse('Certificate revoked successfully');
    }

    /**
     * Generate certificate record.
     * In production, this would also generate the actual PDF.
     */
    protected function generateCertificate(Enrollment $enrollment): Certificate
    {
        // Calculate metadata
        $totalTimeSpent = $enrollment->progress->sum('time_spent_seconds');
        $totalHours = round($totalTimeSpent / 3600, 2);

        // Get average assessment score
        $assessmentScores = $enrollment->assessmentAttempts()
            ->where('passed', true)
            ->pluck('score_percentage');
        $avgScore = $assessmentScores->avg() ?? 0;

        // Get skills earned (target skill from course)
        $skillsEarned = [$enrollment->course->targetSkill->name];

        // Generate certificate URL
        // In production, this would be the actual PDF file URL after generation
        $certificateUrl = $this->generateCertificateUrl($enrollment);

        // Generate verification URL
        $verificationUrl = $this->generateVerificationUrl();

        // Create certificate record
        $certificate = Certificate::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'certificate_url' => $certificateUrl,
            'verification_url' => $verificationUrl,
            'expires_at' => null, // null = never expires, or set to now()->addYears(2)
            'metadata' => [
                'completion_time_hours' => $totalHours,
                'average_score' => round($avgScore, 2),
                'skills_earned' => $skillsEarned,
                'completion_date' => $enrollment->completed_at->format('Y-m-d'),
                'course_duration_weeks' => $enrollment->course->estimated_hours ?
                    ceil($enrollment->course->estimated_hours / 40) : null,
            ],
        ]);

        return $certificate;
    }

    /**
     * Generate certificate URL.
     * In production, this would generate the actual PDF and upload to S3.
     */
    protected function generateCertificateUrl(Enrollment $enrollment): string
    {
        $certificateNumber = 'GT-' . now()->year . '-' . strtoupper(substr(md5(uniqid()), 0, 5));

        // In production:
        // 1. Generate PDF using certificate template
        // 2. Upload to S3
        // 3. Return S3 URL

        // For now, return a placeholder URL
        return config('app.url') . "/storage/certificates/{$certificateNumber}.pdf";
    }

    /**
     * Generate verification URL.
     */
    protected function generateVerificationUrl(): string
    {
        // This will be handled by the verify() method
        return config('app.url') . '/api/v1/certificates/verify/';
    }
}
