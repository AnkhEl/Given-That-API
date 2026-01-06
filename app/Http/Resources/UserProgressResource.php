<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProgressResource extends JsonResource
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
            'enrollment_id' => $this->enrollment_id,
            'module_id' => $this->module_id,
            'status' => $this->status,
            'progress_percentage' => (float) $this->progress_percentage,
            'time_spent_seconds' => $this->time_spent_seconds,
            'time_spent_human' => $this->time_spent_human,
            'last_position' => $this->last_position,
            'notes' => $this->notes,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            // Module (if loaded)
            'module' => $this->whenLoaded('module', function () {
                return [
                    'id' => $this->module->id,
                    'title' => $this->module->title,
                    'slug' => $this->module->slug,
                    'order_index' => $this->module->order_index,
                    'estimated_minutes' => $this->module->estimated_minutes,
                ];
            }),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
