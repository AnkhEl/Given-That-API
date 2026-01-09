<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SkillMetricFile\UploadSkillMetricFileRequest;
use App\Http\Resources\SkillMetricFileResource;
use App\Jobs\ProcessSkillMetricFile;
use App\Models\SkillMetricFile;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SkillMetricFileController extends Controller
{
    use ApiResponse;

    /**
     * List skill metric files.
     * - Company admins see their company's files
     * - Super admins see all
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SkillMetricFile::with(['company', 'uploader']);

        // Role-based filtering
        if ($user->isCompanyAdmin() || $user->isTrainee()) {
            $query->where('company_id', $user->company_id);
        }
        // Super admins see all

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $files = $query->latest()->paginate($perPage);

        return $this->successResponse(
            SkillMetricFileResource::collection($files)->response()->getData(true),
            'Skill metric files retrieved successfully'
        );
    }

    /**
     * Upload and process a skill metric file.
     */
    public function upload(UploadSkillMetricFileRequest $request): JsonResponse
    {
        $user = $request->user();

        // Handle file upload
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $fileType = $file->getClientOriginalExtension();

        // Generate unique filename
        $filename = time() . '_' . uniqid() . '.' . $fileType;

        // Store file (S3 in production, local for development)
        $path = $file->storeAs(
            'skill-metric-files/' . $user->company_id,
            $filename,
            config('filesystems.default') // 's3' in production, 'public' in dev
        );

        // Create database record
        $skillMetricFile = SkillMetricFile::create([
            'company_id' => $user->company_id,
            'uploaded_by' => $user->id,
            'original_filename' => $originalName,
            'file_path' => $path,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'status' => 'pending',
            'trainee_count' => $request->trainee_count ?? 1,
            'timeline_weeks' => $request->timeline_weeks,
            'special_requirements' => $request->special_requirements,
        ]);

        // Dispatch background job for processing
        ProcessSkillMetricFile::dispatch($skillMetricFile);

        return $this->createdResponse(
            new SkillMetricFileResource($skillMetricFile),
            'File uploaded successfully. Processing started.'
        );
    }

    /**
     * Get a specific skill metric file.
     */
    public function show(string $fileId, Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SkillMetricFile::with(['company', 'uploader', 'courses']);

        // Authorization
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $file = $query->findOrFail($fileId);

        return $this->successResponse(
            new SkillMetricFileResource($file),
            'Skill metric file retrieved successfully'
        );
    }

    /**
     * Reprocess a failed file.
     */
    public function reprocess(string $fileId, Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SkillMetricFile::query();

        // Authorization
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $file = $query->findOrFail($fileId);

        // Can only reprocess failed files
        if ($file->status !== 'failed') {
            return $this->errorResponse(
                'Can only reprocess failed files',
                400
            );
        }

        // Reset status
        $file->update([
            'status' => 'pending',
            'error_message' => null,
            'processed_at' => null,
        ]);

        // Dispatch job again
        ProcessSkillMetricFile::dispatch($file);

        return $this->successResponse(
            new SkillMetricFileResource($file->fresh()),
            'File reprocessing started'
        );
    }

    /**
     * Delete a skill metric file.
     */
    public function destroy(string $fileId, Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SkillMetricFile::query();

        // Authorization
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $file = $query->findOrFail($fileId);

        // Check if courses were generated
        if ($file->courses()->exists()) {
            return $this->errorResponse(
                'Cannot delete file that has generated courses. Delete courses first.',
                400
            );
        }

        // Delete physical file
        if (Storage::exists($file->file_path)) {
            Storage::delete($file->file_path);
        }

        $file->delete();

        return $this->deletedResponse('Skill metric file deleted successfully');
    }

    /**
     * Download the original file.
     */
    public function download(string $fileId, Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SkillMetricFile::query();

        // Authorization
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $file = $query->findOrFail($fileId);

        // Check if file exists
        if (!Storage::exists($file->file_path)) {
            return $this->errorResponse('File not found in storage', 404);
        }

        // In a real implementation, you would:
        // return Storage::download($file->file_path, $file->original_filename);

        // For API, return the download URL
        $url = Storage::url($file->file_path);

        return $this->successResponse([
            'download_url' => $url,
            'filename' => $file->original_filename,
            'file_size' => $file->file_size,
            'file_type' => $file->file_type,
        ], 'Download URL generated');
    }

    /**
     * Get statistics about skill metric files.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SkillMetricFile::query();

        // Role-based filtering
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $total = $query->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $processing = (clone $query)->where('status', 'processing')->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        // Count by file type
        $byType = (clone $query)
            ->selectRaw('file_type, COUNT(*) as count')
            ->groupBy('file_type')
            ->pluck('count', 'file_type');

        return $this->successResponse([
            'total' => $total,
            'by_status' => [
                'pending' => $pending,
                'processing' => $processing,
                'completed' => $completed,
                'failed' => $failed,
            ],
            'by_file_type' => $byType,
        ], 'Statistics retrieved successfully');
    }
}
