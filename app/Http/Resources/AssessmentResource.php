<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
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
            'module_id' => $this->module_id,
            'title' => $this->title,
            'description' => $this->description,
            'assessment_type' => $this->assessment_type,
            'passing_score' => $this->passing_score,
            'time_limit_minutes' => $this->time_limit_minutes,
            'max_attempts' => $this->max_attempts,
            'is_required' => $this->is_required,

            // Questions (hide correct answers for trainees taking the assessment)
            'questions' => $this->when(
                $request->user()->hasAnyRole(['super_admin', 'curator']),
                $this->questions,
                // For trainees, remove correct answers
                collect($this->questions)->map(function ($question) {
                    unset($question['correct_answer']);
                    unset($question['explanation']);
                    return $question;
                })
            ),

            // Computed attributes
            'question_count' => $this->question_count,
            'total_points' => $this->total_points,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
