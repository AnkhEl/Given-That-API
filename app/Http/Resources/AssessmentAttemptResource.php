<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentAttemptResource extends JsonResource
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
            'assessment_id' => $this->assessment_id,
            'user_id' => $this->user_id,
            'enrollment_id' => $this->enrollment_id,
            'attempt_number' => $this->attempt_number,
            'score' => (float) $this->score,
            'score_percentage' => $this->score_percentage,
            'passed' => $this->passed,
            'started_at' => $this->started_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'time_taken_seconds' => $this->time_taken_seconds,
            'time_taken_human' => $this->time_taken_human,

            // Answers (show for curators/admins or the user themselves)
            'answers' => $this->when(
                $request->user()->hasAnyRole(['super_admin', 'curator']) ||
                $request->user()->id == $this->user_id,
                $this->answers
            ),

            // Feedback (detailed results)
            'feedback' => $this->when(
                $request->user()->hasAnyRole(['super_admin', 'curator']) ||
                $request->user()->id == $this->user_id,
                $this->feedback
            ),

            // User (if loaded and has permission)
            'user' => $this->when(
                $this->relationLoaded('user') &&
                $request->user()->hasAnyRole(['super_admin', 'curator', 'company_admin']),
                function () {
                    return [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                    ];
                }
            ),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
