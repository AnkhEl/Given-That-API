<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'learning_objectives' => $this->learning_objectives,
            'prerequisites' => $this->prerequisites,
            'difficulty_level' => $this->difficulty_level,
            'estimated_hours' => $this->estimated_hours,
            'status' => $this->status,
            'thumbnail_url' => $this->thumbnail_url,
            'tags' => $this->tags,

            // Skills (if loaded)
            'source_skill' => $this->whenLoaded('sourceSkill', function () {
                return new SkillResource($this->sourceSkill);
            }),
            'target_skill' => $this->whenLoaded('targetSkill', function () {
                return new SkillResource($this->targetSkill);
            }),

            // Curator (if loaded)
            'curator' => $this->whenLoaded('curator', function () {
                return [
                    'id' => $this->curator->id,
                    'name' => $this->curator->name,
                ];
            }),

            // Modules (if loaded)
            'modules' => $this->whenLoaded('modules', function () {
                return CourseModuleResource::collection($this->modules);
            }),

            // Assessments (if loaded)
            'assessments' => $this->whenLoaded('assessments', function () {
                return AssessmentResource::collection($this->assessments);
            }),

            // Computed attributes
            'module_count' => $this->when(isset($this->module_count), $this->module_count),
            'enrolled_count' => $this->when(isset($this->enrolled_count), $this->enrolled_count),

            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
