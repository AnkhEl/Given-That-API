<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'completion_percentage' => (float) $this->completion_percentage,
            'enrolled_at' => $this->enrolled_at->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),

            // User (if loaded)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            // Course (if loaded)
            'course' => $this->whenLoaded('course', function () {
                return new CourseResource($this->course);
            }),

            // Company (if loaded)
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),

            // Progress (if loaded)
            'progress' => $this->whenLoaded('progress', function () {
                return UserProgressResource::collection($this->progress);
            }),

            // Certificate
            'certificate_issued_at' => $this->certificate_issued_at?->toIso8601String(),
            'certificate_url' => $this->certificate_url,

            // Computed attributes
            'completed_modules_count' => $this->when(
                isset($this->completed_modules_count),
                $this->completed_modules_count
            ),
            'total_modules_count' => $this->when(
                isset($this->total_modules_count),
                $this->total_modules_count
            ),
            'total_time_spent' => $this->when(
                isset($this->total_time_spent),
                $this->total_time_spent
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
