<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseModuleResource extends JsonResource
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
            'course_id' => $this->course_id,
            'parent_module_id' => $this->parent_module_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'content_type' => $this->content_type,
            'content' => $this->content,
            'comparison_points' => $this->comparison_points,
            'code_examples' => $this->code_examples,
            'resources' => $this->resources,
            'estimated_minutes' => $this->estimated_minutes,
            'is_published' => $this->is_published,

            // Sub-modules (if loaded)
            'sub_modules' => $this->whenLoaded('subModules', function () {
                return CourseModuleResource::collection($this->subModules);
            }),

            // Assessments (if loaded)
            'assessments' => $this->whenLoaded('assessments', function () {
                return AssessmentResource::collection($this->assessments);
            }),

            // Navigation
            'next_module' => $this->when(isset($this->next_module), function () {
                return $this->next_module ? [
                    'id' => $this->next_module->id,
                    'title' => $this->next_module->title,
                    'slug' => $this->next_module->slug,
                ] : null;
            }),
            'previous_module' => $this->when(isset($this->previous_module), function () {
                return $this->previous_module ? [
                    'id' => $this->previous_module->id,
                    'title' => $this->previous_module->title,
                    'slug' => $this->previous_module->slug,
                ] : null;
            }),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
