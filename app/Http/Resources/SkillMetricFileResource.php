<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillMetricFileResource extends JsonResource
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
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'file_size_human' => $this->file_size_human,
            'file_type' => $this->file_type,
            'status' => $this->status,
            'trainee_count' => $this->trainee_count,
            'timeline_weeks' => $this->timeline_weeks,
            'special_requirements' => $this->special_requirements,

            // Processing information
            'processed_at' => $this->processed_at?->toIso8601String(),
            'error_message' => $this->when($this->status === 'failed', $this->error_message),

            // Extracted data (only show if completed)
            'extracted_data' => $this->when(
                $this->status === 'completed' && $this->extracted_data,
                $this->extracted_data
            ),

            // Skills
            'source_skills' => $this->when(
                $this->status === 'completed' && $this->source_skills,
                $this->source_skills
            ),
            'target_skills' => $this->when(
                $this->status === 'completed' && $this->target_skills,
                $this->target_skills
            ),

            // Company (if loaded)
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),

            // Uploader (if loaded)
            'uploader' => $this->whenLoaded('uploader', function () {
                return [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ];
            }),

            // Generated courses (if loaded)
            'courses' => $this->whenLoaded('courses', function () {
                return $this->courses->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'status' => $course->status,
                    ];
                });
            }),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
